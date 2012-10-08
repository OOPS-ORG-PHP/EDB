<?php
/**
 * Project: EDB :: 확장 DB 추상화 layer<br>
 * File:    edb.php
 *
 * 이 패키지는 다음의 DB 추상화 계층을 제공한다.
 *
 * - CUBRID
 * - MSSQL (Not yet testing)
 * - MYSQL
 * - MYSQLi
 * - PostgreSQL (Not yet testing)
 * - SQLite2
 * - SQLite3
 * - SQLRelay (Not yet testing)
 *
 *
 * @category    Database
 * @package     EDB
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 1997-2012 OOPS.org
 * @license     BSD License
 * @version     SVN: $Id$
 * @link        http://pear.oops.org/package/EDB
 * @since       File available since release 0.0.1
 * @example	    pear_EDB/tests/cubrid.php Sample code of cubrid abstraction layer
 * @example	    pear_EDB/tests/mysql.php Sample code of mysql abstraction layer
 * @example	    pear_EDB/tests/mysqli.php Sample code of mysqli abstraction layer
 * @example	    pear_EDB/tests/pgsql.php Sample code of postgresql abstraction layer
 * @example	    pear_EDB/tests/sqlite2.php Sample code of sqlite2 abstraction layer
 * @example	    pear_EDB/tests/sqlite3.php Sample code of sqlite3 abstraction layer
 * @filesource
 */

/**
 * import EDBException class
 */
require_once 'EDB/EDB_Exception.php';
/**
 * import EDB_Common class
 */
require_once 'EDB/EDB_Common.php';

/**
 * 확장 DB 추상화 layer
 *
 * 이 패키지는 다음의 DB 추상화 계층을 제공한다.
 *
 * - CUBRID
 * - MSSQL
 * - MYSQL
 * - MYSQLi
 * - PostgreSQL (Not yet testing)
 * - SQLite2
 * - SQLite3
 * - SQLRelay
 *
 * @package     EDB
 */
Class EDB
{
	// {{{ prpperties
	/**
	 * EDB class의 DB 핸들러
	 * @access private
	 * @var    object
	 */
	private $db;
	// }}}

	// {{{ (void) EDB::__construct (void)
	/**
	 * EDB 클래스 초기화
	 *
	 * 지원되는 추상화 계층으로는 cubrid, mysql, mysqli, pgsql,
	 * sqlite2, sqlite3를 지원한다.
	 *
	 * @see EDB_CUBRID::__construct()
	 * @see EDB_MSSQL::__construct()
	 * @see EDB_PGSQL::__construct()
	 * @see EDB_MYSQL::__construct()
	 * @see EDB_MYSQLI::__construct()
	 * @see EDB_SQLITE2::__construct()
	 * @see EDB_SQLITE3::__construct()
	 * @access public
	 * @return object
	 * @param  string $host,... 데이터베이스 호스트
	 */
	function __construct () {
		$argc = func_num_args ();
		$argv = func_get_args ();

		$iniset = function_exists ('___ini_set') ? '___ini_set' : 'ini_set';
		$iniset ('magic_quotes_gpc', false);

		if ( preg_match ('!^([^:]+)://!', $argv[0], $matches) ) {
			$dbtype = 'EDB_' . strtoupper ($matches[1]);
			if ( ! EDB_Common::file_exists ("EDB/{$dbtype}.php") ) {
				throw new EDBException ('Unsupported DB Engine');
				return;
			}
		} else
			$dbtype = 'EDB_MYSQLI';

		/**
		 * DB 추상화 계층 class를 로드
		 */
		require_once 'EDB/' . $dbtype . '.php';
		$this->db = new $dbtype ($argv);
	}
	// }}}

	// {{{ (string) EDB::get_charset (void)
	/**
	 * Get current db charset.
	 * 현재의 db 문자셋을 가져온다.
	 *
	 * 이 method는 실 DBMS에 의하여 제약이 있다.
	 * mysql, sqlite3는 <b>Unsupport</b>를 반환한다.
	 *
	 * @access public
	 * @return string 현재 문자셋 이름 반환
	 */
	function get_charset () {
		return $this->db->get_charset ();
	}
	// }}}

	// {{{ (bool) EDB::set_charset ($charset)
	/**
	 * Set database charset.
	 * DB 문자셋을 설정/변경 한다.
	 *
	 * 이 method는 실 DB의 영향을 받는다.
	 *
	 * mysql과 mysqli만 지원을 하며, 나머지는 모두 아무런 동작 없이
	 * 항상 true만 반환한다.
	 *
	 * @access public
	 * @return bool
	 * @param  string DB가 지원하는 문자셋 이름
	 */
	function set_charset ($char = 'utf8') {
		return $this->db->set_charset ($char);
	}
	// }}}

	// {{{ (string) EDB::escape ($string)
	/**
	 * SQL 문에 사용이 되어지는 특수 문자를 에스케이프 시킨다.
	 *
	 * DB API에서 지원을 할 경우 해당 API의 escape api를 사용하며, 지원하지
	 * 않을 경우, addslashes를 사용한다.
	 *
	 * SQLRELAY와 PGSQL의 경우 원 문자열을 그대로 반환한다. SQL RELAY의 경우
	 * 해당 DB의 escape function을 이용해야 하며, PGSQL의 경우 PGSQL에서 지원
	 * 하는 data type의 escape function을 사용하도록 한다.
	 *
	 * 권고사항!
	 * 이 함수를 사용해야 하는 경우라면, 이 함수를 사용하는것 보다 bind query
	 * 를 사용하는 것을 권장한다!
	 *
	 * @access public
	 * @return string
	 * @param  string The string to be escaped.
	 */
	function escape ($string) {
		return $this->db->escape ($string);
	}
	// }}}

	// {{{ (int) EDB::query ($query, $param_type, $param1, $param2 ...)
	/**
	 * 데이터베이스상에서 쿼리를 실행한다.
	 *
	 * @access public
	 * @return integer|false   실제 적용된 row 수
	 * @param  string  $query  실행할 쿼리
	 * @param  string  $type   (optional) bind 파라미터 형식
	 *
	 *         bind parameter 형식은 각 DB 엔진의 영향을 받지 않으며 EDB
	 *         패키지가 직접 테스트를 하고, engine으로 전달이 되지는 않는다.
	 *
	 *         단, blob과 clob의 경우 해당 DB API에서 관련 API를 제공을 할
	 *         경우에는 해댱 API를 이용 하게 된다.
	 *
	 *         EDB 패키지에서 지원하는 형식은 다음과 같다.
	 *
	 *         - <b>b</b> blob
	 *         - <b>c</b> clob
	 *         - <b>i</b> integer
	 *         - <b>f</b> float, double
	 *         - <b>n</b> null
	 *         - <b>s</b> string
	 *          - 검사를 하지 않고 bypass.
	 *          - bcifn 외의 형식은 모두 s로 지정을 하면 무난하게 통과된다.
	 *
	 *         blob와 clob type으로 지정했을 경우 bind parameter 값은 data
	 *         멤버와 len 멤버를 가진 object로 주어져야 한다.
	 *
	 *         <code>
	 *         $param = (object) array ('data' => 'value', 'len' => 'value');
	 *         </code>
	 *
	 *         또한, blob 데이터 insert/update 시에 bind query를 사용하면
	 *         binary data를 escape 할 필요가 없다.
	 *
	 * @param  mixed   $param1 (optional) 첫번째 bind 파라미터 값
	 * @param  mixed   $param2,... (optional) 두번째 bind 파라미터 값
	 */
	function query () {
		$r = $this->db->query (func_get_args ());
		return $r;
	}
	// }}}

	// {{{ (bool) EDB::seek ($offset)
	/**
	 * result row 포인터를 이동한다.
	 *
	 * sqlite/sqlite3의 경우 무조건 처음으로 돌아가서 원하는 offset까지
	 * 이동을 하므로 속도 저하가 발생할 수 있다.
	 *
	 * offset이 row num보다 클경우에는 마지막 row로 이동을 한다.
	 *
	 * @access public
	 * @return boolean
	 * @param  integer 0부터 <b>전체 반환 row수 - 1</b> 까지의 범위
	 */
	function seek ($offset) {
		return $this->db->seek ($offset);
	}
	// }}}

	// {{{ (object) EDB::fetch (void)
	/**
	 * associative 개체로 result row를 가져온다.
	 *
	 * @access public
	 * @return object|false result row로 가져온 object
	 */
	function fetch () {
		return $this->db->fetch ();
	}
	// }}}

	// {{{ (array) EDB::fetch_all ($free = true)
	/**
	 * 모든 result row를 associative object 배열로 반환한다.
	 *
	 * fetch_all method는 fetch를 수행후 result rows를 free한다.
	 * 만약 free하고 싶지 않다면, 첫번째 aurgument값으로 false를
	 * 지정하도록 한다.
	 *
	 * @access public
	 * @return array  associative object 배열
	 * @param  boolean (optional) fetch 수행후 result를 free 한다.
	 *                 (기본값: true)
	 */
	function fetch_all ($free = true) {
		return $this->db->fetch_all ($free);
	}
	// }}}

	// {{{ (string) EDB::field_name ($index)
	/**
	 * 지정한 n번째의 컬럼 이름을 반환한다.
	 *
	 * @access public
	 * @return string
	 * @param  integer 0부터 시작하는 컬럼 인덱스 번호
	 */
	function field_name ($index) {
		return $this->db->field_name ($index);
	}
	// }}}

	// {{{ (string) EDB::field_type ($field_index[, $table = '')
	/**
	 * 지정한 n번째의 컬럼 유형을 반환한다.
	 *
	 * sqlite3의 경우 null을 제외하고는 빈 값을 반환하므로,
	 * EDB package에서는 <b>unknown, maybe libsqlite3 bug?</b>로
	 * 반환한다.
	 *
	 * @access public
	 * @return string  db engine에 따라 자료형이 다르다.
	 * @param  integer 0부터 시작하는 컬럼 인덱스 번호
	 * @param  string  (optional) table 이름. sqlite2 engine에서만
	 *                 지정한다.
	 */
	function field_type ($index, $table = '') {
		return $this->db->field_type ($index, $table);
	}
	// }}}

	// {{{ (int) EDB::num_fields (void)
	/**
	 * 해당 row의 field 수를 반환한다.
	 *
	 * @access public
	 * @return integer
	 */
	function num_fields () {
		return $this->db->num_fields ();
	}
	// }}}

	// {{{ (bool) EDB::free_result (void)
	/**
	 * 주어진 문장 핸들에 대하여 메모리에 저장된 결과를 해제
	 *
	 * @access public
	 * @return boolean sqlite, sqlite3, mysqli는 항상 true를 반환한다.
	 */
	function free_result () {
		return $this->db->free_result();
	}
	// }}}

	// {{{ (void) EDB::close (void)
	/**
	 * DB 핸들을 종료한다.
	 *
	 * 기본적으로 EDB는 페이지가 종료될 때 각 DB class의 __destruct에서
	 * close를 하기 때문에 따로 호출을 할 필요가 없다.
	 *
	 * 크드 중간에서 close를 명시적으로 해야할 필요가 있을 경우에 사용을
	 * 하면 된디.
	 *
	 * @access public
	 * @return void
	 */
	function close () {
		try {
			$this->db->free_result ();
			$this->db->close ();
		} catch ( Exception $e ) { }
	}
	// }}}
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
