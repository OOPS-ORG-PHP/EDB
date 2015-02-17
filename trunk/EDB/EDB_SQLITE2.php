<?php
/**
 * Project: EDB_SQLITE2 :: SQLITE2 abstraction layer
 * File:    EDB/EDB_SQLITE2.php
 *
 * The EDB_SQLITE2 class is sqlite2 abstraction layer that used internally
 * on EDB class.
 *
 * @category    Database
 * @package     EDB
 * @subpackage  EDB_ABSTRACT
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 2015 JoungKyun.Kim
 * @license     BSD License
 * @version     $Id$
 * @link        http://pear.oops.org/package/EDB
 * @filesource
 */

/**
 * SQLite2 engine for EDB API
 *
 * This class support abstracttion DB layer for SQLite3 Engine
 *
 * @package     EDB
 */
Class EDB_SQLITE2 extends EDB_Common {
	// {{{ properties
	/**#@+
	 * @access private
	 */
	/**
	 * db handler of EDB_SQLITE2 class
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

	// {{{ (object) EDB_SQLITE2::__construct ($path[, $mode = 0666])
	/** 
	 * Instantiates an EDB_SQLITE2 object and opens an SQLite 3 database
	 *
	 * For examples:
	 * <code>
	 * $db = new EDB_SQLITE2 ('sqlite2:///path/file.db');
	 * $db = new EDB_SQLITE2 ('sqlite2:///path/file.db', 0666)
	 * </code>
	 *
	 * If you add prefix 'p~' before host, you can connect with persistent
	 * connection.
	 *
	 * For Examples:
	 * <code>
	 * $db = new EDB_SQLTE2 ('sqlite2://p~/path/file.db');
	 * </code>
	 *
	 * @access public
	 * @return EDB_SQLITE2
	 * @param  string  $path  sqlite2 database file
	 * @param  integer $mode  The mode of the file. Intended to be used to open
	 *                        the database in read-only mode. Presently, this
	 *                        parameter is ignored by the sqlite library. The
	 *                        default value for mode is the octal value 0666
	 *                        and this is the recommended value.
	 */
	function __construct () {
		if ( ! extension_loaded ('sqlite') )
			throw new myException ('sqlite extension is not loaded on PHP!', E_USER_ERROR);

		try {
			$_argv = func_get_args ();
			$argv = is_array ($_argv[0]) ? $_argv[0] : $_argv;;

			$o = (object) array (
				'path' => preg_replace ('!^sqlite2://!', '', $argv[0]),
				'mode' => $argv[2],
			);

			if ( ! $o->flag )
				$o->mode = 0666;

			// for persistent connection
			if ( preg_match ('!^p~!', $o->path) ) {
				$o->path = preg_replace ('!^p~!', '', $o->path);
				$func = 'sqlite_popen';
			} else
				$func = 'sqlite_open';

			$this->db = $func ($o->path, $o->mode, $error);
		} catch ( Exception $e ) {
			if ( $error )
				throw new myException ($error, $e->getCode(), $e);
			else
				throw new myException ($e->getMessage (), $e->getCode(), $e);
		}
	}
	// }}}

	// {{{ (string) EDB_SQLITE2::get_charset (void)
	/** 
	 * Get character set of current database
	 *
	 * This method is not allow on SQLite2 Engine
	 *
	 * @access public
	 * @return string Current character set name
	 */
	function get_charset () {
		if ( function_exists ('sqlite_libencoding') )
			return sqlite_libencoding ();
		else
			throw new myException ('Unsupported method on SQLITE2 engine', E_USER_ERROR);
	}
	// }}}

	// {{{ (bool) EDB_SQLITE2::set_charset ($charset)
	/** 
	 * Set character set of current database
	 *
	 * This method is not allow on SQLite2 Engine, and always
	 * returns true
	 *
	 * @access public
	 * @return bool   always returns true
	 * @param  string $char name of character set that supported from database
	 */
	function set_charset () {
		return true;
	}
	// }}}

	// {{{ (string) EDB_SQLITE2::escape ($string)
	/** 
	 * Escape special characters in a string for use in an SQL statement
	 *
	 * @access public
	 * @return string
	 * @param  string  The string that is to be escaped.
	 */
	function escape ($string) {
		return sqlite_escape_string ($string);
	}
	// }}}

	// {{{ (int) EDB_SQLITE2::query ($query, $param_type, $param1, $param2 ...)
	/** 
	 * Performs a query on the database
	 *
	 * Executes an SQL query, returning number of affected rows
	 *
	 * @access public
	 * @return integer The number of affected rows or false. If is not delete/insert/update 
	 *                 query, always returns 0.
	 * @param  string $query  The query strings
	 * @param  string $type   (optional) Bind parameter type. See also
	 * {@link http://www.php.net/manual/en/sqlite3stmt.bindparam.php SQLite3Stmt::bindparam()}.
	 * <code>
	 * i => integer SQLITE2_INTEGER
	 * d => double  SQLITE2_FLOAT
	 * s => string  SQLITE2_TEXT
	 * b => blob    SQLITE2_BLOB
	 * n => null    SQLITE2_NULL
	 * </code>
	 * @param  mixed  $param1 (optional) Bind parameter 1
	 * @param  mixed  $param2,... (optional) Bind parameter 2 ..
	 */
	function query () {
		$_argv = func_get_args ();
		$argv = is_array ($_argv[0]) ? $_argv[0] : $_argv;;

		try {
			$sql = array_shift ($argv);
			$this->pno = count ($argv) ? $this->get_param_number ($sql) : 0;

			if ( $this->free )
				$this->free_result ();

			// store query in log variable
			$this->queryLog[] = $sql;

			/*
			 * For no bind query
			 */
			if ( $this->pno++ == 0 )
				return $this->no_bind_query ($sql);

			/*
			 * For bind query
			 */
			return $this->bind_query ($sql, $argv);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (int) EDB_SQLITE2::lastId (void)
	/**
	 * 가장 마지막 입력 row ID를 반환한다.
	 *
	 * @since  2.0.4
	 * @access public
	 * @return int|false
	 */
	function lastId () {
		return sqlite_last_insert_rowid ($this->db);
	}
	// }}}

	// {{{ (bool) EDB_SQLITE2::seek ($offset)
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
			return sqlite_seek ($this->result, $offset);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (object) EDB_SQLITE2::fetch (void)
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
			$r = sqlite_fetch_object ($this->result);
			if ( $free )
				$this->free_result ();
			return $r;
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (array) EDB_SQLITE2::fetch_all ($free = true)
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
			$rows = sqlite_fetch_all ($this->result, SQLITE_ASSOC);
			if ( $free )
				$this->free_result ();

			return $rows;
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return array ();
		}
	}
	// }}}

	// {{{ (bool) EDB_SQLITE2::free_result (void)
	/**
	 * Frees stored result memory for the given statement handle
	 *
	 * @access public
	 * @return boolean always returns true
	 */
	function free_result () {
		if ( ! $this->free ) return true;
		$this->free = false;

		if ( isset ($this->result) )
			unset ($this->result);

		$this->result = null;

		return true;
	}
	// }}}

	// {{{ (string) EDB_SQLITE2::field_name ($index)
	/**
	 * Get the name of the specified field in a result
	 *
	 * Given the ordinal column number, field_index, sqlite_field_name()
	 * returns the name of that field in the result set result.
	 *
	 * @access public
	 * @return string|false
	 * @param  integer The numerical field offset. The index starts at 0.
	 * @see http://php.net/manual/en/function.sqlite-field-name.php sqlite_field_name()
	 */
	function field_name ($index) {
		try {
			if ( ! is_resource ($this->result) )
				return false;
			return sqlite_field_name ($this->result, $index);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (string) EDB_SQLITE2::field_type ($index, $table)
	/**
	 * Get the type of the specified field in a result
	 *
	 * @access public
	 * @return string|false
	 * @param  integer The numerical field offset. The index starts at 0.
	 * @param  string  name of table
	 * @see http://php.net/manual/en/function.sqlite-fetch-column-types.php sqlite_fetch_column_types()
	 */
	function field_type ($index, $table) {
		try {
			if ( ($r = sqlite_fetch_column_types ($table, $this->db, SQLITE_NUM)) === false )
				return false;

			return $r[$index];
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (int) EDB_SQLITE2::num_fields (void)
	/**
	 * Returns the number of fields in the result set.
	 *
	 * @access public
	 * @return integer|false
	 */
	function num_fields () {
		try {
			if ( ! is_resource ($this->result) )
				return false;

			return sqlite_num_fields ($this->result);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (void) EDB_SQLITE2::trstart (void)
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

	// {{{ (void) EDB_SQLITE2::trend ($v)
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

	// {{{ (void) EDB_SQLITE2::close (void)
	/**
	 * Close the db handle
	 *
	 * @access public
	 * @return void
	 */
	function close () {
		if ( is_resource ($this->db) ) {
			sqlite_close ($this->db);
			unset ($this->db);
		}
	}
	// }}}

	/*
	 * Priavte functions
	 */
	// {{{ private (int) EDB_SQLITE2::no_bind_query ($sql)
	/** 
	 * Performs a query on the database
	 *
	 * @access private
	 * @return integer The number of affected rows or false
	 * @param  string  The query strings
	 */
	private function no_bind_query ($sql) {
		try {
			$this->result = sqlite_query ($this->db, $sql, SQLITE_ASSOC);
			if ( ! is_resource ($this->result) ) {
				$this->free = false;
				throw new myException (sqlite_last_error ($this->db), E_USER_WARNING);
				return false;
			}
		} catch ( Exception $e ) {
			$this->free = false;
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		$this->switch_freemark ();

		if ( preg_match ('/^(update|insert|delete|replace)/i', trim ($sql)) )
			return sqlite_changes ($this->db);
		if ( ! strncasecmp ('create|drop', trim ($sql), 6) )
			return 1;

		return sqlite_num_rows ($this->result);
	}
	// }}}

	// {{{ private (int) EDB_SQLITE2::bind_query ($sql, $parameters)
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
						$params[$j] = $params[$j]->data;
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
			@$this->free_result ();
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
