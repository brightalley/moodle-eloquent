<?php

namespace local_eloquent\eloquent;

use Illuminate\Database\Eloquent\Model as base_model;
use local_eloquent\connection;

class model extends base_model {

    /**
     * Indicates if all mass assignment is enabled.
     *
     * @var bool
     */
    protected static $unguarded = true;

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'timecreated';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'timeupdated';

    /**
     * Get a fresh timestamp for the model.
     *
     * @return int
     */
    public function freshTimestamp()
    {
        return time();
    }

    /**
     * Get a fresh timestamp for the model.
     *
     * @return int
     */
    public function freshTimestampString()
    {
        return time();
    }

    /**
     * Get the attributes that should be converted to dates.
     *
     * @return array
     */
    public function getDates()
    {
        return $this->dates;
    }

    /**
     * Get the database connection for the model.
     *
     * @return \Illuminate\Database\Connection
     */
    public function getConnection()
    {
        return static::resolveConnection();
    }

    /**
     * Resolve a connection instance.
     *
     * @param  string|null  $connection
     * @return \Illuminate\Database\Connection
     */
    public static function resolveConnection($connection = null)
    {
        return connection::instance();
    }
}
