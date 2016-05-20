<?php

namespace Itav\Component\Mysql;

class MysqlDriver extends AbstractDriver implements DriverInterface {

    protected $_loaded = true;
    protected $_dbtype = 'mysql';

    public function __construct($dbhost, $dbuser, $dbpasswd, $dbname) {
        if (!extension_loaded('mysql')) {
            trigger_error('MySQL extension not loaded!', E_USER_WARNING);
            $this->_loaded = FALSE;
            return;
        }
        return parent::__construct($dbhost, $dbuser, $dbpasswd, $dbname);
    }

    protected function _driver_dbversion() {
        return \mysql_get_server_info();
    }

    protected function _driver_connect($dbhost, $dbuser, $dbpasswd, $dbname) {
        if ($this->_dblink = @mysql_connect($dbhost, $dbuser, $dbpasswd, true)) {
            $this->_dbhost = $dbhost;
            $this->_dbuser = $dbuser;
            $this->_driver_selectdb($dbname);
        } else {
            $this->_error = TRUE;
        }

        return $this->_dblink;
    }

    protected function _driver_shutdown() {
        $this->_loaded = FALSE;
        \mysql_close($this->_dblink);
    }

    protected function _driver_geterror() {
        if ($this->_dblink) {
            return mysql_error($this->_dblink);
        } elseif ($this->_query) {
            return 'We\'re not connected!';
        } else {
            return mysql_error();
        }
    }

    protected function _driver_disconnect() {
        return \mysql_close($this->_dblink);
    }

    protected function _driver_selectdb($dbname) {
        if ($result = mysql_select_db($dbname, $this->_dblink)) {
            $this->_dbname = $dbname;
        }
        return $result;
    }

    protected function _driver_execute($query) {
        $this->_query = $query;

        if ($this->_result = \mysql_query($query, $this->_dblink)) {
            $this->_error = FALSE;
        } else {
            $this->_error = TRUE;
        }
        return $this->_result;
    }

    protected function _driver_fetchrow_assoc($result = NULL) {
        if (!$this->_error) {
            return \mysql_fetch_array($result ? $result : $this->_result, \MYSQL_ASSOC);
        } else {
            return false;
        }
    }

    protected function _driver_fetchrow_num() {
        if (!$this->_error) {
            return \mysql_fetch_array($this->_result, \MYSQL_NUM);
        } else {
            return false;
        }
    }

    protected function _driver_affected_rows() {
        if (!$this->_error) {
            return \mysql_affected_rows();
        } else {
            return false;
        }
    }

    protected function _driver_num_rows() {
        if (!$this->_error) {
            return \mysql_num_rows($this->_result);
        } else {
            return false;
        }
    }

    protected function _driver_now() {
        return 'UNIX_TIMESTAMP()';
    }

    protected function _driver_like() {
        return 'LIKE';
    }

    protected function _driver_concat($input) {
        $return = implode(', ', $input);
        return 'CONCAT(' . $return . ')';
    }

    protected function _driver_listtables() {
        return $this->GetCol('SELECT table_name FROM information_schema.tables
	                        WHERE table_type = ? AND table_schema = ?', array('BASE TABLE', $this->_dbname));
    }

    protected function _driver_begintrans() {
        return $this->Execute('BEGIN');
    }

    protected function _driver_committrans() {
        return $this->Execute('COMMIT');
    }

    protected function _driver_rollbacktrans() {
        return $this->Execute('ROLLBACK');
    }

    protected function _driver_locktables($table, $locktype = null) {
        $locktype = $locktype ? strtoupper($locktype) : 'WRITE';

        if (is_array($table)) {
            $this->Execute('LOCK TABLES ' . implode(' ' . $locktype . ', ', $table) . ' ' . $locktype);
        } else {
            $this->Execute('LOCK TABLES ' . $table . ' ' . $locktype);
        }
    }

    protected function _driver_unlocktables() {
        $this->Execute('UNLOCK TABLES');
    }

    protected function _driver_lastinsertid() {
        return $this->GetOne('SELECT LAST_INSERT_ID()');
    }

    protected function _driver_groupconcat($field, $separator = ',') {
        return 'GROUP_CONCAT(' . $field . ' SEPARATOR \'' . $separator . '\')';
    }

}
