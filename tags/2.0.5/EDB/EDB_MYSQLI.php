<?php
/**
 * Project: EDB_MYSQLI :: MySQLi abstraction layer
 * File:    EDB/EDB_MYSQLI.php
 *
 * The EDB_MYSQLI class is mysqli abstraction layer that used internally
 * on EDB class.
 *
 * @category    Database
 * @package     EDB
 * @subpackage  EDB_ABSTRACT
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 2012, JoungKyun.Kim
 * @license     BSD License
 * @version     $Id$
 * @link        http://pear.oops.org/package/EDB
 * @filesource
 */

/**
 * MySQLi engine for EDB API
 *
 * This class support abstracttion DB layer for MySQLi Engine
 *
 * @package     EDB
 */
Class EDB_MYSQLI extends EDB_Common {
	// {{{ properties
	/**#@+
	 * @access private
	 */
	/**
	 * db handler of EDB_MYSQLI class
	 * @var    object
	 */
	private $db;
	/**
	 * The number of query parameter
	 * @var    integer
	 */
	private $pno = 0;
	/**
	 * The number of query parameter
	 * @var    integer
	 */
	private $field = array ();
	/**#@-*/
	// }}}

	// {{{ (object) EDB_MYSQLI::__construct ($host, $user, $pass, $db)
	/** 
	 * Instantiates an EDB_MYSQLI object and opens an mysql database
	 *
	 * For examples:
	 * <code>
	 * $db = new EDB_MYSQLI ('mysqli://localhost', 'user', 'host', 'database');
	 * $db = new EDB_MYSQLI ('mysqli://localhost:3306', 'user', 'host', 'database');
	 * $db = new EDB_MYSQLI ('mysqli://localhost:/var/run/mysqld/mysql.socl', 'user', 'host', 'database');
	 * </code>
	 *
	 * If you add prefix 'p~' before host, you can connect with persistent
	 * connection.
	 *
	 * For Examples:
	 * <code>
	 * $db = new EDB_MYSQLI ('mysqli://p~localhost', 'user', 'host', 'database');
	 * </code>
	 *
	 * @access public
	 * @return EDB_MYSQLI
	 * @param  string  $hostname mysql host
	 * @param  string  $user     mysql user
	 * @param  string  $password mysql password
	 * @param  string  $database mysql database
	 */
	function __construct () {
		$_argv = func_get_args ();
		$argv = is_array ($_argv[0]) ? $_argv[0] : $_argv;;

		if ( ! extension_loaded ('mysqli') )
			throw new myException ('MySQLi extension is not loaded on PHP!', E_USER_ERROR);

		$o = (object) array (
			'host' => preg_replace ('!^mysqli://!', '', $argv[0]),
			'user' => $argv[1],
			'pass' => $argv[2],
			'db'   => $argv[3]
		);

		if ( preg_match ('/([^:]+):(.*)/', $o->host, $matches) ) {
			$o->host = $matches[1];
			$o->port = $matches[2];
		} else
			$o->port = 3306;

		if ( ! is_numeric ($o->port) ) {
			$o->sock = $o->port;
			$o->port = 3306;
		} else
			$o->sock = null;

		// set persistent connect
		$o->host = preg_replace ('/^p~/', 'p:', $o->host);

		try {
			$this->db = new mysqli ($o->host, $o->user, $o->pass, $o->db, $o->port, $o->sock);
		} catch ( Exception $e ) {
			if ( mysqli_connect_error () )
				throw new myException (mysqli_connect_error (), E_USER_ERROR);
			else
				throw new myException ($e->getMessage (), $e->getCode(), $e);
		}
	}
	// }}}

	// {{{ (string) EDB_MYSQLI::get_charset (void)
	/** 
	 * Get character set of current database
	 *
	 * @access public
	 * @return string Current character set name on DB
	 */
	function get_charset () {
		try {
			return $this->db->character_set_name ();
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (bool) EDB_MYSQLI::set_charset ($charset)
	/** 
	 * Set character set of current database
	 *
	 * @access public
	 * @return bool    The name of character set that is supported on database
	 * @param  string  name of character set that supported from database
	 */
	function set_charset ($char) {
		try {
			if ( ! is_object ($this->db) )
				return false;
			return $this->db->set_charset ($char);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (string) EDB_MYSQLI::escape ($string)
	/** 
	 * Escape special characters in a string for use in an SQL statement
	 *
	 * @access public
	 * @return string
	 * @param  string  The string that is to be escaped.
	 */
	function escape ($string) {
		return $this->db->real_escape_string ($string);
	}
	// }}}

	// {{{ (int) EDB_MYSQLI::query ($query, $param_type, $param1, $param2 ...)
	/** 
	 * Performs a query on the database
	 *
	 * @access public
	 * @return integer The number of affected rows or false
	 * @param  string  $query The query strings
	 * @param  string  $type  (optional) Bind parameter type. See also
	 * {@link http://php.net/manual/en/mysqli-stmt.bind-param.php mysqli_stmt::bind_param}.
	 * <code>
	 * i => integer
	 * d => double
	 * s => string
	 * b => blob
	 * </code>
	 * @param  mixed   $param1 (optional) Bind parameter 1
	 * @param  mixed   $param2,... (optional) Bind parameter 2 ..
	 */
	function query () {
		$_argv = func_get_args ();
		$argv = is_array ($_argv[0]) ? $_argv[0] : $_argv;;

		$this->error = null;

		$sql = array_shift ($argv);
		$this->pno = count ($argv) ? $this->get_param_number ($sql) : 0;

		if ( $this->free )
			$this->free_result ();

		/*
		 * For no bind query
		 */
		if ( $this->pno++ == 0 )
			return $this->no_bind_query ($sql);

		/*
		 * For bind query
		 */
		return $this->bind_query ($sql, $argv);
	}
	// }}}

	// {{{ (int) EDB_MYSQLI::lastId (void)
	/**
	 * 마지막 실행한 쿼리에서 자동으로 생성된 ID(auto increment)값을 반환
	 *
	 * @since  2.0.4
	 * @access public
	 * @return integer|false
	 */
	function lastId () {
		return $this->db->insert_id;
	}
	// }}}

	// {{{ (bool) EDB_MYSQLI::seek ($offset)
	/**
	 * Adjusts the result pointer to an arbitrary row in the result
	 *
	 * @access public
	 * @return boolean
	 * @param  integer Must be between zero and the total number of rows minus one
	 */
	function seek ($offset) {
		if ( ! is_object ($this->result) )
			return false;

		try {
			return $this->result->data_seek ($offset);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (object) EDB_MYSQLI::fetch (void)
	/**
	 * Fetch a result row as an associative object
	 *
	 * @access public
	 * @return object The object of fetched a result row or false
	 * @param  boolean (optional) fetch 수행 후 result를 free한다.
	 *                 (기본값: false) EDB >= 2.0.3
	 */
	function fetch ($free = false) {
		try {
			if ( $this->result instanceof mysqli_result )
				$r = $this->fetch_result ();
			else if ( $this->result instanceof mysqli_stmt )
				$r = $this->fetch_stmt ();

			if ( $free )
				$this->free_result ();

			return $r;
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
		return false;
	}
	// }}}

	// {{{ (array) EDB_MYSQLI::fetch_all ($free = true)
	/**
	 * Fetch all result rows as an associative object
	 *
	 * @access public
	 * @return array The fetched result rows
	 * @param  boolean (optional) free result set after fetch.
	 *                 Defaluts is true.
	 */
	function fetch_all ($free = true) {
		try {
			if ( $this->result instanceof mysqli_result ) {
				return $this->fetch_result_all ($free);
			} else if ( $this->result instanceof mysqli_stmt ) {
				return $this->fetch_stmt_all ($free);
			}
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return array ();
		}

		return array ();
	}
	// }}}

	// {{{ (bool) EDB_MYSQLI::free_result (void)
	/**
	 * Frees stored result memory for the given statement handle
	 *
	 * @access public
	 * @return boolean
	 * @param  void
	 */
	function free_result () {
		if ( ! $this->free ) return true;
		$this->free = false;

		try {
			if ( is_object ($this->result) )
				$this->result->free_result ();

			if ( $this->result instanceof mysqli_stmt )
				$this->result->close ();

			unset ($this->field);
			$this->field = array ();
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		return true;
	}
	// }}}

	// {{{ (string) EDB_MYSQLI::field_name ($index)
	/**
	 * Get the name of the specified field in a result
	 *
	 * @access public
	 * @return string|false
	 * @param  integer The field number. This value must be in the
	 *                 range from 0 to number of fields - 1.
	 */
	function field_name ($index) {
		try {
			$r = $this->field_info ($index, 'type');
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		return $r;
	}
	// }}}

	// {{{ (string) EDB_MYSQLI::field_type ($index)
	/**
	 * Get the type of the specified field in a result
	 *
	 * @access public
	 * @return string|false
	 * @param  integer The numerical field offset. The field_offset starts
	 *                 at 0. If field_offset does not exist, return false
	 *                 and an error of level E_WARNING is also issued.
	 * @see http://php.net/manual/en/mysqli.constants.php Predefined Constants
	 */
	function field_type ($index) {
		try {
			$r = $this->field_info ($index, 'type');
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		return $this->file_type_string ($r);
	}
	// }}}

	// {{{ (int) EDB_MYSQLI::num_fields (void)
	/**
	 * Returns the number of columns for the most recent query
	 *
	 * @access public
	 * @return integer An integer representing the number of fields in a result set.
	 * @see http://php.net/manual/en/mysqli.field-count.php mysqli::$field_count
	 */
	function num_fields () {
		try {
			return $this->db->field_count;
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (void) EDB_MYSQLI::trstart (void)
	/**
	 * DB transaction 을 시작한다.
	 *
	 * @access public
	 * @return void
	 */
	function trstart () {
		//$this->db->query ('BEGIN');
		$this->db->query ('START TRANSACTION WITH CONSISTENT SNAPSHOT');
	}
	// }}}

	// {{{ (void) EDB_MYSQLI::trend ($v)
	/**
	 * DB transaction 을 종료한다.
	 *
	 * @access public
	 * @return void
	 * @param bool false일경우 rollback을 수행한다.
	 */
	function trend ($v = true) {
		$sql = ($v === false) ? 'ROLLBACK' : 'COMMIT';
		$this->db->query ($sql);
	}
	// }}}

	// {{{ (void) EDB_MYSQLI::close (void)
	/**
	 * Close the db handle
	 *
	 * @access public
	 * @return void
	 * @param  void
	 */
	function close () {
		if ( is_object ($this->db) )
			$this->db->close ();
	}
	// }}}

	/*
	 * Priavte functions
	 */
	// {{{ private (int) EDB_MYSQLI::no_bind_query ($sql)
	/** 
	 * Performs a query on the database
	 *
	 * @access private
	 * @return integer The number of affected rows or false
	 * @param  string  The query strings
	 */
	private function no_bind_query ($sql) {
		try {
			$this->result = $this->db->query ($sql);
			if ( $this->db->errno ) {
				$this->free = false;
				throw new myException ($this->db->error, E_USER_WARNING);
				return false;
			}
		} catch ( Exception $e ) {
			$this->free = false;
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		$this->switch_freemark ();

		if ( preg_match ('/^(update|insert|delete|replace)/i', trim ($sql)) ) {
			/* Insert or update, or delete query */
			return $this->db->affected_rows;
		} else if ( preg_match ('/create|drop/i', trim ($sql)) ) {
			return 1;
		}

		return $this->result->num_rows;
	}
	// }}}

	// {{{ private (int) EDB_MYSQLI::bind_query ($sql, $parameters)
	/** 
	 * Performs a bind query on the database
	 *
	 * @access private
	 * @return integer The number of affected rows or false
	 * @param  string  The query strings
	 * @param  array   (optional) Bind parameter type
	 */
	private function bind_query ($sql, $params) {
		if ( isset ($param) )
			unset ($param);

		$this->result = $this->db->prepare ($sql);

		if ( $this->db->errno || ! is_object ($this->result) ) {
			throw new myException ($this->db->error, E_USER_WARNING);
			return false;
		}

		$this->switch_freemark ();

		if ( $this->pno != count ($params) || $this->check_param ($params) === false ) {
			$this->free_result ();
			throw new myException (
				'Number of elements in query doesn\'t match number of bind variables',
				E_USER_WARNING
			);
			return false;
		}

		$blobs = array ();
		for ( $i=0; $i<count ($params); $i++ ) {
			$param[$i] = &$params[$i];
			if ( $i == 0 )
				continue;

			switch ($params[0][$i-1]) {
				case 'c' :
					// don't support clob type mysqli_bind_params
					$params[0][$i-1] = 'b';
				case 'b' :
					$blobs[$i-1] = is_object ($params[$i]) ? $params[$i]->data : $params[$i];
					$params[$i]  = null;
					break;
			}
		}

		try {
			$r = call_user_func_array (array ($this->result, 'bind_param'), $param);
			if ( $r === false )
				throw new myException ($this->result->error, E_USER_ERROR);

			# for blob data
			foreach ( $blobs as $key => $val ) {
				if ( $this->result->send_long_data ($key, $val) === false )
					throw new myException ($this->result->error, E_USER_ERROR);
			}

			if ( $this->result->execute () === false )
				throw new myException ($this->result->error, E_USER_ERROR);
		} catch ( Exception $e ) {
			$this->free_result ();
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		$this->bind_result ($sql);

		return $this->result->affected_rows;
	}
	// }}}

	// {{{ private (void) EDB_MYSQLI::bind_result (void)
	/**
	 * Binds variables to a prepared statement for result storage
	 *
	 * @access private
	 * @return void
	 * @param  string Query string
	 */
	private function bind_result ($sql) {
		if ( preg_match ('/^(update|insert|delete)/i', trim ($sql)) )
			return;

		unset ($this->field);
		$this->field = array ();

		try {
			$this->result->store_result ();
			$var = array ();
			$meta = $this->result->result_metadata ();

			while ( $fields = $meta->fetch_field () )
				$var[] = &$this->field[$fields->name];

			$meta->free ();
			call_user_func_array(array($this->result, 'bind_result'), $var);
		} catch ( Exception $e ) {
			$this->free_result ();
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ private (object) EDB_MYSQLI::fetch_result (void)
	/**
	 * Fetch a result row as an associative object
	 *
	 * @access private 
	 * @return object The object of fetched a result row or false
	 * @param  void
	 */
	private function fetch_result () {
		if ( $this->result instanceof mysqli_result )
			$r  = $this->result->fetch_object ();
		return is_object ($r) ? $r : false;
	}
	// }}}

	// {{{ private (object) EDB_MYSQLI::fetch_stmt (void)
	/**
	 * Fetch a result row as an associative object
	 *
	 * @access private
	 * @return object The object of fetched a result row or false
	 * @param  void
	 */
	private function fetch_stmt () {
		if ( ! $this->result instanceof mysqli_stmt )
			return false;

		if ( $fetch_check = $this->result->fetch () ) {
			$retval = new stdClass;
			foreach ( $this->field as $key => $val )
				$retval->$key = $val;
		} else
			$retval = false;

		return $retval;
	}
	// }}}

	// {{{ private (array) EDB_MYSQLI::fetch_result_all ($free = true)
	/**
	 * Fetch all result rows as an associative object
	 *
	 * @access private 
	 * @return array The fetched result rows
	 * @param  boolean (optional) free result set after fetch.
	 *                 Defaluts is true.
	 */
	private function fetch_result_all ($free = true) {
		if ( ! $this->result instanceof mysqli_result )
			return array ();

		//$this->field = array ();
		$rows = array ();

		while ( ($row = $this->result->fetch_object ()) !== null )
			$rows[] = $row;

		if ( $free )
			$this->free_result ();
		return $rows;
	}
	// }}}

	// {{{ private (array) EDB_MYSQLI::fetch_stmt_all ($free = true)
	/**
	 * Fetch all result rows as an associative object
	 *
	 * @access public
	 * @return array The fetched result rows
	 * @param  boolean (optional) free result set after fetch.
	 *                 Defaluts is true.
	 */
	private function fetch_stmt_all ($free = true) {
		if ( ! $this->result instanceof mysqli_stmt )
			return array ();

		$r = array ();

		$i = 0;
		while ( $this->result->fetch () ) {
			$r[$i] = new stdClass;
			foreach ( $this->field as $key => $val )
				$r[$i]->$key = $val;
			$i++;
		}

		if ( $free)
			$this->free_result ();

		return $r;
	}
	// }}}

	// {{{ private (mixed) EDB_MYSQLI::field_info ($index)
	/**
	 * Get the type of the specified field in a result
	 *
	 * @access private
	 * @return mixed|false
	 * @param  integer The numerical field offset. The field_offset starts
	 *                 at 0. If field_offset does not exist, return false
	 *                 and an error of level E_WARNING is also issued.
	 */
	private function field_info ($index, $type = 'name') {
		try {
			$r = false;

			if ( $this->result instanceof mysqli_result ) {
				if ( ($o = $this->result->fetch_field_direct ($index)) === false )
					return false;
				$r = $o->$type;
			} else if ( $this->result instanceof mysqli_stmt ) {
				if ( ($result = $this->result->result_metadata ()) === false )
					return false;

				for ( $i=0; $i<=$index; $i++ )
					$o = $result->fetch_field ();
				$r = $o->$type;
				$result->free_result ();
			}

			return $r;
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ private (string) file_type_string ($type) {
	/**
	 * change mysqli filed type to strings
	 *
	 * @access private
	 * @return string
	 * @param  integer mysqli field type
	 */
	private function file_type_string ($type) {
		switch ($type) {
			case MYSQLI_TYPE_DECIMAL :
				return 'DECIMAL';
			//Precision math DECIMAL or NUMERIC field (MySQL 5.0.3 and up)
			case MYSQLI_TYPE_NEWDECIMAL :
				return 'NUMERIC';
			case MYSQLI_TYPE_BIT :
				return 'BIT (MySQL 5.0.3 and up)';
			case MYSQLI_TYPE_TINY :
				return 'TINYINT';
			case MYSQLI_TYPE_SHORT :
				return 'SMALLINT';
			case MYSQLI_TYPE_LONG :
				return 'INT';
			case MYSQLI_TYPE_FLOAT :
				return 'FLOAT';
			case MYSQLI_TYPE_DOUBLE :
				return 'DOUBLE';
			case MYSQLI_TYPE_NULL :
				return 'DEFAULT NULL';
			case MYSQLI_TYPE_TIMESTAMP :
				return 'TIMESTAMP';
			case MYSQLI_TYPE_LONGLONG :
				return 'BIGINT';
			case MYSQLI_TYPE_INT24 :
				return 'MEDIUMINT';
			case MYSQLI_TYPE_DATE :
				return 'DATE';
			case MYSQLI_TYPE_TIME :
				return 'TIME';
			case MYSQLI_TYPE_DATETIME :
				return 'DATETIME';
			case MYSQLI_TYPE_YEAR :
				return 'YEAR';
			case MYSQLI_TYPE_NEWDATE :
				return 'DATE';
			case MYSQLI_TYPE_INTERVAL :
				return 'INTERVAL';
			case MYSQLI_TYPE_ENUM :
				return 'ENUM';
			case MYSQLI_TYPE_SET :
				return 'SET';
			case MYSQLI_TYPE_TINY_BLOB :
				return 'TINYBLOB';
			case MYSQLI_TYPE_MEDIUM_BLOB :
				return 'MEDIUMBLOB';
			case MYSQLI_TYPE_LONG_BLOB :
				return 'LONGBLOB';
			case MYSQLI_TYPE_BLOB :
				return 'BLOB';
			case MYSQLI_TYPE_VAR_STRING :
				return 'VARCHAR';
			case MYSQLI_TYPE_STRING :
				return 'STRING';
			case MYSQLI_TYPE_CHAR :
				return 'CHAR';
			case MYSQLI_TYPE_GEOMETRY :
				return 'GEOMETRY';
			default:
				return 'UNKNOWN';
		}
	}
	// }}}

	function __destruct () {
		try {
			$this->free_result ();
			$this->close ();
		} catch ( Exception $e ) { }
	}
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim: set filetype=php noet sw=4 ts=4 fdm=marker:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
?>
