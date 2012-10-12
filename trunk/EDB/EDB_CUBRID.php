<?php
/**
 * Project: EDB_CUBRID :: CUBRID abstraction layer
 * File:    EDB/EDB_CUBRID.php
 *
 * The EDB_CUBRID class is cubrid abstraction layer that used internally
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
 * CUBRID engine for EDB API
 *
 * This class support abstracttion DB layer for CUBRID Engine
 *
 * @package     EDB
 */
Class EDB_CUBRID extends EDB_Common {
	// {{{ properties
	/**#@+
	 * @access private
	 */
	/**
	 * db handler of EDB_CUBRID class
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

	// {{{ (object) EDB_CUBRID::__construct ($host, $user, $pass, $db)
	/** 
	 * Instantiates an EDB_CUBRID object and opens an cubrid database
	 *
	 * For examples:
	 * <code>
	 * $db = new EDB_CUBRID ('cubrid://localhost', 'user', 'host', 'database');
	 * $db = new EDB_CUBRID ('cubrid://localhost:33000', 'user', 'host', 'database');
	 * $db = new EDB_CUBRID ('cubrid://localhost:33000?autocommit=false', 'user', 'host', 'database');
	 * </code>
	 *
	 * If you add prefix 'p~' before host, you can connect with persistent
	 * connection.
	 *
	 * For Examples:
	 * <code>
	 * $db = new EDB_CUBRID ('cubrid://p~localhost', 'user', 'host', 'database');
	 * </code>
	 *
	 * @access public
	 * @return object
	 * @param  string  $hostname cubrid host
	 * @param  string  $user     cubrid user
	 * @param  string  $password cubrid password
	 * @param  string  $database cubrid database
	 */
	function __construct () {
		$_argv = func_get_args ();
		$argv = is_array ($_argv[0]) ? $_argv[0] : $_argv;;

		if ( ! extension_loaded ('cubrid') )
			throw new EDBException ('CUBRID extension is not loaded on PHP!', E_ERROR);

		$o = (object) array (
			'host' => preg_replace ('!^cubrid://!', '', $argv[0]),
			'user' => $argv[1],
			'pass' => $argv[2],
			'db'   => $argv[3]
		);

		if ( preg_match ('/\?.*/', $o->host, $matches) ) {
			$o->opt = $matches[0];
			$o->host = preg_replace ('/\?.*/', '', $o->host);
		}

		if ( preg_match ('/^([^:]+):(.+)/', $o->host, $matches) ) {
			if ( ! is_numeric ($matches[2]) )
				$o->host .= ':33000';
		} else
			$o->host .= ':33000';

		if ( preg_match ('/^p~/', $o->host) ) {
			$func = 'cubrid_pconnect_with_url';
			$o->host = preg_replace ('/^p~/', '', $o->host);
		} else
			$func = 'cubrid_connect_with_url';

		$url = sprintf ('CUBRID:%s:%s:::%s', $o->host, $o->db, $o->opt);

		try {
			$this->db = $func ($url, $o->user, $o->pass);
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
		}
	}
	// }}}

	// {{{ (string) EDB_CUBRID::get_charset (void)
	/** 
	 * Get character set of current database
	 *
	 * CUBRID extension don't support this function
	 *
	 * @access public
	 * @return string Current character set name on DB
	 */
	function get_charset () {
		try {
			return cubrid_get_charset ($this->db);
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (bool) EDB_CUBRID::set_charset ($charset)
	/** 
	 * Set character set of current database
	 *
	 * This method is always returned true because CUBRID don't support
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

	// {{{ (string) EDB_CUBRID::escape ($string)
	/** 
	 * Escape special characters in a string for use in an SQL statement
	 *
	 * @access public
	 * @return string
	 * @param  string  The string that is to be escaped.
	 */
	function escape ($string) {
		return cubrid_real_escape_string ($string, $this->db);
	}
	// }}}

	// {{{ (int) EDB_CUBRID::query ($query, $param_type, $param1, $param2 ...)
	/** 
	 * Performs a query on the database
	 *
	 * @access public
	 * @return integer The number of affected rows or false
	 * @param  string  $query The query strings
	 * @param  string  $type  (optional) Bind parameter type. See also
	 * {@link http://php.net/manual/en/cubridi-stmt.bind-param.php cubridi_stmt::bind_param}.
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

		if ( $this->pno++ == 0 ) // no bind query
			$this->no_bind_query ($sql);
		else // bind query
			$this->bind_query ($sql, $argv);

		if ( preg_match ('/^(update|insert|delete|replace)/i', trim ($sql)) ) {
			/* Insert or update, or delete query */
			return cubrid_affected_rows ($this->db);
		} else if ( preg_match ('/create|drop/i', trim ($sql)) ) {
			return 1;
		}

		return cubrid_num_rows ($this->result);
	}
	// }}}

	// {{{ (bool) EDB_CUBRID::seek ($offset)
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
			// cubrid_move_cursor은 시작점이 0이 아니라 1이라서
			// 호환성에 문제가 있어 cubrid_data_seek를 사용한다.
			//return cubrid_move_cursor ($this->result, $offset, CUBRID_CURSOR_FIRST);
			return cubrid_data_seek ($this->result, $offset);
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (object) EDB_CUBRID::fetch (void)
	/**
	 * Fetch a result row as an associative object
	 *
	 * @access public
	 * @return object|false The object of fetched a result row or false
	 * @param  void
	 */
	function fetch () {
		try {
			$r = cubrid_fetch ($this->result, CUBRID_OBJECT | CUBIRD_LOB);

			if ( $r === null )
				$r = false;

			return $r;
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (array) EDB_CUBRID::fetch_all ($free = true)
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

	// {{{ (bool) EDB_CUBRID::free_result (void)
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

			#return cubrid_free_result ($this->result);
			return cubrid_close_request ($this->result);
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (string) EDB_CUBRID::field_name ($index)
	/**
	 * Return the name of the specified field index
	 *
	 * @access public
	 * @return string|false
	 * @param  integer The numerical field offset. The field_offset starts
	 *                 at 0. If field_offset does not exist, return false
	 *                 and an error of level E_WARNING is also issued.
	 * @see http://php.net/manual/en/function.cubrid-field-name.php cubrid_field_name()
	 */
	function field_name ($index) {
		try {
			if ( ! is_resource ($this->result) )
				return false;
			return cubrid_field_name ($this->result, $index);
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (string) EDB_CUBRID::field_type ($index)
	/**
	 * Get the type of the specified field in a result
	 *
	 * @access public
	 * @return string|false
	 * @param  integer The numerical field offset. The field_offset starts
	 *                 at 0. If field_offset does not exist, return false
	 *                 and an error of level E_WARNING is also issued.
	 * @see http://php.net/manual/en/function.cubrid-field-type.php cubrid_field_type()
	 */
	function field_type ($index) {
		try {
			if ( ! is_resource ($this->result) )
				return false;

			return cubrid_field_type ($this->result, $index);
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (int) EDB_CUBRID::num_fields (void)
	/**
	 * Return the number of columns in the result set
	 *
	 * @access public
	 * @return integer|false return -1 if SQL sentence is not SELECT.
	 * @see http://php.net/manual/en/function.cubrid-num-fields.php cubrid_num_fileds()
	 */
	function num_fields () {
		try {
			if ( ! is_resource ($this->result) )
				return false;
			return cubrid_num_cols ($this->result);
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (void) EDB_CUBRID::close (void)
	/**
	 * Close the db handle
	 *
	 * @access public
	 * @return void
	 * @param  void
	 */
	function close () {
		if ( is_resource ($this->db) )
			cubrid_disconnect ($this->db);
	}
	// }}}

	/*
	 * Priavte functions
	 */
	// {{{ private (int) EDB_CUBRID::no_bind_query ($sql)
	/** 
	 * Performs a query on the database
	 *
	 * @access private
	 * @return integer The number of affected rows or false
	 * @param  string  The query strings
	 */
	private function no_bind_query ($sql) {
		try {
			$this->result = cubrid_query ($sql, $this->db);
			if ( cubrid_error_code () ) {
				$this->free = false;
				throw new EDBException (cubrid_error_msg (), E_WARNING);
				return false;
			}
		} catch ( Exception $e ) {
			$this->free = false;
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		$this->switch_freemark ();
	}
	// }}}

	// {{{ private (int) EDB_CUBRID::bind_query ($sql, $parameters)
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

		try {
			$this->result = cubrid_prepare ($this->db, $sql);

			for ( $i=1; $i<$this->pno; $i++ ) {
				switch ($params[0][$i-1]) {
					case 'b' :
						cubrid_lob2_bind (
							$this->result,
							$i,
							is_object ($params[$i]) ? $params[$i]->data : $params[$i],
							'BLOB'
						);
						break;
					case 'c' :
						cubrid_lob2_bind (
							$this->result,
							$i,
							is_object ($params[$i]) ? $params[$i]->data : $params[$i],
							'CLOB'
						);
						break;
					default :
						cubrid_bind ($this->result, $i, $params[$i]);
				}
			}

			cubrid_execute ($this->result);

			$this->switch_freemark ();
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
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
