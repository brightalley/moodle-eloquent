<?php

namespace local_eloquent\query\processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor as base_processor;

class processor extends base_processor{
    /**
     * Process an "insert get ID" query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $sql
     * @param  array   $values
     * @param  string  $sequence
     * @return int
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $id = $query->getConnection()->insert($sql, $values);

        return is_numeric($id) ? (int) $id : $id;
    }
}
