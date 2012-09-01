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
 * @version     SVN: $Id$
 */

Class EDB_MYSQLI extends EDB_Common {
	/**
	 * db handler of EDB_MYSQLI class
	 * @access private
	 * @var    object
	 */
	static private $db;
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
		try {
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
			#$this->error = mysqli_connect_error ();
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
		}
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
		try {
			if ( is_object ($this->db) )
				return $this->db->character_set_name ();
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
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
		$r = false;

		try {
			if ( is_object ($this->db) ) {
				if ( ($r = $this->db->set_charset ($char)) === false )
					$this->error = $this->db->error;
			}
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
		}

		return $r;
	}
	// }}}

	// {{{ (int) EDB_MYSQLI::query ($query, $param_type, $param1, $param2 ...)
	/** 
	 * Performs a query on the database
	 *
	 * See also http://php.net/manual/en/mysqli-stmt.bind-param.php
	 *
	 * @access public
	 * @return integer The number of affected rows or false
	 * @param  string  The query strings
	 * @param  string  (optional) Bind parameter type
	 *                            i => integer
	 *                            d => double
	 *                            s => string
	 *                            b => blob
	 * @param  mixed   (optional) Bind parameter 1
	 * @param  mixed   (optional) Bind parameter 2 ..
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
		if ( $this->result instanceof mysqli_result )
			return $this->fetch_result_all ();
		else if ( $this->result instanceof mysqli_stmt )
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
		if ( ! $this->free ) return;

		try {
			$this->result->free_result ();
			if ( $this->result instanceof mysqli_stmt )
				$this->result->close ();
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
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
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		$this->switch_freemark ();

		if ( preg_match ('/^(update|insert|delete)/i', trim ($sql)) ) {
			/* Insert or update, or delete query */
			return $this->db->affected_rows;
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

		try {
			$this->result = $this->db->prepare ($sql);
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		if ( $this->pno != count ($params) || $this->check_param ($params) === false ) {
			$this->result->free_result ();
			throw new EDBExeption ('Number of elements in query doesn\'t match number of bind variables');
			return false;
		}

		$param[] = array_shift ($params);
		for ( $i=0; $i<count ($params); $i++ )
			$param[] = &$params[$i];

		try {
			call_user_func_array (array ($this->result, 'bind_param'), $param);
		} catch ( Exception $e ) {
			$this->result->free_result ();
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		try {
			$this->result->execute ();
		} catch ( Exception $e ) {
			$this->result->free_result ();
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		$this->switch_freemark ();
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

		try {
			$this->result->store_result ();
			$var = array ();
			$meta = $this->result->result_metadata ();

			while ( $fields = $meta->fetch_field () )
				$var[] = &$this->field[$fields->name];

			$meta->free ();
			call_user_func_array(array($this->result, 'bind_result'), $var);
		} catch ( Exception $e ) {
			$this->result->free_result ();
			if ( is_object ($meta) )
				$meta->free ();
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
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
		try {
			$r  = $this->result->fetch_object ();
			return is_object ($r) ? $r : false;
		} catch ( Exception $e ) {
			$this->result->free_result ();
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
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
		$this->field = array ();
		$rows = array ();

		try {
			while ( ($row = $this->result->fetch_object ()) !== null )
				$rows[] = $row;
		} catch ( Exception $e ) {
			$this->result->free_result ();
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return array ();
		}

		$this->result->free_result ();
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

		try {
			$i = 0;
			while ( $this->result->fetch () ) {
				foreach ( $this->field as $key => $val )
					$r[$i]->$key = $val;
				$i++;
			}
		} catch ( Exception $e ) {
			$this->result->free_result ();
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return array ();
		}

		$this->result->free_result ();

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
