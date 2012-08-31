<?php
/**
 * PHP Version 5
 *
 * Copyright (c) 1997-2012 JoungKyun.Kim
 *
 * LICENSE: BSD
 *
 * @category    Database
 * @package     EDB_MYSQLI
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   1997-2012 OOPS.org
 * @license     BSD
 * @version     SVN: $Id: $
 */

Class EDB_MYSQLI {
	// {{{ prpperties
	/**
	 * db handler of EDB_MYSQLI class
	 * @access private
	 * @var    object
	 */
	static private $db;
	/**
	 * DB result handler of EDB_MYSQLI class
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
	/**
	 * The error messages
	 * @access public
	 * @var    string or null
	 */
	public $error = null;
	// }}}

	// {{{ (void) EDB_MYSQLI::__construct ($host, $user, $pass, $db)
	/** 
	 * Initialize EDB_MYSQLI class
	 *
	 * @access public
	 * @return object
	 * @param  string  mysql host, format is 'mysqli://localhost[:[port|sockfile]]'
	 * @param  string  mysql user
	 * @param  string  mysql password
	 * @param  string  mysql database
	 */
	function __construct () {
		$_argv = func_get_args ();
		$argv = is_array ($_argv[0]) ? $_argv[0] : $_argv;;

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

		$this->db = new mysqli ($o->host, $o->user, $o->pass, $o->db, $o->port, $o->sock);
		$this->error = mysqli_connect_error ();
	}
	// }}}

	// {{{ (array) EDB_MYSQLI::get_charset (void)
	/** 
	 * Get character set of current database
	 *
	 * @access public
	 * @return string Current character set name
	 * @param  void
	 */
	function get_charset () {
		if ( is_object ($this->db) )
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
	 * @param  string  The query strings
	 * @param  string  (optional) Bind parameter type
	 * @param  mixed   (optional) Bind parameter 1
	 * @param  mixed   (optional) Bind parameter 2 ..
	 */
	function query () {
		$_argv = func_get_args ();
		$argv = is_array ($_argv[0]) ? $_argv[0] : $_argv;;

		$this->error = null;

		$sql = array_shift ($argv);
		$this->pno = $this->get_param_number ($sql);

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

	// {{{ (object) EDB_MYSQLI::fetch (void)
	/**
	 * Fetch a result row as an associative object
	 *
	 * @access public
	 * @return object The object of fetched a result row or false
	 * @param  void
	 */
	function fetch () {
		if ( $this->stmt instanceof mysqli_result )
			return $this->fetch_result ();
		else if ( $this->stmt instanceof mysqli_stmt )
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
		if ( $this->stmt instanceof mysqli_result )
			return $this->fetch_result_all ();
		else if ( $this->stmt instanceof mysqli_stmt )
			return $this->fetch_stmt_all ();

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
		$this->stmt->free_result ();
		if ( $this->stmt instanceof mysqli_stmt )
			$this->stmt->close ();
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
	// {{{ private (int) EDB_MYSQLI:: get_param_number ($sql)
	/**
	 * Get number of query parameters
	 *
	 * @access private
	 * @return integer The number of parameters
	 * @param  string Bind query string
	 */
	private function get_param_number ($sql) {
		return strlen (preg_replace ('/[^?]/', '', $sql));
	}
	// }}}

	// {{{ private (bool) EDB_MYSQLI::check_param ($parameters)
	/**
	 * Check parameter type and parameters
	 *
	 * @access private
	 * @return bool
	 * @param  array The parameter of bind query
	 */
	private function check_param ($param) {
		if ( ! is_array ($param) )
			return false;

		if ( count ($param) < 2 )
			return false;

		$type = array_shift ($param);
		if ( strlen ($type) != count ($param) )
			return false;

		return true;
	}
	// }}}

	// {{{ private (int) EDB_MYSQLI::no_bind_query ($sql)
	/** 
	 * Performs a query on the database
	 *
	 * @access private
	 * @return integer The number of affected rows or false
	 * @param  string  The query strings
	 */
	private function no_bind_query ($sql) {
		$this->stmt = $this->db->query ($sql);
		if ( $this->db->errno ) {
			$this->error = $this->db->error;
			return false;
		}

		if ( preg_match ('/^(update|insert|delete)/i', trim ($sql)) ) {
			/* Insert or update, or delete query */
			return $this->db->affected_rows;
		}

		return $this->stmt->num_rows;
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

		$this->stmt = $this->db->prepare ($sql);

		if ( $this->db->errno || ! is_object ($this->stmt) ) {
			$this->error = sprintf ('Query Failes: %s', $this->db->error);
			return false;
		}

		if ( $this->pno != count ($params) || $this->check_param ($params) === false ) {
			$this->stmt->free_result ();
			$this->error = 'Number of elements in query doesn\'t match number of bind variables';
			return false;
		}

		$param[] = array_shift ($params);
		for ( $i=0; $i<count ($params); $i++ )
			$param[] = &$params[$i];

		if ( call_user_func_array (array ($this->stmt, 'bind_param'), $param) === false ) {
			$this->stmt->free_result ();
			$this->error = $this->stmt->error;
			return false;
		}

		if ( $this->stmt->execute () === false ) {
			$this->stmt->free_result ();
			$this->error = $this->stmt->error;
			return false;
		}

		$this->bind_result ();

		return $this->stmt->affected_rows;
	}
	// }}}

	// {{{ private (void) EDB_MYSQLI::bind_result (void)
	/**
	 * Binds variables to a prepared statement for result storage
	 *
	 * @access private
	 * @return void
	 * @param  void
	 */
	private function bind_result () {
		if ( preg_match ('/^(update|insert|delete)/i', trim ($sql)) )
			return;

		$this->stmt->store_result ();
		$var = array ();
		$meta = $this->stmt->result_metadata ();

		while ( $fields = $meta->fetch_field () )
			$var[] = &$this->field[$fields->name];

		$meta->free ();
		call_user_func_array(array($this->stmt, 'bind_result'), $var);
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
		return $this->stmt->fetch_object ();
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
		if ( $fetch_check = $this->stmt->fetch () ) {
			foreach ( $this->field as $key => $val )
				$retval->$key = $val;
		} else
			$retval = null;

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
		$this->field = array ();
		$rows = array ();

		while ( ($row = $this->stmt->fetch_object ()) !== null )
			$rows[] = $row;

		$this->stmt->free_result ();
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
		$r = array ();

		$i = 0;
		while ( $this->stmt->fetch () ) {
			foreach ( $this->field as $key => $val )
				$r[$i]->$key = $val;
			$i++;
		}

		$this->stmt->free_result ();

		return $r;
	}
	// }}}

	function __destruct () {
		//@$this->free_result ();
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
