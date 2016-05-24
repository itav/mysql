<?php

namespace Itav\Component\Mysql;

abstract class AbstractDriver implements DriverInterface
{

    protected $_version = '0.1';
    protected $_loaded = false;
    protected $_dbtype = 'NONE';
    protected $_dblink = null;
    protected $_dbhost = null;
    protected $_dbuser = null;
    protected $_dbname = null;
    protected $_error = false;
    protected $_query = null;
    protected $_result = null;
    protected $errors = [];
    protected $debug = false;

    public function __construct($dbhost, $dbuser, $dbpasswd, $dbname)
    {
        $this->Connect($dbhost, $dbuser, $dbpasswd, $dbname);
    }

    /**
     * @param $dbhost
     * @param $dbuser
     * @param $dbpasswd
     * @param $dbname
     * @return bool|null
     */
    protected function Connect($dbhost, $dbuser, $dbpasswd, $dbname)
    {
        if (method_exists($this, '_driver_shutdown')) {
            register_shutdown_function(array($this, '_driver_shutdown'));
        }

        if ($this->_driver_connect($dbhost, $dbuser, $dbpasswd, $dbname)) {
            return $this->_dblink;
        } else {
            $this->errors[] = array(
                'query' => 'database connect',
                'error' => $this->_driver_geterror(),
            );
            return FALSE;
        }
    }

    public function destroy()
    {
        return $this->_driver_disconnect();
    }

    /**
     * @param $query
     * @param null $inputarray
     * @return mixed
     */
    public function execute($query, $inputarray = NULL)
    {
        if (!$this->_driver_execute($this->_query_parser($query, $inputarray))) {
            $this->errors[] = [
                'query' => $this->_query,
                'error' => $this->_driver_geterror()
            ];
        } elseif ($this->debug) {
            $this->errors[] = array(
                'query' => $this->_query,
                'error' => 'DEBUG: NOERROR'
            );
        }
        return $this->_driver_affected_rows();
    }

    public function getAll($query = NULL, $inputarray = NULL)
    {
        if ($query) {
            $this->execute($query, $inputarray);
        }

        $result = NULL;

        while ($row = $this->_driver_fetchrow_assoc()) {
            $result[] = $row;
        }

        return $result;
    }

    public function getAllByKey($query = NULL, $key = NULL, $inputarray = NULL)
    {
        if ($query) {
            $this->execute($query, $inputarray);
        }

        $result = NULL;

        while ($row = $this->_driver_fetchrow_assoc()) {
            $result[$row[$key]] = $row;
        }

        return $result;
    }

    public function getRow($query = NULL, $inputarray = NULL)
    {
        if ($query) {
            $this->execute($query, $inputarray);
        }

        return $this->_driver_fetchrow_assoc();
    }

    public function getCol($query = NULL, $inputarray = NULL)
    {
        if ($query) {
            $this->execute($query, $inputarray);
        }

        $result = NULL;

        while ($row = $this->_driver_fetchrow_num()) {
            $result[] = $row[0];
        }

        return $result;
    }

    public function getOne($query = NULL, $inputarray = NULL)
    {
        if ($query) {
            $this->execute($query, $inputarray);
        }

        $result = null;

        list($result) = $this->_driver_fetchrow_num();

        return $result;
    }

    // with exec() & FetchRow() we can do big results looping
    // in less memory consumptive way than using getAll() & foreach()
    protected function exec($query, $inputarray = NULL)
    {
        if (!$this->_driver_execute($this->_query_parser($query, $inputarray))) {
            $this->errors[] = array(
                'query' => $this->_query,
                'error' => $this->_driver_geterror()
            );
        } elseif ($this->debug) {
            $this->errors[] = array(
                'query' => $this->_query,
                'error' => 'DEBUG: NOERROR'
            );
        }

        if ($this->_driver_num_rows()) {
            return $this->_result;
        } else {
            return null;
        }
    }

    public function FetchRow($result)
    {
        return $this->_driver_fetchrow_assoc($result);
    }

    public function concat()
    {
        return $this->_driver_concat(func_get_args());
    }

    /**
     * @return int
     */
    public function now()
    {
        return $this->_driver_now();
    }

    public function listTables()
    {
        return $this->_driver_listtables();
    }

    public function beginTrans()
    {
        return $this->_driver_begintrans();
    }

    public function commitTrans()
    {
        return $this->_driver_committrans();
    }

    public function rollbackTrans()
    {
        return $this->_driver_rollbacktrans();
    }

    public function lockTables($table, $locktype = null)
    {
        return $this->_driver_locktables($table, $locktype);
    }

    public function unLockTables()
    {
        return $this->_driver_unlocktables();
    }

    public function getDBVersion()
    {
        return $this->_driver_dbversion();
    }

    public function setEncoding($name)
    {
        return $this->_driver_setencoding($name);
    }

    public function getLastInsertId($table = null)
    {
        return $this->_driver_lastinsertid($table);
    }

    public function escape($input)
    {
        return $this->_quote_value($input);
    }

    protected function _query_parser($query, $inputarray = null)
    {
        //Jeżeli uzywamy mysql'a tylko to nie trzeba podmieniać now i like
        //$query = preg_replace('/\?NOW\?/i', $this->_driver_now(), $query);
        //$query = preg_replace('/\?LIKE\?/i', $this->_driver_like(), $query);

        if ($inputarray) {
            $queryelements = explode("\0", str_replace('?', "?\0", $query));
            $query = '';
            foreach ($queryelements as $queryelement) {
                if (strpos($queryelement, '?') !== FALSE) {
                    $value = each($inputarray)['value'];
                    $queryelement = str_replace('?', $this->_quote_value($value), $queryelement);
                }
                $query .= $queryelement;
            }
        }
        return $query;
    }

    protected function _quote_value($input)
    {
        if ($input === null) {
            return 'NULL';
        } elseif (gettype($input) == 'string') {
            return '\'' . addcslashes($input, "'\\\0") . '\'';
        } else {
            return $input;
        }
    }

    protected function _driver_now()
    {
        return time();
    }

    protected function _driver_like()
    {
        return 'LIKE';
    }

    protected function _driver_setencoding($name)
    {
        $this->execute('SET NAMES ?', array($name));
    }

    public function groupConcat($field, $separator = ',')
    {
        return $this->_driver_groupconcat($field, $separator);
    }

    abstract protected function _driver_connect($dbhost, $dbuser, $dbpasswd, $dbname);

    abstract protected function _driver_geterror();

    abstract protected function _driver_execute($_query_parser);

    abstract protected function _driver_disconnect();

    abstract protected function _driver_lastinsertid($table = null);

    abstract protected function _driver_dbversion();

    abstract protected function _driver_unlocktables();

    abstract protected function _driver_fetchrow_assoc($result = null);

    abstract protected function _driver_listtables();

    abstract protected function _driver_rollbacktrans();

    abstract protected function _driver_groupconcat($field, $separator);

    abstract protected function _driver_locktables($table, $locktype);

    abstract protected function _driver_committrans();

    abstract protected function _driver_begintrans();

    abstract protected function _driver_fetchrow_num();

    abstract protected function _driver_num_rows();

    abstract protected function _driver_concat($func_get_args);

    abstract protected function _driver_affected_rows();
}
