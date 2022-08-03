<?php

namespace AJUR\FSNews;

interface LongreadsHelperInterface
{
    public static function rmdir($directory): bool;
    public static function makeReplaceQuery(string $table, array &$dataset, string $where = '');
    public static function makeInsertQuery(string $table, &$dataset):string;
    public static function makeUpdateQuery(string $table, &$dataset, $where_condition):string;
}

