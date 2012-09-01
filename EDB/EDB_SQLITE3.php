<?php
/**
 * PHP Version 5
 *
 * Copyright (c) 1997-2012 JoungKyun.Kim
 *
 * LICENSE: BSD
 *
 * @category    Database
 * @package     EDB_SQLITE3
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   1997-2012 OOPS.org
 * @license     BSD
 * @version     SVN: $Id: EDB_SQLITE3.php 4 2012-08-31 19:14:39Z oops $
 */

Class EDB_SQLITE3 extends EDB_Common {
	/**
	 * db handler of EDB_SQLITE3 class
	 * @access private
	 * @var    object
	 */
	static private $db;
	/**
	 * SQLITE3 STMT object of EDB_SQLITE3 class
	 * @access private
	 * @var    object
	 */
	static private $stmt;
	/**
	 * The number of query parameter
	 * @access private
	 * @var    integer
	 */
	static private $pno = 0;
	/**
	 * The number of query parameter
	 * @access private
	 * @var    integer
	 */
	static private $field = array ();
	// }}}

	// {{{ (void) EDB_SQLITE3::__construct ($path, $db, $flag = SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE)
	/** 
	 * Initialize EDB_SQLITE3 class
	 *
	 * @access public
	 * @return object
	 * @param  string  sqlite3 database file, format is 'sqlite3:///path/file.db'
	 * @param  int     mysql database
	 */
	function __construct () {
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

	// {{{ (array) EDB_SQLITE3::get_charset (void)
	/** 
	 * Get character set of current database
	 *
	 * @access public
	 * @return string Current character set name
	 * @param  void
	 */
	function get_charset () {
		throw new EDBException ('Unsupport on SQLITE3 engine');
	}
	// }}}

	// {{{ (bool) EDB_SQLITE3::set_charset ($charset)
	/** 
	 * Set character set of current database
	 *
	 * @access public
	 * @return bool    The name of character set that is supported on database
	 * @param  string  name of character set that supported from database
	 */
	function set_charset ($char) {
		throw new EDBException ('Unsupport on SQLITE3 engine');
	}
	// }}}

	// {{{ (int) EDB_SQLITE3::query ($query, $param_type, $param1, $param2 ...)
	/** 
	 * Performs a query on the database
	 *
	 * http://www.php.net/manual/en/sqlite3stmt.bindparam.php
	 *
	 * @access public
	 * @return integer The number of affected rows or false
	 * @param  string  (optional) The query strings
	 *                            i => integer SQLITE3_INTEGER
	 *                            d => double  SQLITE3_FLOAT
	 *                            s => string  SQLITE3_TEXT
	 *                            b => blob    SQLITE3_BLOB
	 *                            n => null    SQLITE3_NULL
	 * @param  string  (optional) Bind parameter type
	 * @param  mixed   (optional) Bind parameter 1
	 * @param  mixed   (optional) Bind parameter 2 ..
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

	// {{{ (object) EDB_SQLITE3::fetch (void)
	/**
	 * Fetch a result row as an associative object
	 *
	 * @access public
	 * @return object The object of fetched a result row or false
	 * @param  void
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
	 * @param  void
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
	 * @param  void
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
	// {{{ private (int) EDB_SQLITE3::no_bind_query ($sql)
	/** 
	 * Performs a query on the database
	 *
	 * @access private
	 * @return integer The number of affected rows or false only update|insert|delete.
	 *                 selected row is returned -1.
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

		if ( preg_match ('/^(update|insert|delete)/i', trim ($sql)) )
			return $this->db->changes ();

		return -1;
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

		if ( preg_match ('/^(update|insert|delete)/i', trim ($sql)) )
			return $this->db->changes ();

		return -1;
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
