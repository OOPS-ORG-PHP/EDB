<?php
/**
 * Project: EDB_MSSQL :: MSSQL abstraction layer
 * File:    EDB/EDB_MSSQL.php
 *
 * The EDB_MSSQL class is MSSQL abstraction layer that used internally
 * on EDB class.
 *
 * @category    Database
 * @package     EDB
 * @subpackage  EDB_ABSTRACT
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 2014, JoungKyun.Kim
 * @license     BSD License
 * @version     $Id$
 * @link        http://pear.oops.org/package/EDB
 * @filesource
 */

/**
 * MSSQL engine for EDB API
 *
 * This class support abstracttion DB layer for MSSQL Engine
 *
 * @package     EDB
 */
Class EDB_MSSQL extends EDB_Common {
	// {{{ properties
	/**#@+
	 * @access private
	 */
	/**
	 * db handler of EDB_MSSQL class
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

	// {{{ (object) EDB_MSSQL::__construct ($host, $user, $pass, $db)
	/** 
	 * Instantiates an EDB_MSSQL object and opens an MSSQL database
	 *
	 * For examples:
	 * <code>
	 * $db = new EDB_MSSQL ('mssql://localhost', 'user', 'host', 'database');
	 * $db = new EDB_MSSQL ('mssql://localhost:33000', 'user', 'host', 'database');
	 * $db = new EDB_MSSQL ('mssql://localhost:33000?autocommit=false', 'user', 'host', 'database');
	 * </code>
	 *
	 * If you add prefix 'p~' before host, you can connect with persistent
	 * connection.
	 *
	 * For Examples:
	 * <code>
	 * $db = new EDB_MSSQL ('mssql://p~localhost', 'user', 'host', 'database');
	 * </code>
	 *
	 * @access public
	 * @return EDB_MSSQL
	 * @param  string  $hostname MSSQL host
	 * @param  string  $user     MSSQL user
	 * @param  string  $password MSSQL password
	 * @param  string  $database MSSQL database
	 */
	function __construct () {
		$_argv = func_get_args ();
		$argv = is_array ($_argv[0]) ? $_argv[0] : $_argv;;

		$iniset = function_exists ('___ini_set') ? '___ini_set' : 'ini_set';
		$iniset ('magic_quotes_sybase', 'Off');

		if ( ! extension_loaded ('mssql') )
			throw new myException ('MSSQL extension is not loaded on PHP!', E_USER_ERROR);

		$o = (object) array (
			'host' => preg_replace ('!^mssql://!', '', $argv[0]),
			'user' => $argv[1],
			'pass' => $argv[2],
			'db'   => $argv[3]
		);

		if ( preg_match ('/^p~/', $o->host) ) {
			$func = 'mssql_pconnect';
			$o->host = preg_replace ('/^p~/', '', $o->host);
		} else
			$func = 'mssql_connect';

		try {
			$this->db = $func ($o->host, $o->user, $o->pass);
			mssql_select_db ($o->db, $this->db);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
		}
	}
	// }}}

	// {{{ (string) EDB_MSSQL::get_charset (void)
	/** 
	 * Get character set of current database
	 *
	 * MSSQL extension don't support this function
	 *
	 * @access public
	 * @return string Current character set name on DB
	 */
	function get_charset () {
		return 'Unsupport';
	}
	// }}}

	// {{{ (bool) EDB_MSSQL::set_charset ($charset)
	/** 
	 * Set character set of current database
	 *
	 * This method is always returned true because MSSQL don't support
	 * charset settings.
	 *
	 * @access public
	 * @return bool    always return true
	 * @param  string  name of character set that supported from database
	 */
	function set_charset ($char) {
		return true;
	}
	// }}}

	// {{{ (string) EDB_MSSQL::escape ($string)
	/** 
	 * Escape special characters in a string for use in an SQL statement
	 *
	 * @access public
	 * @return string
	 * @param  string  The string that is to be escaped.
	 */
	function escape ($string) {
		return preg_replace ('/[\']/', '\'\'', $string);
	}
	// }}}

	// {{{ (int) EDB_MSSQL::query ($query, $param_type, $param1, $param2 ...)
	/** 
	 * Performs a query on the database
	 *
	 * @access public
	 * @return integer The number of affected rows or false
	 * @param  string  $query The query strings
	 * @param  string  $type  (optional) Bind parameter type. See also
	 * <code>
	 * i => integer
	 * d => double
	 * s => string
	 * b => blob
	 * c => clob
	 * n => null
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

		// store query in log variable
		$this->queryLog[] = $sql;

		if ( $this->pno++ == 0 ) // no bind query
			$this->no_bind_query ($sql);
		else // bind query
			$this->bind_query ($sql, $argv);

		if ( preg_match ('/^(update|insert|delete|replace)/i', trim ($sql)) ) {
			/* Insert or update, or delete query */
			return mssql_rows_affected ($this->db);
		} else if ( preg_match ('/create|drop/i', trim ($sql)) ) {
			return 1;
		}

		return mssql_num_rows ($this->result);
	}
	// }}}

	// {{{ (string) EDB_MSSQL::lastId (void)
	/**
	 * 가장 마지막 입력 row의 OID를 반환한다.
	 *
	 * @since  2.0.4
	 * @access public
	 * @return string|false
	 */
	function lastId () {
		$id = 0;
		if ( ($r = @mssql_query ('SELECT @@identity AS id')) === false )
			return 0;

		if ( $row = mssql_fetch_object ($r) )
			$id = $row->id;

		return $id;
	}
	// }}}

	// {{{ (bool) EDB_MSSQL::seek ($offset)
	/**
	 * Move the cursor in the result
	 *
	 * @access public
	 * @return boolean
	 * @param  Number of units you want to move the cursor.
	 */
	function seek ($offset) {
		if ( ! is_resource ($this->result) )
			return false;

		try {
			return mssql_data_seek ($this->result, $offset);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (object) EDB_MSSQL::fetch (void)
	/**
	 * Fetch a result row as an associative object
	 *
	 * @access public
	 * @return object|false The object of fetched a result row or false
	 * @param  boolean (optional) 수행 후 result를 free 한다. 기본값: false
	 *                 EDB >= 2.0.3
	 */
	function fetch ($free = false) {
		try {
			$r = mssql_fetch_object ($this->result);
			if ( $free )
				$this->free_result ();
			return $r;
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (array) EDB_MSSQL::fetch_all ($free = true)
	/**
	 * Fetch all result rows as an associative object
	 *
	 * @access public
	 * @return array The fetched object result rows
	 * @param  boolean (optional) free result set after fetch.
	 *                 Defaluts is true.
	 */
	function fetch_all ($free = true) {
		$row = array ();

		while ( ($r = $this->fetch ()) !== false )
			$row[] = $r;

		if ( $free )
			$this->free_result ();

		return $row;
	}
	// }}}

	// {{{ (bool) EDB_MSSQL::free_result (void)
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
			if ( ! is_resource ($this->result) )
				return true;

			return mssql_free_result ($this->result);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (string) EDB_MSSQL::field_name ($index)
	/**
	 * Return the name of the specified field index
	 *
	 * @access public
	 * @return string|false
	 * @param  integer The numerical field offset. The field_offset starts
	 *                 at 0. If field_offset does not exist, return false
	 *                 and an error of level E_WARNING is also issued.
	 */
	function field_name ($index) {
		try {
			if ( ! is_resource ($this->result) )
				return false;
			return mssql_field_name ($this->result, $index);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (string) EDB_MSSQL::field_type ($index)
	/**
	 * Get the type of the specified field in a result
	 *
	 * @access public
	 * @return string|false
	 * @param  integer The numerical field offset. The field_offset starts
	 *                 at 0. If field_offset does not exist, return false
	 *                 and an error of level E_WARNING is also issued.
	 */
	function field_type ($index) {
		try {
			if ( ! is_resource ($this->result) )
				return false;

			return mssql_field_type ($this->result, $index);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (int) EDB_MSSQL::num_fields (void)
	/**
	 * Return the number of columns in the result set
	 *
	 * @access public
	 * @return integer|false return -1 if SQL sentence is not SELECT.
	 */
	function num_fields () {
		try {
			if ( ! is_resource ($this->result) )
				return false;
			return mssql_num_fields ($this->result);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (void) EDB_MSSQL::trstart (void)
	/**
	 * DB transaction 을 시작한다.
	 *
	 * @access public
	 * @return void
	 */
	function trstart () {
		$this->db->query ('BEGIN TRANSACTION');
	}
	// }}}

	// {{{ (void) EDB_MSSQL::trend ($v)
	/**
	 * DB transaction 을 종료한다.
	 *
	 * @access public
	 * @return void
	 * @param bool false일경우 rollback을 수행한다.
	 */
	function trend ($v = true) {
		$sql = ($v === false) ? 'ROLLBACK' : 'COMMIT';
		$this->db->query ($sql . ' TRANSACTION');
	}
	// }}}

	// {{{ (void) EDB_MSSQL::close (void)
	/**
	 * Close the db handle
	 *
	 * @access public
	 * @return void
	 * @param  void
	 */
	function close () {
		if ( is_resource ($this->db) )
			mssql_close ($this->db);
	}
	// }}}

	/*
	 * Priavte functions
	 */
	// {{{ private (int) EDB_MSSQL::no_bind_query ($sql)
	/** 
	 * Performs a query on the database
	 *
	 * @access private
	 * @return integer The number of affected rows or false
	 * @param  string  The query strings
	 */
	private function no_bind_query ($sql) {
		try {
			if ( ($this->result = mssql_query ($sql, $this->db)) === false ) {
				$this->free = false;
				throw new myException (mssql_get_last_message (), E_USER_WARNING);
				return false;
			}
		} catch ( Exception $e ) {
			$this->free = false;
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		$this->switch_freemark ();
	}
	// }}}

	// {{{ private (int) EDB_MSSQL::bind_query ($sql, $parameters)
	/** 
	 * Performs a bind query on the database
	 *
	 * mssql_bind api is supported stored procedure, so EDB
	 * package is supported by self bind method of EDB
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
				case 'b' :
					if ( is_object ($params[$j]) )
						$params[$j] = $params[$j]->data;
					$params[$j] = 'unquote:' . $this->escape_bin ($params[$j]);
					break;
				case 'c' :
					$params[0][$i] = 's';
					break;
			}
		}

		$query = $this->bind_param ($sql, $params);
		return $this->no_bind_query ($query);
	}
	// }}}

	// {{{ priavet (string) EDB_MSSQL::binary_escape ($string)
	/** 
	 * Escape special characters in a string for use in an SQL statement
	 *
	 * @access public
	 * @return string
	 * @param  string  The string that is to be escaped.
	 */
	private function escape_bin ($bin) {
		return '0x' . bin2hex ($bin);
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
