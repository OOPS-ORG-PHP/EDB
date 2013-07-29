<?php
/**
 * Project: EDB_MYSQL :: MySQL abstraction layer
 * File:    EDB/EDB_MYSQL.php
 *
 * The EDB_MYSQL class is mysql abstraction layer that used internally
 * on EDB class.
 *
 * @category    Database
 * @package     EDB
 * @subpackage  EDB_ABSTRACT
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 2012, JoungKyun.Kim
 * @license     BSD License
 * @version     $Id: EDB_MYSQL.php 30 2012-09-26 18:02:58Z oops $
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
Class EDB_MYSQL extends EDB_Common {
	// {{{ properties
	/**#@+
	 * @access private
	 */
	/**
	 * db handler of EDB_MYSQL class
	 * @var    object
	 */
	private $db;
	/**
	 * The number of query parameter
	 * @var    integer
	 */
	private $pno = 0;
	/**#@-*/
	// }}}

	// {{{ (object) EDB_MYSQL::__construct ($host, $user, $pass, $db)
	/** 
	 * Instantiates an EDB_MYSQL object and opens an mysql database
	 *
	 * For examples:
	 * <code>
	 * $db = new EDB_MYSQL ('mysql://localhost', 'user', 'host', 'database');
	 * $db = new EDB_MYSQL ('mysql://localhost:3306', 'user', 'host', 'database');
	 * $db = new EDB_MYSQL ('mysql://localhost:/var/run/mysqld/mysql.sock', 'user', 'host', 'database');
	 * </code>
	 *
	 * If you add prefix 'p~' before host, you can connect with persistent
	 * connection.
	 *
	 * For Examples:
	 * <code>
	 * $db = new EDB_MYSQL ('mysql://p~localhost', 'user', 'host', 'database');
	 * </code>
	 *
	 * @access public
	 * @return EDB_MYSQL
	 * @param  string  $hostname mysql host
	 * @param  string  $user     mysql user
	 * @param  string  $password mysql password
	 * @param  string  $database mysql database
	 */
	function __construct () {
		$_argv = func_get_args ();
		$argv = is_array ($_argv[0]) ? $_argv[0] : $_argv;;

		if ( ! extension_loaded ('mysql') )
			throw new myException ('MySQL extension is not loaded on PHP!', E_USER_ERROR);

		$o = (object) array (
			'host' => preg_replace ('!^mysql://!', '', $argv[0]),
			'user' => $argv[1],
			'pass' => $argv[2],
			'db'   => $argv[3]
		);

		if ( preg_match ('/^([^:]+):(.+)/', $o->host, $matches) ) {
			if ( ! is_numeric ($matches[2]) )
				$o->host = ':' . $matches[2];
		} else
			$o->host .= ':3306';

		if ( preg_match ('/^p~/', $o->host) ) {
			$func = 'mysql_pconnect';
			$o->host = preg_replace ('/^p~/', '', $o->host);
		} else
			$func = 'mysql_connect';

		try {
			$this->db = $func ($o->host, $o->user, $o->pass);
			mysql_select_db ($o->db, $this->db);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
		}
	}
	// }}}

	// {{{ (string) EDB_MYSQL::get_charset (void)
	/** 
	 * Get character set of current database
	 *
	 * MySQL extension don't support this function
	 *
	 * @access public
	 * @return string Current character set name on DB
	 */
	function get_charset () {
		$r = $this->query ('SHOW variables WHERE Variable_name = ?', 's', 'character_set_client');
		if ( $r != 1 )
			return 'Unsupport';

		$row = $this->fetch (true);
		return $row->Value;
	}
	// }}}

	// {{{ (bool) EDB_MYSQL::set_charset ($charset)
	/** 
	 * Set character set of current database
	 *
	 * @access public
	 * @return bool    The name of character set that is supported on database
	 * @param  string  name of character set that supported from database
	 */
	function set_charset ($char) {
		try {
			$r = false;

			if ( is_resource ($this->db) ) {
				if ( ($r = mysql_set_charset ($char, $this->db)) == false )
					$this->error = mysql_error ($this->db);
			}

			return $r;
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (string) EDB_MYSQL::escape ($string)
	/** 
	 * Escape special characters in a string for use in an SQL statement
	 *
	 * @access public
	 * @return string
	 * @param  string  The string that is to be escaped.
	 */
	function escape ($string) {
		return mysql_real_escape_string ($string, $this->db);
	}
	// }}}

	// {{{ (int) EDB_MYSQL::query ($query, $param_type, $param1, $param2 ...)
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

	// {{{ (boo) EDB_MYSQL::seek ($offset)
	/**
	 * Adjusts the result pointer to an arbitrary row in the result
	 *
	 * @access public
	 * @return boolean
	 * @param  integer Must be between zero and the total number of rows minus one
	 */
	function seek ($offset) {
		if ( ! is_resource ($this->result) )
			return false;

		try {
			return mysql_data_seek ($this->result, $offset);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (object) EDB_MYSQL::fetch (void)
	/**
	 * Fetch a result row as an associative object
	 *
	 * @access public
	 * @return object The object of fetched a result row or false
	 * @param  boolean (optional) fetch 수행 후 result를 free한다.
	 *                 (기본값: false) EDB >= 2.0.3
	 */
	function fetch ($free = false) {
		if ( ! is_resource ($this->result) )
			return false;

		try {
			$r = mysql_fetch_object ($this->result);
			if ( $free )
				$this->free_result ();
			return $r;
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (array) EDB_MYSQL::fetch_all ($free = true)
	/**
	 * Fetch all result rows as an associative object
	 *
	 * @access public
	 * @return array The fetched result rows
	 * @param  boolean (optional) free result set after fetch.
	 *                 Defaluts is true.
	 */
	function fetch_all ($true = true) {
		$row = array ();

		while ( ($r = $this->fetch ()) !== false )
			$row[] = $r;

		if ( $true )
			$this->free_result ();

		return $row;
	}
	// }}}

	// {{{ (bool) EDB_MYSQL::free_result (void)
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

		if ( ! is_resource ($this->result) )
			return true;

		try {
			return mysql_free_result ($this->result);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (string) EDB_MYSQL::field_name ($index)
	/**
	 * Get the name of the specified field in a result
	 *
	 * @access public
	 * @return string|false
	 * @param  integer The numerical field offset. The field_offset starts
	 *                 at 0. If field_offset does not exist, return false
	 *                 and an error of level E_WARNING is also issued.
	 * @see http://php.net/manual/en/function.mysql-field-name.php mysql_field_name()
	 */
	function field_name ($index) {
		try {
			if ( ! is_resource ($this->result) )
				return false;

			return mysql_field_name ($this->result, $index);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (string) EDB_MYSQL::field_type ($index)
	/**
	 * Get the type of the specified field in a result
	 *
	 * @access public
	 * @return string|false
	 * @param  integer The numerical field offset. The field_offset starts
	 *                 at 0. If field_offset does not exist, return false
	 *                 and an error of level E_WARNING is also issued.
	 * @see http://php.net/manual/en/function.mysql-field-type.php mysql_field_type()
	 */
	function field_type ($index) {
		try {
			if ( ! is_resource ($this->result) )
				return false;

			return mysql_field_type ($this->result, $index);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (int) EDB_MYSQL::num_fields (void)
	/**
	 * Get number of fields in result
	 *
	 * @access public
	 * @return integer|false
	 * @see http://php.net/manual/en/function.mysql-num-fields.php mysql_num_fields()
	 */
	function num_fields () {
		try {
			if ( ! is_resource ($this->result) )
				return false;

			return mysql_num_fields ($this->result);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (void) EDB_MYSQL::close (void)
	/**
	 * Close the db handle
	 *
	 * @access public
	 * @return void
	 * @param  void
	 */
	function close () {
		if ( is_resource ($this->db) )
			mysql_close ($this->db);
	}
	// }}}

	/*
	 * Priavte functions
	 */
	// {{{ private (int) EDB_MYSQL::no_bind_query ($sql)
	/** 
	 * Performs a query on the database
	 *
	 * @access private
	 * @return integer The number of affected rows or false
	 * @param  string  The query strings
	 */
	private function no_bind_query ($sql) {
		try {
			$this->result = mysql_query ($sql, $this->db);
			if ( mysql_errno ($this->db) ) {
				$this->free = false;
				throw new myException (mysql_error ($this->db), E_USER_WARNING);
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
			return mysql_affected_rows ($this->db);
		} else if ( preg_match ('/create|drop/i', trim ($sql)) ) {
			return 1;
		}

		return mysql_num_rows ($this->result);
	}
	// }}}

	// {{{ private (int) EDB_MYSQL::bind_query ($sql, $parameters)
	/** 
	 * Performs a bind query on the database
	 *
	 * @access private
	 * @return integer The number of affected rows or false
	 * @param  string  The query strings
	 * @param  array   (optional) Bind parameter type
	 */
	private function bind_query ($sql, $params) {
		if ( $this->pno != count ($params) || $this->check_param ($params) === false ) {
			throw new myException (
				'Number of elements in query doesn\'t match number of bind variables',
				E_USER_WARNING
			);
			return false;
		}

		$parano = strlen ($params[0]);
		for ( $i=0, $j=1; $i<$parano; $i++, $j++ ) {
			switch ($params[0][$i]) {
				case 'c' :
				case 'b' :
					if ( is_object ($params[$j]) )
						$params[$j] = $this->escape ($params[$j]->data);
					$params[$j] = $this->escape ($params[$j]);
					break;
			}
		}

		$query = $this->bind_param ($sql, $params);
		return $this->no_bind_query ($query);
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
