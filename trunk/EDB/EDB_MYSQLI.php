<?php
/**
 * Project: EDB_MYSQLI :: MySQLi abstraction layer
 * File:    EDB/EDB_MYSQLI.php
 *
 * The EDB_MYSQLI class is mysql abstraction layer that used internally
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
	 * @return object
	 * @param  string  $hostname mysql host
	 * @param  string  $user     mysql user
	 * @param  string  $password mysql password
	 * @param  string  $database mysql database
	 */
	function __construct () {
		$_argv = func_get_args ();
		$argv = is_array ($_argv[0]) ? $_argv[0] : $_argv;;

		if ( ! extension_loaded ('mysqli') )
			throw new EDBException ('MySQLi extension is not loaded on PHP!', E_ERROR);

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
				throw new EDBException (mysqli_connect_error (), E_ERROR);
			else
				throw new EDBException ($e->getMessage (), $e->getCode(), $e);
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
		if ( $this->db instanceof mysqli )
			return $this->db->character_set_name ();
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
		$r = false;

		if ( is_object ($this->db) ) {
			if ( ($r = $this->db->set_charset ($char)) === false )
				$this->error = $this->db->error;
		}

		return $r;
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

	// {{{ (void) EDB_MYSQLI::seek ($offset)
	/**
	 * Adjusts the result pointer to an arbitrary row in the result
	 *
	 * @access public
	 * @return void
	 * @param  integer Must be between zero and the total number of rows minus one
	 */
	function seek ($offset) {
		if ( is_object ($this->result) )
			$this->result->data_seek ($offset);
	}
	// }}}

	// {{{ (object) EDB_MYSQLI::fetch (void)
	/**
	 * Fetch a result row as an associative object
	 *
	 * @access public
	 * @return object The object of fetched a result row or false
	 * @param  void
	 */
	function fetch () {
		if ( $this->result instanceof mysqli_result )
			return $this->fetch_result ();
		else if ( $this->result instanceof mysqli_stmt )
			return $this->fetch_stmt ();
		return false;
	}
	// }}}

	// {{{ (array) EDB_MYSQLI::fetch_all (void)
	/**
	 * Fetch all result rows as an associative object
	 *
	 * @access public
	 * @return array The fetched result rows
	 * @param  void
	 */
	function fetch_all () {
		if ( $this->result instanceof mysqli_result ) {
			return $this->fetch_result_all ();
		} else if ( $this->result instanceof mysqli_stmt ) {
			return $this->fetch_stmt_all ();
		}

		return array ();
	}
	// }}}

	// {{{ (void) EDB_MYSQLI::free_result (void)
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
			if ( is_object ($this->result) )
				$this->result->free_result ();

			if ( $this->result instanceof mysqli_stmt )
				$this->result->close ();
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		$this->switch_freemark ();
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
		} catch ( Exception $e ) {
			$this->free = false;
			$err = $this->db->errno ? $this->db->error : $e->getMessage ();
			throw new EDBException ($err, $e->getCode(), $e);
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
		$this->switch_freemark ();

		if ( $this->db->errno || ! is_object ($this->result) ) {
			throw new EDBException ($this->db->error, E_WARNING);
			return false;
		}

		if ( $this->pno != count ($params) || $this->check_param ($params) === false ) {
			$this->free_result ();
			throw new EDBExeption ('Number of elements in query doesn\'t match number of bind variables', E_WARNING);
			return false;
		}

		$param[] = array_shift ($params);
		for ( $i=0; $i<count ($params); $i++ )
			$param[] = &$params[$i];

		if ( call_user_func_array (array ($this->result, 'bind_param'), $param) === false ) {
			$this->free_result ();
			throw new EDBException ($this->result->error, E_WARNING);
			return false;
		}

		if ( $this->result->execute () === false ) {
			$this->free_result ();
			throw new EDBException ($this->result->error, E_WARNING);
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

		$this->result->store_result ();
		$var = array ();
		$meta = $this->result->result_metadata ();

		while ( $fields = $meta->fetch_field () )
			$var[] = &$this->field[$fields->name];

		$meta->free ();
		if ( call_user_func_array(array($this->result, 'bind_result'), $var) === false ) {
			$this->free_result ();
			throw new EDBException ($this->result->error, E_WARNING);
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
			foreach ( $this->field as $key => $val )
				$retval->$key = $val;
		} else
			$retval = false;

		return $retval;
	}
	// }}}

	// {{{ private (array) EDB_MYSQLI::fetch_result_all (void)
	/**
	 * Fetch all result rows as an associative object
	 *
	 * @access private 
	 * @return array The fetched result rows
	 * @param  void
	 */
	private function fetch_result_all () {
		if ( ! $this->result instanceof mysqli_result )
			return array ();

		$this->field = array ();
		$rows = array ();

		while ( ($row = $this->result->fetch_object ()) !== null )
			$rows[] = $row;

		$this->free_result ();
		return $rows;
	}
	// }}}

	// {{{ private (array) EDB_MYSQLI::fetch_stmt_all (void)
	/**
	 * Fetch all result rows as an associative object
	 *
	 * @access public
	 * @return array The fetched result rows
	 * @param  void
	 */
	private function fetch_stmt_all () {
		if ( ! $this->result instanceof mysqli_stmt )
			return array ();

		$r = array ();

		$i = 0;
		while ( $this->result->fetch () ) {
			foreach ( $this->field as $key => $val )
				$r[$i]->$key = $val;
			$i++;
		}

		$this->free_result ();

		return $r;
	}
	// }}}

	function __destruct () {
		$this->free_result ();
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
