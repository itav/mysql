<?php

namespace Itav\Component\Mysql;

interface DriverInterface
{

    public function execute($query, $inputarray = null);

    public function getAll($query = null, $inputarray = null);

    public function getAllByKey($query = null, $key = null, $inputarray = null);

    public function getRow($query = null, $inputarray = null);

    public function getCol($query = null, $inputarray = null);

    public function getOne($query = null, $inputarray = null);

    public function concat();

    public function now();

    public function listTables();

    public function beginTrans();

    public function commitTrans();

    public function rollbackTrans();

    public function lockTables($table, $locktype = null);

    public function unLockTables();

    public function getDBVersion();

    public function setEncoding($name);

    public function getLastInsertId($table = null);

    public function escape($input);

    public function groupConcat($field, $separator = ',');
}
