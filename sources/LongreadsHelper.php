<?php

namespace AJUR\FSNews;

class LongreadsHelper implements LongreadsHelperInterface
{
    public static function rmdir($directory): bool
    {
        if (!is_dir( $directory )) {
            return false;
        }

        $files = array_diff( scandir( $directory ), [ '.', '..' ] );

        foreach ($files as $file) {
            $target = "{$directory}/{$file}";
            (is_dir( $target ))
                ? self::rmdir( $target )
                : unlink( $target );
        }
        return rmdir( $directory );
    }

    public static function makeReplaceQuery(string $table, array &$dataset, string $where = '')
    {
        $fields = [];

        if (empty($dataset))
            return false;

        $query = "REPLACE `{$table}` SET ";

        foreach ($dataset as $index => $value) {
            if (strtoupper(trim($value)) === 'NOW()') {
                $fields[] = "`{$index}` = NOW()";
                unset($dataset[ $index ]);
                continue;
            }

            $fields[] = "`{$index}` = :{$index}";
        }

        $query .= implode(', ', $fields);

        $query .= "{$where}; ";

        return $query;
    }

    /**
     * Строит INSERT-запрос на основе массива данных для указанной таблицы.
     * В массиве допустима конструкция 'key' => 'NOW()'
     * В этом случае она будет добавлена в запрос и удалена из набора данных (он пере).
     *
     * @param $table    -- таблица
     * @param $dataset      -- передается по ссылке, мутабелен
     * @return string       -- результирующая строка запроса
     */
    public static function makeInsertQuery(string $table, &$dataset):string
    {
        if (empty($dataset)) {
            return "INSERT INTO {$table} () VALUES (); ";
        }

        $set = [];

        $query = "INSERT INTO `{$table}` SET ";

        foreach ($dataset as $index => $value) {
            if (strtoupper(trim($value)) === 'NOW()') {
                $set[] = "`{$index}` = NOW()";
                unset($dataset[ $index ]);
                continue;
            }

            $set[] = "`{$index}` = :{$index}";
        }

        $query .= implode(', ', $set) . ' ;';

        return $query;
    }

    /**
     * Build UPDATE query by dataset for given table
     *
     * @param $tablename
     * @param $dataset
     * @param $where_condition
     * @return bool|string
     */
    public static function makeUpdateQuery(string $table, &$dataset, $where_condition):string
    {
        $set = [];

        if (empty($dataset)) {
            return false;
        }

        $query = "UPDATE `{$table}` SET";

        foreach ($dataset as $index => $value) {
            if (strtoupper(trim($value)) === 'NOW()') {
                $set[] = "`{$index}` = NOW()";
                unset($dataset[ $index ]);
                continue;
            }

            $set[] = "`{$index}` = :{$index}";
        }

        $query .= implode(', ', $set);

        if (is_array($where_condition)) {
            $where_condition = key($where_condition) . ' = ' . current($where_condition);
        }

        if ( is_string($where_condition ) && !strpos($where_condition, 'WHERE')) {
            $where_condition = " WHERE {$where_condition}";
        }

        if (is_null($where_condition)) {
            $where_condition = '';
        }

        $query .= " $where_condition ;";

        return $query;
    }

}