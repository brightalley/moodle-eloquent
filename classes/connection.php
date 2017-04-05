<?php

namespace local_eloquent;

use Closure;
use DateTimeInterface;
use Exception;
use Illuminate\Database\Concerns\ManagesTransactions;
use Illuminate\Database\Connection as base_connection;
use Illuminate\Database\DetectsDeadlocks;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\Grammar as QueryGrammar;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use local_eloquent\query\processors\processor;
use moodle_database;

abstract class connection extends base_connection {
    /**
     * @var connection The connection singleton.
     */
    protected static $instance;

    /**
     * @var moodle_database The Moodle database connection.
     */
    protected $connection;

    /**
     * Create a new database connection instance.
     *
     * @param \moodle_database $db
     */
    public function __construct(moodle_database $db) {
        global $CFG;

        $this->db = $db;

        // First we will setup the default properties. We keep track of the DB
        // name we are connected to since it is needed when some reflective
        // type commands are run such as checking whether a table exists.
        $this->database = $CFG->dbname;

        $this->tablePrefix = $this->db->get_prefix();

        // We need to initialize a query grammar and the query post processors
        // which are both very important parts of the database abstractions
        // so we initialize these to their default values while starting.
        $this->useDefaultQueryGrammar();

        $this->useDefaultPostProcessor();
    }

    /**
     * Get the connection singleton instance.
     *
     * @return connection
     */
    public static function instance() {
        if (static::$instance === null) {
            static::$instance = static::resolve_connection();
        }

        return static::$instance;
    }

    /**
     * Resolve the correct database connection, depending on the Moodle database
     * family.
     *
     * @return connection
     */
    protected static function resolve_connection() {
        global $DB;

        switch ($DB->get_dbfamily()) {
            case 'mysql':
                return new mysql_connection($DB);
            default:
                throw new \Exception('Unsupported database family: ' . $DB->get_dbfamily());
        }
    }

    /**
     * Run a select statement against the database.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return [];
            }

            // For select statements, we'll simply execute the query and return an array
            // of the database result set. Each element in the array will be a single
            // row from the database table, and will either be an array or objects.
            $recordset = $this->db->get_recordset_sql($query, $bindings);

            // We use a record set to work around Moodle's insistence that the value
            // of the first column should always be unique.
            $records = [];
            foreach ($recordset as $record) {
                $records[] = $record;
            }

            $recordset->close();

            return $records;
        });
    }

    /**
     * Run a select statement against the database and returns a generator.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return \Generator
     */
    public function cursor($query, $bindings = [], $useReadPdo = true)
    {
        $recordset = $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return [];
            }

            return $this->db->get_recordset_sql($query, $bindings);
        });

        foreach ($recordset as $record) {
            yield $record;
        }

        $recordset->close();
    }

    /**
     * Run an insert statement against the database.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function insert($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return 0;
            }

            // Moodle does not support a raw insert query, so this... sucks. Extract
            // the column names from the query. Luckily the Laravel queries have a
            // pretty well-defined format.
            $matches = [];
            if (!preg_match_all('/`([^`]+)`/', str_replace(',', ',' . PHP_EOL, $query), $matches)) {
                var_dump('cannot match query!!');
            }

            // Identifiers is now an array containing the table name (including
            // prefix), and the column names that are being inserted.
            $identifiers = $matches[1];

            // Strip off the table prefix, since Moodle re-adds it.
            $table = substr($identifiers[0], strlen($this->tablePrefix));

            // Get the columns. If there is no id column, we should use a regular
            // statement, since otherwise Moodle will try to extract the inserted
            // id, and throw an error.
            $columns = $this->db->get_columns($table);
            if (!array_key_exists('id', $columns)) {
                return $this->db->execute($query, $bindings);
            }

            $columns = array_slice($identifiers, 1);
            if (count($columns) == count($bindings)) {
                // Combine the columns with the bindings to produce an associative
                // array of column -> value.
                $row = array_combine($columns, $bindings);

                return $this->db->insert_record_raw($table, $row);
            }

            // Sanity check.
            if (count($bindings) % count($columns) != 0) {
                var_dump('sanity check for bulk insert failed!!!');
            }

            // There are multiple rows.
            $rows = [];
            for ($i = 0; $i < count($bindings); $i += count($columns)) {
                $rows[] = array_combine($columns, array_slice($bindings, $i, count($columns)));
            }

            $this->db->insert_records($table, $rows);
            return true;
        });
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return true;
            }

            return (bool) $this->db->execute($query, $bindings);
        });
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return 0;
            }

            $this->db->execute($query, $bindings);

            // Moodle unfortunately does not provide us with the number of records
            // affected by a query.
            return -1;
        });
    }

    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param  string  $query
     * @return bool
     */
    public function unprepared($query)
    {
        return $this->run($query, [], function ($query) {
            if ($this->pretending()) {
                return true;
            }

            return (bool) $this->db->execute($query);
        });
    }

    /**
     * Run a SQL statement and log its execution context.
     *
     * @param  string    $query
     * @param  array     $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Illuminate\Database\QueryException
     */
    protected function run($query, $bindings, Closure $callback)
    {
        $start = microtime(true);

        // Here we will run this query.
        $result = $this->runQueryCallback($query, $bindings, $callback);

        // Once we have run the query we will calculate the time that it took to run and
        // then log the query, bindings, and execution time so we will report them on
        // the event that the developer needs them. We'll log time in milliseconds.
        $this->logQuery(
            $query, $bindings, $this->getElapsedTime($start)
        );

        return $result;
    }

    /**
     * Log a query in the connection's query log.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @param  float|null  $time
     * @return void
     */
    public function logQuery($query, $bindings, $time = null)
    {
        if ($this->loggingQueries) {
            $this->queryLog[] = compact('query', 'bindings', 'time');
        }
    }

    /**
     * Disconnect from the underlying PDO connection.
     *
     * @return void
     */
    public function disconnect()
    {
        $this->db->dispose();
    }

    /**
     * Is Doctrine available?
     *
     * @return bool
     */
    public function isDoctrineAvailable()
    {
        return false;
    }

    /**
     * Get the current PDO connection.
     *
     * @return \PDO
     */
    public function getPdo()
    {
        throw new Exception('Invalid call to getPdo, not supported by Moodle connection.');
    }

    /**
     * Get the current PDO connection used for reading.
     *
     * @return \PDO
     */
    public function getReadPdo()
    {
        return $this->getPdo();
    }

    /**
     * Get the database connection name.
     *
     * @return string|null
     */
    public function getName()
    {
        return 'moodle';
    }

    /**
     * Get the PDO driver name.
     *
     * @return string
     */
    public function getDriverName()
    {
        return $this->db->get_dbfamily();
    }

    /**
     * Start a new database transaction.
     *
     * @return void
     */
    public function beginTransaction()
    {
        throw new Exception('transactions are not yet supported');
        $this->db->start_delegated_transaction();
    }

    /**
     * Commit the active database transaction.
     *
     * @return void
     */
    public function commit()
    {
        throw new Exception('transactions are not yet supported');
        $this->db->commit_delegated_transaction();
    }

    /**
     * Rollback the active database transaction.
     *
     * @return void
     */
    public function rollBack()
    {
        throw new Exception('transactions are not yet supported');
        $this->db->rollback_delegated_transaction();
    }
}
