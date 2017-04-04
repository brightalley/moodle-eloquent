<?php

namespace local_eloquent\query\processors;

class mysql_processor extends processor
{
    /**
     * Process the results of a column listing query.
     *
     * @param  array  $results
     * @return array
     */
    public function processColumnListing($results)
    {
        return array_map(function ($result) {
            return with((object) $result)->column_name;
        }, $results);
    }
}
