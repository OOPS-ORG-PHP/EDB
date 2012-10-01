<?php
/**
 * Project: EDB_SQLRELAY :: SQLRELAY abstraction layer
 * File:    EDB/EDB_SQLRELAY.php
 *
 * The EDB_SQLRELAY class is SQLRelay abstraction layer that used internally
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
 * SQLRELAY engine for EDB API
 *
 * This class support abstracttion DB layer for SQLRELAY Engine
 *
 * @package     EDB
 */
Class EDB_SQLRELAY extends EDB_Common {
	// {{{ properties
	/**#@+
	 * @access private
	 */
	/**
	 * db handler of EDB_SQLRELAY class
	 * @var    object
	 */
	private $db;
	/**
	 * The number of query parameter
	 * @var    integer
	 */
	private $pno = 0;
	/**
	 * The current offset of result rows
	 * @var    integer
	 */
	private $rowid;
	/**
	 * The last rownums of result rows
	 * @var    integer
	 */
	private $rownum;
	/**#@-*/
	// }}}

	// {{{ (object) EDB_SQLRELAY::__construct ($host, $user, $pass)
	/** 
	 * Instantiates an EDB_SQLRELAY object and opens an SQLRELAY database
	 *
	 * For examples:
	 * <code>
	 * $db = new EDB_SQLRELAY ('sqlrelay://localhost', 'user', 'host');
	 * $db = new EDB_SQLRELAY ('sqlrelay://localhost:9000', 'user', 'host');
	 * $db = new EDB_SQLRELAY ('sqlrelay://localhost:/path/sock', 'user', 'host');
	 * </code>
	 *
	 * If you add prefix 'p~' before host, you can connect with persistent
	 * connection.
	 *
	 * For Examples:
	 * <code>
	 * $db = new EDB_SQLRELAY ('sqlrelay://p~localhost', 'user', 'host', 'database');
	 * </code>
	 *
	 * @access public
	 * @return object
	 * @param  string  $hostname SQLRELAY host
	 * @param  string  $user     SQLRELAY user
	 * @param  string  $password SQLRELAY password
	 * @param  string  $database SQLRELAY database
	 */
	function __construct () {
		$_argv = func_get_args ();
		$argv = is_array ($_argv[0]) ? $_argv[0] : $_argv;;

		if ( ! extension_loaded ('sql_relay') )
			throw new EDBException ('SQLRELAY extension is not loaded on PHP!', E_ERROR);

		$o = (object) array (
			'host' => preg_replace ('!^sqlrelay://!', '', $argv[0]),
			'user' => $argv[1],
			'pass' => $argv[2],
		);

		if ( preg_match ('/([^:]+):(.*)/', $o->host, $matches) ) {
			$o->host = $matches[1];
			$o->port = $matches[2];
		} else
			$o->port = 9000;

		if ( ! is_numeric ($o->port) ) {
			$o->sock = $o->port;
			$o->port = 9000;
		} else
			$o->sock = null;

		try {
			$this->db = sqlrcon_alloc ($o->host, $o->port, $o->sock, $o->user, $o->pass, 0, 1);
			$this->result  = sqlrcur_alloc ($this->db);

			if ( ! sqlrcon_ping ($this->db) )
				throw new EDBException (sqlrcur_errorMessage ($this->db), E_ERROR);
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
		}
	}
	// }}}

	// {{{ (string) EDB_SQLRELAY::get_charset (void)
	/** 
	 * Get character set of current database
	 *
	 * SQLRELAY extension don't support this function
	 *
	 * @access public
	 * @return string Current character set name on DB
	 */
	function get_charset () {
		return 'Unsupport';
	}
	// }}}

	// {{{ (bool) EDB_SQLRELAY::set_charset ($charset)
	/** 
	 * Set character set of current database
	 *
	 * This method is always returned true because SQLRELAY don't support
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

	// {{{ (int) EDB_SQLRELAY::query ($query, $param_type, $param1, $param2 ...)
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
	 * </code>
	 * @param  mixed   $param1 (optional) Bind parameter 1
	 * @param  mixed   $param2,... (optional) Bind parameter 2 ..
	 */
	function query () {
		$_argv = func_get_args ();
		$argv = is_array ($_argv[0]) ? $_argv[0] : $_argv;;

		$this->error = null;

		try {
			$sql = array_shift ($argv);
			$this->pno = $this->get_param_number ($sql, 'sqlrelay');

			if ( $this->free )
				$this->free_result ();

			// 얼마나 많은 라인을 받을 것인지.. 0은 무제한
			//sqlrcur_setResultSetBufferSize ($this->result, 0);

			if ( $this->pno++ == 0 ) // no bind query
				$this->no_bind_query ($sql);
			else // bind query
				$this->bind_query ($sql, $argv);

			$this->rowid= 0;

			if ( preg_match ('/^(update|insert|delete|replace)/i', trim ($sql)) ) {
				/* Insert or update, or delete query */
				$this->rownum = sqlrcur_affectedRows ($this->result);
				return $this->rownum;
			} else if ( preg_match ('/create|drop/i', trim ($sql)) ) {
				$this->rownum = 1;
				return $this->rownum;
			}

			$this->rownum = sqlrcur_rowCount ($this->result);
			return $this->rownum;
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (bool) EDB_SQLRELAY::seek ($offset)
	/**
	 * Move the cursor in the result
	 *
	 * @access public
	 * @return boolean
	 * @param  Number of units you want to move the cursor.
	 */
	function seek ($offset) {
		$this->rowid = $offset;
		return 0;
	}
	// }}}

	// {{{ (object) EDB_SQLRELAY::fetch (void)
	/**
	 * Fetch a result row as an associative object
	 *
	 * @access public
	 * @return object|false The object of fetched a result row or false
	 * @param  void
	 */
	function fetch () {
		try {
			$r = sqlrcur_getRowAssoc ($this->result, $this->rowid++);

			return $r ? (object) $r : false;
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (array) EDB_SQLRELAY::fetch_all ($free = true)
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

		$start = $this->rowid;
		for ( $i=$start; $i<$this->rownum; $i++ ) {
			$row[] = $this->fetch ();
		}

		if ( $free )
			$this->free_result ();

		return $row;
	}
	// }}}

	// {{{ (bool) EDB_SQLRELAY::free_result (void)
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

			$this->rowid = 0;

			// until current version (0.46)
			// missing sqlrcur_closeResultSet api on sqlrelay php api.
			// If you wnat to use this api, see also php-sqlrelay package
			// on AnNyung LInux 2.
			if ( function_exists ('sqlrcur_closeResultSet') )
				return sqlrcur_closeResultSet ($this->result);

			unset ($this->result);
			return true;
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (string) EDB_SQLRELAY::field_name ($index)
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
			return sqlrcur_getColumnName ($this->result, $index);
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (string) EDB_SQLRELAY::field_type ($index)
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

			return sqlrcur_getColumnType ($this->result, $index);
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (int) EDB_SQLRELAY::num_fields (void)
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
			return sqlrcur_colCount ($this->result);
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (void) EDB_SQLRELAY::close (void)
	/**
	 * Close the db handle
	 *
	 * @access public
	 * @return void
	 * @param  void
	 */
	function close () {
		if ( is_resource ($this->result) ) {
			sqlrcon_free ($this->result);
			unset ($this->result);
		}

		if ( is_resource ($this->db) ) {
			sqlrcon_free ($this->db);
			unset ($this->db);
		}
	}
	// }}}

	/*
	 * Priavte functions
	 */
	// {{{ private (int) EDB_SQLRELAY::no_bind_query ($sql)
	/** 
	 * Performs a query on the database
	 *
	 * @access private
	 * @return integer The number of affected rows or false
	 * @param  string  The query strings
	 */
	private function no_bind_query ($sql) {
		try {
			if ( ! sqlrcur_sendQuery ($this->result, $sql) ) {
				throw new EDBException (sqlrcur_errorMessage ($this->result), E_WARNING);
				return false;
			}
			sqlrcon_endSession ($this->db);
		} catch ( Exception $e ) {
			$this->free = false;
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		$this->switch_freemark ();
	}
	// }}}

	// {{{ private (int) EDB_SQLRELAY::bind_query ($sql, $parameters)
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
			sqlrcur_prepareQuery ($this->result, $sql);

			for ( $i=1; $i<$this->pno; $i++ ) {
				switch ($params[0][$i-1]) {
					case 'b' :
					case 'c' :
						// is binary safe strlen on blob?
						if ( ! is_object ($params[$i]) ) {
							$buf = $params[$i];
							unset ($params[$i]);

							$params->data = $buf;
							$params->len  = strlen ($buf);
						}

						$func = ( $params[0][$i-1] == 'b') ?
								'sqlrcur_inputBindBlob' : 'sqlrcur_inputBindClob';

						$func (
							$this->result,
							'param' . $i,
							$params[$i]->data,
							$params[$i]->len
						);
						break;
					default :
						sqlrcur_inputBind ($this->result, $i, $params[$i]);
				}
			}

			if ( ! sqlrcur_executeQuery ($this->result) ) {
				sqlrcur_clearBinds ($this->result);
				sqlrcon_endSession ($this->db);
				throw new EDBException (sqlrcur_errorMessage ($this->result), E_WARNING);
				return false;
			}

			sqlrcur_clearBinds ($this->result);
			sqlrcon_endSession ($this->db);

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
