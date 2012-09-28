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
 * @copyright   (c) 2012, JoungKyun.Kim
 * @license     BSD License
 * @version     $Id: EDB_PGSQL.php 32 2012-09-26 18:37:32Z oops $
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
	 * @access public
	 * @return object
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
			throw new EDBException ('pgsql extension is not loaded on PHP!', E_ERROR);

		$o = (object) array (
			'host' => preg_replace ('!^pgsql://!', '', $argv[0]),
			'user' => $argv[1],
			'pass' => $argv[2],
			'db'   => $argv[3],
			'options' => $argv[4]
		);

		foreach ( $o as $key => $val ) {
			if ( $key == 'host' ) {
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
			$this->db = pg_connect ($cstring, PGSQL_CONNECT_FORCE_NEW);
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
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
		return pg_client_encoding ($this->db);
	}
	// }}}

	// {{{ (bool) EDB_PGSQL::set_charset ($charset)
	/** 
	 * Set character set of current database
	 *
	 * @access public
	 * @return bool    The name of character set that is supported on database
	 * @param  string  name of character set that supported from database
	 */
	function set_charset ($char) {
		$string = "Unsupported method on MySQL engine.\n" .
			"If you want to set your charset, use 5th parameter ".
			"'options' of EDB::connect()";

		throw new EDBException ($string, E_ERROR);
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

		$sql = array_shift ($argv);
		$this->pno = $this->get_param_number ($sql);

		if ( $this->free )
			$this->free_result ();

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

		return pg_num_rows ($this->result);
	}
	// }}}

	// {{{ (void) EDB_PGSQL::seek ($offset)
	/**
	 * Adjusts the result pointer to an arbitrary row in the result
	 *
	 * @access public
	 * @return void
	 * @param  integer Must be between zero and the total number of rows minus one
	 */
	function seek ($offset) {
		if ( is_resource ($this->result) )
			pg_result_seek ($this->result, $offset);
	}
	// }}}

	// {{{ (object) EDB_PGSQL::fetch (void)
	/**
	 * Fetch a result row as an associative object
	 *
	 * @access public
	 * @return object The object of fetched a result row or false
	 * @param  void
	 */
	function fetch () {
		if ( ! is_resource ($this->result ) )
			return false;

		$r = pg_fetch_object ($this->result);
		return is_object ($r) ? $r : false;
	}
	// }}}

	// {{{ (array) EDB_PGSQL::fetch_all (void)
	/**
	 * Fetch all result rows as an associative object
	 *
	 * @access public
	 * @return array The fetched result rows
	 * @param  void
	 */
	function fetch_all () {
		$r = array ();
		while ( ($row = $this->fetch ()) !== false )
			$r[] = $row; 

		return $r;
	}
	// }}}

	// {{{ (void) EDB_PGSQL::free_result (void)
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
			if ( is_resource ($this->result) )
				pg_free_result ($this->result);
		} catch ( Exception $e ) {
			throw new EDBException ($e->getMessage (), $e->getCode(), $e);
			return false;
		}

		$this->switch_freemark ();
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
		if ( is_resource ($this->db) )
			pg_close ($this->db);
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
			$this->result = $this->db->query ($sql);
			$this->result = pg_query ($this->db, $sql);
		} catch ( Exception $e ) {
			$this->free = false;
			throw new EDBException ($e->getMessage (), $e->getCode (), $e);
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
			throw new EDBExeption (
				'Number of elements in query doesn\'t match number of bind variables',
				E_WARNING
			);
			return false;
		}

		array_shift ($params);
		$this->result = pg_query_params ($this->db, $sql, $params);
		if ( ! is_resource ($this->result) ) {
			$this->free = false;
			throw new EDBExeption (pg_last_error ($this->db), E_WARNING);
			return false;
		}

		$this->switch_freemark ();
		return true;
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
