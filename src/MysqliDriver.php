<?php

namespace Itav\Component\Mysql;

class MysqliDriver extends AbstractDriver implements DriverInterface
{

    protected $_loaded = true;
    protected $_dbtype = 'mysqli';

    public function __construct($dbhost, $dbuser, $dbpasswd, $dbname)
    {
        if (!extension_loaded('mysqli')) {
            \trigger_error('MySQLi extension not loaded!', E_USER_WARNING);
            $this->_loaded = false;
            return;
        }
        if (method_exists($this, '_driver_shutdown')) {
            register_shutdown_function(array($this, '_driver_shutdown'));
        }
        parent::__construct($dbhost, $dbuser, $dbpasswd, $dbname);
    }

    protected function _driver_dbversion()
    {
        return \mysqli_get_server_info($this->_dblink);
    }

    protected function _driver_connect($dbhost, $dbuser, $dbpasswd, $dbname)
    {
        if ($this->_dblink = new \mysqli($dbhost, $dbuser, $dbpasswd, $dbname)) {
            $this->_dbhost = $dbhost;
            $this->_dbuser = $dbuser;
            $this->_dbname = $dbname;
        } else {
            $this->_error = true;
        }
        return $this->_dblink;
    }

    protected function _driver_shutdown()
    {
        $this->_loaded = false;
        \mysqli_close($this->_dblink); // apparently, mysqli_close() is automagicly called after end of the script...
    }

    protected function _driver_geterror()
    {
        if ($this->_dblink) {
            return mysqli_error($this->_dblink);
        } elseif ($this->_query) {
            return 'We\'re not connected!';
        } else {
            return \mysqli_connect_error();
        }
    }

    protected function _driver_disconnect()
    {
        return \mysqli_close($this->_dblink);
    }

    protected function _driver_execute($query)
    {
        $this->_query = $query;

        if ($this->_result = \mysqli_query($this->_dblink, $query)) {
            $this->_error = false;
        } else {
            $this->_error = true;
        }
        return $this->_result;
    }

    protected function _driver_fetchrow_assoc($result = null)
    {
        if (!$this->_error) {
            return \mysqli_fetch_array($result ? $result : $this->_result, \MYSQLI_ASSOC);
        } else {
            return false;
        }
    }

    protected function _driver_fetchrow_num()
    {
        if (!$this->_error) {
            return \mysqli_fetch_array($this->_result, \MYSQLI_NUM);
        } else {
            return false;
        }
    }

    protected function _driver_affected_rows()
    {
        if (!$this->_error) {
            return \mysqli_affected_rows($this->_dblink);
        } else {
            return false;
        }
    }

    protected function _driver_num_rows()
    {
        if (!$this->_error) {
            return \mysqli_num_rows($this->_result);
        } else {
            return false;
        }
    }

    protected function _driver_now()
    {
        return 'UNIX_TIMESTAMP()';
    }

    protected function _driver_like()
    {
        return 'LIKE';
    }

    protected function _driver_concat($input)
    {
        $return = implode(', ', $input);
        return 'CONCAT(' . $return . ')';
    }

    protected function _driver_listtables()
    {
        return $this->getCol('SELECT table_name FROM information_schema.tables
				WHERE table_type = ? AND table_schema = ?', ['BASE TABLE', $this->_dbname]);
    }

    protected function _driver_begintrans()
    {
        return $this->execute('BEGIN');
    }

    protected function _driver_committrans()
    {
        return $this->execute('COMMIT');
    }

    protected function _driver_rollbacktrans()
    {
        return $this->execute('ROLLBACK');
    }

    protected function _driver_locktables($table, $locktype = null)
    {
        $locktype = $locktype ? strtoupper($locktype) : 'WRITE';

        if (is_array($table)) {
            $this->execute('LOCK TABLES ' . implode(' ' . $locktype . ', ', $table) . ' ' . $locktype);
        } else {
            $this->execute('LOCK TABLES ' . $table . ' ' . $locktype);
        }
    }

    protected function _driver_unlocktables()
    {
        $this->execute('UNLOCK TABLES');
    }

    protected function _driver_lastinsertid($table = null)
    {
        return $this->getOne('SELECT LAST_INSERT_ID()');
    }

    protected function _driver_groupconcat($field, $separator = ',')
    {
        return 'GROUP_CONCAT(' . $field . ' SEPARATOR \'' . $separator . '\')';
    }

}
