<?php
/**
 * Project: EDB_PGSQL :: PostgreSQL abstraction layer<br>
 * File:    EDB/EDB_PGSQL.php
 *
 * EDB_PGSQL class는 EDB 패키지가 PosgreSQL을 처리하기 위한
 * 추상 계층을 제공한다.
 *
 * @category    Database
 * @package     EDB
 * @subpackage  EDB_ABSTRACT
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 2015, JoungKyun.Kim
 * @license     BSD License
 * @version     $Id$
 * @link        http://pear.oops.org/package/EDB
 * @filesource
 */

/**
 * PosgreSQL engine for EDB API
 *
 * PostgreSQL 엔진을 위한 DB 추상 계층을 제공
 *
 * @package     EDB
 */
Class EDB_PGSQL extends EDB_Common {
	// {{{ properties
	/**#@+
	 * @access private
	 */
	/**
	 * db handler of EDB_PGSQL class
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
	/**
	 * Bytea information
	 * @var    array
	 */
	private $lob = array ();
	/**#@-*/
	// }}}

	// {{{ (object) EDB_PGSQL::__construct ($host, $user, $pass, $db)
	/** 
	 * EDB_PGSQL 객체를 인스턴스화 하고 PostgreSQL 데이터베이스를
	 * 연결한다.
	 *
	 * For examples:
	 * <code>
	 * $db = new EDB_PGSQL ('pgsql://localhost', 'user', 'host', 'database');
	 * $db = new EDB_PGSQL ('pgsql://localhost:3306', 'user', 'host', 'database');
	 * $db = new EDB_PGSQL (
	 *           'pgsql:///var/run/postgresql',
	 *           'user', 'host', 'database'
	 * );
	 * $db = new EDB_PGSQL (
	 *           'pgsql:///var/run/postgresql',
	 *           'user', 'host', 'database', 'options'
	 * );
	 * </code>
	 *
	 * options parameter는 다음의 객체로 지정한다.
	 *
	 * <code>
	 * $o = (object) array (
	 *     'connect_timeout' => 2,
	 *     'options' => '--client_encoding=UTF8',
	 *     'sslmode' => 'prefer',
	 *     'requiressl' => 0,
	 *     'service' => ''
	 * }
	 * </code>
	 *
	 * options 객체 멤버에 대해서는
	 * {@link http://www.postgresql.org/docs/8.3/static/libpq-connect.html}를
	 * 참조하도록 한다.
	 *
	 * 만약 persistent connection을 사용하고 싶다면 host 앞에 'p~' prefix를
	 * 붙이면 된다.
	 *
	 * For Examples:
	 * <code>
	 * $db = new EDB_PGSQL ('pgsql://p~localhost', 'user', 'host', 'database');
	 * </code>
	 *
	 * @access public
	 * @return EDB_PGSQL
	 * @param  string  $hostname pgsql host[:port] 또는 unix socket 경로
	 * @param  string  $user     pgsql DB 계정
	 * @param  string  $password pgsql DB 암호
	 * @param  string  $database pgsql DB 이름
	 * @param  object  $options  pgsql 옵션
	 */
	function __construct () {
		$_argv = func_get_args ();
		$argv = is_array ($_argv[0]) ? $_argv[0] : $_argv;;

		if ( ! extension_loaded ('pgsql') )
			throw new myException ('pgsql extension is not loaded on PHP!', E_USER_ERROR);

		$o = (object) array (
			'host' => preg_replace ('!^pgsql://!', '', $argv[0]),
			'user' => $argv[1],
			'pass' => $argv[2],
			'dbname'   => $argv[3],
			'options' => $argv[4]
		);

		foreach ( $o as $key => $val ) {
			if ( $key == 'host' ) {
				if ( preg_match ('/^p~/', $val) ) {
					$func = 'pg_pconnect';
					$val = preg_replace ('/^p~/', '', $val);
				} else
					$func = 'pg_connect';

				// 파일이 존재하면 host를 unix socket으로 지정
				if ( file_exists ($val) ) {
					$cstring = sprintf ('host=%s', $val);
					continue;
				}

				if ( preg_match ('/([^:]+):(.*)/', $val, $m) ) {
					$port = is_numeric ($m[2]) ? $m[2] : 5432;
					$cstring = sprintf ('hostaddr=%s port=%s', gethostbyname ($m[1]), $port);
				} else
					$cstring = sprintf ('hostaddr=%s port=5432', gethostbyname ($val));
			}

			if ( $key == 'options' && is_object ($val) ) {
				foreach ( $val as $k => $v ) {
					if ( trim ($v) )
						$cstring .= sprintf (' %s=%s', $k, trim ($v));
				}
			}

			if ( trim ($val) )
				$cstring .= sprintf (' %s=%s', $key, trim ($val));
		}

		try {
			$this->db = $func ($cstring, PGSQL_CONNECT_FORCE_NEW);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
		}
	}
	// }}}

	// {{{ (string) EDB_PGSQL::get_charset (void)
	/** 
	 * Get character set of current database
	 *
	 * @access public
	 * @return string Current character set name on DB
	 */
	function get_charset () {
		try {
			return pg_client_encoding ($this->db);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (bool) EDB_PGSQL::set_charset ($charset)
	/** 
	 * Set character set of current database
	 *
	 * Postgresql don't support set characterset and always returns true
	 *
	 * @access public
	 * @return bool    always returns true
	 * @param  string  name of character set that supported from database
	 */
	function set_charset ($char) {
		$r = pg_set_client_encoding ($this->db, $char);
		return $r ? false : true;
	}
	// }}}

	// {{{ (string) EDB_PGSQL::escape ($string)
	/** 
	 * Escape special characters in a string for use in an SQL statement
	 *
	 * Attention! This method always returns original string.
	 *
	 * @access public
	 * @return string
	 * @param  string  The string that is to be escaped.
	 */
	function escape ($buf, $type = 's') {
		switch ($type) {
			case 'b' :
				return base64_encode ($buf);
				#return pg_escape_bytea ($buf);
			case 'i' :
				return pg_escape_identifier ($buf);
			case 'u' :
				/*
				 * bind query시에 ::bytea가 먹지를 않는다 --;
				 * 그리고 base64가 용량이 가장 작다
				if ( preg_match ('/::bytea$/', $buf) ) {
					$buf = preg_replace (
						array ('/\'\'/', '/::bytea/'),
						array ('\'', ''),
						$buf
					);
					return pg_unescape_bytea (stripslashes ($buf));
				}
				return pg_unescape_bytea ($buf);
				 */
				return base64_decode ($buf);
			default:
				/*
				$pgver = pg_version ($this->db);
				if ( version_compare ('8.3', $pgver['client'], '<') )
					return pg_escape_literal ($buf);
				else
				 */
				return pg_escape_string ($buf);
		}
	}
	// }}}

	// {{{ (int) EDB_PGSQL::query ($query, $param_type, $param1, $param2 ...)
	/** 
	 * Performs a query on the database
	 *
	 * @access public
	 * @return integer|false The number of affected rows
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

		try {
			$sql = array_shift ($argv);
			$this->pno = count ($argv) ? $this->get_param_number ($sql, 'pgsql') : 0;

			if ( $this->free )
				$this->free_result ();

			// store query in log variable
			$this->queryLog[] = $sql;

			if ( $this->pno++ == 0 )
				$r = $this->no_bind_query ($sql);
			else
				$r = $this->bind_query ($sql, $argv);

			if ( $r === false )
				return false;

			if ( preg_match ('/^(update|insert|delete|replace)/i', trim ($sql)) ) {
				/* Insert or update, or delete query */
				return pg_affected_rows ($this->result);
			} else if ( preg_match ('/create|drop/i', trim ($sql)) ) {
				return 1;
			}

			# Only select
			if ( preg_match ('/^select/i', trim ($sql)) ) {
				$fno = $this->num_fields ();
				for ( $i=0; $i<$fno; $i++ ) {
					$type = $this->field_type ($i);
					if ( $type == 'bytea' )
						$lob .= ':' . $this->field_name ($i);
				}

				$lob = substr ($lob, 1);
				if ( preg_match ('/:/', $lob) )
					$this->lob = preg_split ('/:/', $lob);
				else
					$this->lob = array ($lob);
			}

			return pg_num_rows ($this->result);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (string) EDB_PGSQL::lastId (void)
	/**
	 * 가장 마지막 입력 row의 OID를 반환한다.
	 *
	 * @since  2.0.4
	 * @access public
	 * @return string|false
	 */
	function lastId () {
		return pg_last_oid ($this->db);
	}
	// }}}

	// {{{ (bool) EDB_PGSQL::seek ($offset)
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
			return pg_result_seek ($this->result, $offset);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (object) EDB_PGSQL::fetch (void)
	/**
	 * Fetch a result row as an associative object
	 *
	 * @access public
	 * @return object The object of fetched a result row or false
	 * @param  boolean (optional) fetch 수행 후 result를 free한다.
	 *                 (기본값: false) EDB >= 2.0.3
	 */
	function fetch ($free = false) {
		if ( ! is_resource ($this->result ) )
			return false;

		try {
			$r = pg_fetch_object ($this->result);
			if ( ! is_object ($r) )
				return false;

			foreach ( $this->lob as $keyname )
				$r->$keyname = $this->escape ($r->$keyname, 'u');

			if ( $free )
				$this->free_result ();

			return $r;
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (array) EDB_PGSQL::fetch_all ($free = true)
	/**
	 * Fetch all result rows as an associative object
	 *
	 * @access public
	 * @return array The fetched result rows
	 * @param  boolean (optional) free result set after fetch.
	 *                 Defaluts is true.
	 */
	function fetch_all ($free = true) {
		$r = array ();
		while ( ($row = $this->fetch ()) !== false )
			$r[] = $row; 

		if ( $free )
			$this->free_result ();

		return $r;
	}
	// }}}

	// {{{ (bool) EDB_PGSQL::free_result (void)
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
		$this->lob = array ();

		try {
			if ( ! is_resource ($this->result) )
				return true;

			return pg_free_result ($this->result);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (string) EDB_PGSQL::field_name ($index)
	/**
	 * Get the name of the specified field in a result
	 *
	 * @access public
	 * @return string|false
	 * @param  integer Field number, starting from 0.
	 * @see http://php.net/manual/en/function.pg-field-name.php pg_field_name()
	 */
	function field_name ($index) {
		try {
			if ( ! is_resource ($this->result) )
				return false;

			return pg_field_name ($this->result, $index);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		return false;
	}
	// }}}

	// {{{ (string) EDB_PGSQL::field_type ($index)
	/**
	 * Returns the type name for the corresponding field number
	 *
	 * returns a string containing the base type name of the given
	 * field_number in the given PostgreSQL result resource.
	 *
	 * @access public
	 * @return string|false
	 * @param  integer Field number, starting from 0.
	 */
	function field_type ($index) {
		try {
			if ( ! is_resource ($this->result) )
				return false;

			return pg_field_type ($this->result, $index);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}
	}
	// }}}

	// {{{ (int) EDB_PGSQL::num_fields (void)
	/**
	 * Returns the number of fields in a result
	 *
	 * @access public
	 * @return integer|false
	 * @see http://php.net/manual/en/function.pg-num-fields.php pg_num_fields()
	 */
	function num_fields () {
		try {
			if ( ! is_resource ($this->result) )
				return false;
			$r = pg_num_fields ($this->result);
		} catch ( Exception $e ) {
			throw new myException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		return ($r != -1) ? $r : false;
	}
	// }}}

	// {{{ (void) EDB_PGSQL::trstart (void)
	/**
	 * DB transaction 을 시작한다.
	 *
	 * @access public
	 * @return void
	 */
	function trstart () {
		$this->db->query ('BEGIN');
	}
	// }}}

	// {{{ (void) EDB_PGSQL::trend ($v)
	/**
	 * DB transaction 을 종료한다.
	 *
	 * @access public
	 * @return void
	 * @param bool false일경우 rollback을 수행한다.
	 */
	function trend ($v = true) {
		$sql = ($v === false) ? 'ROLLBACK' : 'COMMIT';
		$this->db->query ($sql);
	}
	// }}}

	// {{{ (void) EDB_PGSQL::close (void)
	/**
	 * Close the db handle
	 *
	 * @access public
	 * @return void
	 * @param  void
	 */
	function close () {
		if ( is_resource ($this->db) ) {
			pg_close ($this->db);
			unset ($this->db);
		}
	}
	// }}}

	/*
	 * Priavte functions
	 */
	// {{{ private (bool) EDB_PGSQL::no_bind_query ($sql)
	/** 
	 * Performs a query on the database
	 *
	 * @access private
	 * @return boolean
	 * @param  string  The query strings
	 */
	private function no_bind_query ($sql) {
		try {
			if ( ($this->result = pg_query ($this->db, $sql)) === false ) {
				$this->free = false;
				throw new myException (pg_last_error ($this->db), E_USER_WARNING);
				return false;
			}
		} catch ( Exception $e ) {
			$this->free = false;
			throw new myException ($e->getMessage (), $e->getCode (), $e);
			return false;
		}

		$this->switch_freemark ();
		return true;
	}
	// }}}

	// {{{ private (bool) EDB_PGSQL::bind_query ($sql, $parameters)
	/** 
	 * Performs a bind query on the database
	 *
	 * @access private
	 * @return boolean
	 * @param  string  The query strings
	 * @param  array   (optional) Bind parameter type
	 */
	private function bind_query ($sql, $params) {
		if ( isset ($param) )
			unset ($param);

		if ( $this->pno != count ($params) || $this->check_param ($params) === false ) {
			$this->free = false;
			throw new myExeption (
				'Number of elements in query doesn\'t match number of bind variables',
				E_USER_WARNING
			);
			return false;
		}

		try {
			$type = array_shift ($params);
			foreach ($params as $key => $v) {
				if ( is_object ($v) )
					$params[$key] = $v->data;

				if ( $type[$key] == 'b' || $type[$key] == 'c' )
					$params[$key] = $this->escape ($params[$key], 'b');
			}

			$this->result = pg_query_params ($this->db, $sql, $params);
			if ( ! is_resource ($this->result) ) {
				$this->free = false;
				throw new myExeption (pg_last_error ($this->db), E_USER_WARNING);
				return false;
			}
		} catch ( Exception $e ) {
			$this->free = false;
			throw new myException ($e->getMessage (), $e->getCode (), $e);
			return false;
		}

		$this->switch_freemark ();
		return true;
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
