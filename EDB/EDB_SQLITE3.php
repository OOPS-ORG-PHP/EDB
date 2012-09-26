<?php
/**
 * Project: EDB_SQLITE3 :: SQLITE3 abstraction layer
 * File:    EDB/EDB_SQLITE3.php
 *
 * The EDB_SQLITE3 class is mysql abstraction layer that used internally
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
Class EDB_SQLITE3 extends EDB_Common {
	// {{{ properties
	/**#@+
	 * @access private
	 */
	/**
	 * db handler of EDB_SQLITE3 class
	 * @var    object
	 */
	private $db;
	/**
	 * SQLITE3 STMT object of EDB_SQLITE3 class
	 * @var    object
	 */
	private $stmt;
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

	// {{{ (object) EDB_SQLITE3::__construct ($path, $flag = SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE)
	/** 
	 * Instantiates an EDB_SQLITE3 object and opens an SQLite 3 database
	 *
	 * For examples:
	 * <code>
	 * $db = new EDB_SQLITE3 ('sqlite3:///path/file.db');
	 * $db = new EDB_SQLITE3 ('sqlite3:///path/file.db', SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
	 * $db = new EDB_SQLITE3 ('sqlite3://:memory:', SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
	 * </code>
	 *
	 * @access public
	 * @return object
	 * @param  string  $path  sqlite3 database file
	 * @param  int     $flags (optinal) open flags of sqlite3. See also {@link http://manual.phpdoc.org/HTMLSmartyConverter/PHP/phpDocumentor/tutorial_tags.inlinelink.pkg.html SQLite3::__construct}.
	 */
	function __construct () {
		if ( ! extension_loaded ('sqlite3') )
			throw new EDBException ('sqlite3 extension is not loaded on PHP!', E_ERROR);

		try {
			$_argv = func_get_args ();
			$argv = is_array ($_argv[0]) ? $_argv[0] : $_argv;;

			$o = (object) array (
				'path' => preg_replace ('!^sqlite3://!', '', $argv[0]),
				'flag' => $argv[2],
			);

			if ( ! $o->flag )
				$o->flag = SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE;

			$this->db = new SQLite3 ($o->path, $o->flag);
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
		}
	}
	// }}}

	// {{{ (string) EDB_SQLITE3::get_charset (void)
	/** 
	 * Get character set of current database
	 *
	 * This method is not allow on SQLite3 Engine
	 *
	 * @access public
	 * @return string Current character set name
	 */
	function get_charset () {
		throw new EDBException ('Unsupported method on SQLITE3 engine', E_ERROR);
	}
	// }}}

	// {{{ (bool) EDB_SQLITE3::set_charset ($charset)
	/** 
	 * Set character set of current database
	 *
	 * This method is not allow on SQLite3 Engine
	 *
	 * @access public
	 * @return bool   The name of character set that is supported on database
	 * @param  string $char name of character set that supported from database
	 */
	function set_charset () {
		throw new EDBException ('Unsupported method on SQLITE3 engine', E_ERROR);
	}
	// }}}

	// {{{ (int) EDB_SQLITE3::query ($query, $param_type, $param1, $param2 ...)
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
	 * i => integer SQLITE3_INTEGER
	 * d => double  SQLITE3_FLOAT
	 * s => string  SQLITE3_TEXT
	 * b => blob    SQLITE3_BLOB
	 * n => null    SQLITE3_NULL
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

	// {{{ (void) EDB_SQLITE3::seek ($offset)
	/**
	 * Adjusts the result pointer to an arbitrary row in the result
	 *
	 * This method don't support on SQLITE3 engine
	 *
	 * @access public
	 * @return void
	 * @param  integer Must be between zero and the total number of rows minus one
	 */
	function seek ($offset) {
		throw new EDBException ('Unsupported method on SQLITE3 engine', E_ERROR);
	}
	// }}}

	// {{{ (object) EDB_SQLITE3::fetch (void)
	/**
	 * Fetch a result row as an associative object
	 *
	 * @access public
	 * @return object The object of fetched a result row or false
	 */
	function fetch () {
		return $this->result->fetchArray ();
	}
	// }}}

	// {{{ (array) EDB_SQLITE3::fetch_all (void)
	/**
	 * Fetch all result rows as an associative object
	 *
	 * @access public
	 * @return array The fetched result rows
	 */
	function fetch_all () {
		$this->field = array ();
		$rows = array ();

		while ( ($row = $this->result->fetchArray (SQLITE3_ASSOC)) !== false )
			$rows[] = $row;

		$this->free_result ();

		return $rows;
	}
	// }}}

	// {{{ (void) EDB_SQLITE3::free_result (void)
	/**
	 * Frees stored result memory for the given statement handle
	 *
	 * @access public
	 * @return void
	 */
	function free_result () {
		if ( ! $this->free ) return;

		try {
			$this->result->finalize ();
			if ( $this->stmt instanceof SQLite3Stmt )
				$this->stmt->clear ();
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
		}

		$this->switch_freemark ();
	}
	// }}}

	// {{{ (void) EDB_SQLITE3::close (void)
	/**
	 * Close the db handle
	 *
	 * @access public
	 * @return void
	 */
	function close () {
		if ( is_object ($this->db) )
			$this->db->close ();
	}
	// }}}

	/*
	 * Priavte functions
	 */

	// {{{ private (int) EDB_SQLITE3::num_rows ()
	/**
	 * Returns the number of rows in the result set
	 *
	 * SQLite3 extension don't support num_rows method. Why???
	 *
	 * @access private
	 * @return integer
	 */
	function num_rows () {
		try {
			$r = &$this->result;

			$i = 0;
			while ( ($r->fetchArray (SQLITE3_ASSOC)) !== false )
				$i++;

			unset ($r);

			return $i;
		} catch ( Exception $e ) {
			$this->free = false;
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ private (int) EDB_SQLITE3::no_bind_query ($sql)
	/** 
	 * Performs a query on the database
	 *
	 * @access private
	 * @return integer The number of affected rows or false only update|insert|delete.
	 *                 other row is returned -1.
	 * @param  string  The query strings
	 */
	private function no_bind_query ($sql) {
		try {
			$this->result = $this->db->query ($sql);
			$this->free = true;
		} catch ( Exception $e ) {
			$this->free = false;
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		if ( preg_match ('/^(update|insert|delete|replace)/i', trim ($sql)) )
			return $this->db->changes ();
		else if ( preg_match ('/^create|drop/i', trim ($sql)) ) 
			return 1;

		return $this->num_rows ();
	}
	// }}}

	// {{{ private (int) EDB_SQLITE3::bind_query ($sql, $parameters)
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

		try {
			$this->stmt = $this->db->prepare ($sql);
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
		}

		if ( $this->pno != count ($params) || $this->check_param ($params) === false ) {
			$this->stmt->clear ();
			throw new EDBException ('Number of elements in query doesn\'t match number of bind variables');
			return false;
		}

		$param[] = array_shift ($params);
		for ( $i=0; $i<count ($params); $i++ )
			$param[] = &$params[$i];

		try {
			for ( $i=1; $i<$this->pno+1; $i++ )
				$this->stmt->bindParam ($i, $param[$i]);

			$this->result = $this->stmt->execute ();
		} catch ( Exception $e ) {
			#$this->stmt->clear ();
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			#return false;
		}

		$this->switch_freemark ();

		if ( preg_match ('/^(create|update|insert|delete)/i', trim ($sql)) )
			return $this->db->changes ();
		else if ( preg_match ('/^create/i', trim ($sql)) )
			return 1;

		return $this->num_rows ();
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
