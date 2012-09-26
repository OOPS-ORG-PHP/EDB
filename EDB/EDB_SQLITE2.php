<?php
/**
 * Project: EDB_SQLITE2 :: SQLITE2 abstraction layer
 * File:    EDB/EDB_SQLITE2.php
 *
 * The EDB_SQLITE2 class is mysql abstraction layer that used internally
 * on EDB class.
 *
 * @category    Database
 * @package     EDB
 * @subpackage  EDB_ABSTRACT
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 2012 JoungKyun.Kim
 * @license     BSD License
 * @version     $Id: EDB_Common.php 4 2012-08-31 19:14:39Z oops $
 * @link        http://pear.oops.org/package/EDB
 * @filesource
 */

/**
 * SQLite3 engine for EDB API
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
	 * @access public
	 * @return object
	 * @param  string  $path  sqlite2 database file
	 * @param  integer $mode  The mode of the file. Intended to be used to open
	 *                        the database in read-only mode. Presently, this
	 *                        parameter is ignored by the sqlite library. The
	 *                        default value for mode is the octal value 0666
	 *                        and this is the recommended value.
	 */
	function __construct () {
		if ( ! extension_loaded ('sqlite') )
			throw new EDBException ('sqlite extension is not loaded on PHP!', E_ERROR);

		try {
			$_argv = func_get_args ();
			$argv = is_array ($_argv[0]) ? $_argv[0] : $_argv;;

			$o = (object) array (
				'path' => preg_replace ('!^sqlite2://!', '', $argv[0]),
				'mode' => $argv[2],
			);

			if ( ! $o->flag )
				$o->mode = 0666;

			$this->db = sqlite_open ($o->path, $o->mode, $error);
		} catch ( Exception $e ) {
			if ( $error )
				throw new EDBException ($error, $e->getCode(), $e);
			else
				throw new EDBException ($e->getMessage (), $e->getCode(), $e);
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
			throw new EDBException ('Unsupported method on SQLITE2 engine', E_ERROR);
	}
	// }}}

	// {{{ (bool) EDB_SQLITE2::set_charset ($charset)
	/** 
	 * Set character set of current database
	 *
	 * This method is not allow on SQLite2 Engine
	 *
	 * @access public
	 * @return bool   The name of character set that is supported on database
	 * @param  string $char name of character set that supported from database
	 */
	function set_charset () {
		throw new EDBException ('Unsupported method on SQLITE2 engine', E_ERROR);
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

		$sql = array_shift ($argv);
		$this->pno = $this->get_param_number ($sql);

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

	// {{{ (void) EDB_SQLITE2::seek ($offset)
	/**
	 * Adjusts the result pointer to an arbitrary row in the result
	 *
	 * @access public
	 * @return void
	 * @param  integer Must be between zero and the total number of rows minus one
	 */
	function seek ($offset) {
		return sqlite_seek ($this->result, $offset);
	}
	// }}}

	// {{{ (object) EDB_SQLITE2::fetch (void)
	/**
	 * Fetch a result row as an associative object
	 *
	 * @access public
	 * @return object The object of fetched a result row or false
	 */
	function fetch () {
		return sqlite_fetch_object ($this->result);
	}
	// }}}

	// {{{ (array) EDB_SQLITE2::fetch_all (void)
	/**
	 * Fetch all result rows as an associative object
	 *
	 * @access public
	 * @return array The fetched result rows
	 */
	function fetch_all () {
		$rows = sqlite_fetch_all ($this->result, SQLITE_ASSOC);
		$this->free_result ();

		return $rows;
	}
	// }}}

	// {{{ (void) EDB_SQLITE2::free_result (void)
	/**
	 * Frees stored result memory for the given statement handle
	 *
	 * @access public
	 * @return void
	 */
	function free_result () {
		if ( ! $this->free ) return;

		if ( isset ($this->result) )
			unset ($this->result);

		$this->result = null;

		$this->switch_freemark ();
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
		if ( is_resource ($this->db) )
			sqlite_close ($this->db);
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
			$this->free = true;
		} catch ( Exception $e ) {
			$this->free = false;
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

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
			throw new EDBException ('Number of elements in query doesn\'t match number of bind variables');
			return false;
		}

		$query = $this->bind_param ($sql, $params);
		return $this->no_bind_query ($query);
	}
	// }}}

	function __destruct () {
		@$this->free_result ();
		$this->close ();
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
