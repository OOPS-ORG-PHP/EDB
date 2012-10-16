<?php
/**
 * Project: EDB_SQLITE3 :: SQLITE3 abstraction layer
 * File:    EDB/EDB_SQLITE3.php
 *
 * The EDB_SQLITE3 class is sqlite3 abstraction layer that used internally
 * on EDB class.
 *
 * @category    Database
 * @package     EDB
 * @subpackage  EDB_ABSTRACT
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 2012 JoungKyun.Kim
 * @license     BSD License
 * @version     $Id$
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
	/**
	 * number of query result rows
	 * @var    integer
	 */
	private $nums = 0;
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
			throw new myException ('sqlite3 extension is not loaded on PHP!', E_USER_ERROR);

		try {
			$_argv = func_get_args ();
			$argv = is_array ($_argv[0]) ? $_argv[0] : $_argv;;

			$o = (object) array (
				'path' => preg_replace ('!^sqlite3://!', '', $argv[0]),
				'flag' => $argv[2],
			);

			if ( ! $o->flag )
				$o->flag = SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE;

			// for persistent connection. sqlite3 don't support
			if ( preg_match ('!^p~!', $o->path) )
				$o->path = preg_replace ('!^p~!', '', $o->path);

			$this->db = new SQLite3 ($o->path, $o->flag);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
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
		return 'Unsupport';
		#throw new myException ('Unsupported method on SQLITE3 engine', E_ERROR);
	}
	// }}}

	// {{{ (bool) EDB_SQLITE3::set_charset ($charset)
	/** 
	 * Set character set of current database
	 *
	 * This method is not allow on SQLite3 Engine, and always
	 * returns true.
	 *
	 * @access public
	 * @return bool   always retuns true
	 * @param  string $char name of character set that supported from database
	 */
	function set_charset () {
		return true;
		#throw new myException ('Unsupported method on SQLITE3 engine', E_ERROR);
	}
	// }}}

	// {{{ (string) EDB_SQLITE3::escape ($string)
	/** 
	 * Escape special characters in a string for use in an SQL statement
	 *
	 * @access public
	 * @return string
	 * @param  string  The string that is to be escaped.
	 */
	function escape ($string) {
		return $this->db->escapeString ($string);
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

		try {
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
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (bool) EDB_SQLITE3::seek ($offset)
	/**
	 * Adjusts the result pointer to an arbitrary row in the result
	 *
	 * @access public
	 * @return boolean
	 * @param  integer Must be between zero and the total number of rows minus one
	 */
	function seek ($offset) {
		try {
			if ( ! is_object ($this->result) )
				return false;

			$this->result->reset ();

			if ( $offset == 0 )
				return true;

			if ( $offset >= $this->nums )
				$offset = $this->nums;

			$i = 0;
			$offset--;
			while ( $this->result->fetchArray (SQLITE3_ASSOC) !== false ) {
				if ( $i == $offset )
					break;
				$i++;
			}
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		return true;
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
		try {
			$r = $this->result->fetchArray (SQLITE3_ASSOC);
			return is_array ($r) ? (object) $r : false;
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (array) EDB_SQLITE3::fetch_all ($free = true)
	/**
	 * Fetch all result rows as an associative object
	 *
	 * @access public
	 * @return array The fetched result rows
	 * @param  boolean (optional) free result set after fetch.
	 *                 Defaluts is true.
	 */
	function fetch_all ($free = true) {
		$this->field = array ();
		$rows = array ();

		try {
			while ( ($row = $this->result->fetchArray (SQLITE3_ASSOC)) !== false )
				$rows[] = (object) $row;

			if ( $free )
				$this->free_result ();
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return array ();
		}

		return $rows;
	}
	// }}}

	// {{{ (bool) EDB_SQLITE3::free_result (void)
	/**
	 * Frees stored result memory for the given statement handle
	 *
	 * @access public
	 * @return boolean always returns true
	 */
	function free_result () {
		if ( ! $this->free ) return true;
		$this->free = false;

		try {
			$this->result->finalize ();
			if ( $this->stmt instanceof SQLite3Stmt )
				$this->stmt->clear ();
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		return true;
	}
	// }}}

	// {{{ (string) EDB_SQLITE3::field_name ($index)
	/**
	 * Returns the name of the column specified by the column_number.
	 *
	 * @access public
	 * @return string|false
	 * @param  integer The numeric zero-based index of the column.
	 */
	function field_name ($index) {
		try {
			if ( ! is_object ($this->result) )
				return false;
			return $this->result->columnName ($index);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (string) EDB_SQLITE3::field_type ($index)
	/**
	 * Get the type of the specified field in a result
	 *
	 * @access public
	 * @return string|false
	 * @param  integer The numeric zero-based index of the column.
	 */
	function field_type ($field_index) {
		try {
			if ( ! is_object ($this->result) )
				return false;

			$r = $this->result->columnType ($index);

			switch ($r) {
				case SQLITE3_INTEGER :
					return 'int';
				case SQLITE3_FLOAT :
					return 'float';
				case SQLITE3_TEXT :
					return 'string';
				case SQLITE3_BLOB :
					return 'blob';
				case SQLITE3_NULL :
					return 'null';
				default :
					//throw new myException ('Unknown. This is libsqlite3 bug!', E_USER_WARNING);
					//return false;
					return 'unknown, maybe libsqlite3 bug?';
			}
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (int) EDB_SQLITE3::num_fields (void)
	/**
	 * Get number of fields in result
	 *
	 * @access public
	 * @return integer|false
	 */
	function num_fields () {
		try {
			if ( ! is_object ($this->result) )
				return false;

			return $this->result->numColumns ();
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
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
		$this->nums = 0;

		try {
			$r = &$this->result;

			$i = 0;
			while ( ($r->fetchArray (SQLITE3_ASSOC)) !== false )
				$this->nums++;

			unset ($r);

			return $this->nums;
		} catch ( Exception $e ) {
			$this->free = false;
			throw new myException ($e->getMessage (), $e->getCode(), $e);
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
			if ( ($this->result = $this->db->query ($sql)) === false ) {
				$this->free = false;
				throw new myException ($this->db->lastErrorMsg (), E_USER_WARNING);
				return false;
			}
		} catch ( Exception $e ) {
			$this->free = false;
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		$this->switch_freemark ();

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
			throw new myException ($e->getMessage (), $e->getCode(), $e);
		}

		if ( $this->pno != count ($params) || $this->check_param ($params) === false ) {
			$this->stmt->clear ();
			throw new myException (
				'Number of elements in query doesn\'t match number of bind variables',
				E_USER_WARNING
			);
			return false;
		}

		$param[] = array_shift ($params);
		for ( $i=0; $i<count ($params); $i++ )
			$param[] = &$params[$i];

		try {
			for ( $i=1; $i<$this->pno+1; $i++ ) {
				switch ($param[0][$i-1]) {
					case 'b' :
					case 'c' :
						$data = is_object ($param[$i]) ? $param[$i]->data : $param[$i];
						$this->stmt->bindParam ($i, $data, SQLITE3_BLOB);
						unset ($data);
						break;
					case 'i' :
						$this->stmt->bindParam ($i, $param[$i], SQLITE3_INTEGER);
						break;
					case 'd' :
					case 'f' :
						$this->stmt->bindParam ($i, $param[$i], SQLITE3_FLOAT);
						break;
					case 'n' :
						$this->stmt->bindParam ($i, $param[$i], SQLITE3_NULL);
						break;
					default :
						$this->stmt->bindParam ($i, $param[$i]);
				}
			}

			$this->result = $this->stmt->execute ();
		} catch ( Exception $e ) {
			#$this->stmt->clear ();
			throw new myException ($e->getMessage (), $e->getCode(), $e);
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
