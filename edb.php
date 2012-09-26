<?php
/**
 * Project: EDB :: 확장 DB 추상화 layer<br>
 * File:    edb.php
 *
 * 이 패키지는 mysqli, sqlite3의 DB 추상화 계층을 제공한다.
 *
 * @category    Database
 * @package     EDB
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 1997-2012 OOPS.org
 * @license     BSD License
 * @version     SVN: $Id$
 * @link        http://pear.oops.org/package/EDB
 * @since       File available since release 0.0.1
 * @example	    pear_EDB/tests/mysqli.php Sample code of mysqli abstraction layer
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
 * 이 패키지는 mysqli, sqlite3의 DB 추상화 계층을 제공한다.
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
	 * 지원되는 추상화 계층으로는 mysqli와 sqlite3을 지원한다.
	 *
	 * 예제:
	 *
	 * <code>
	 * # mysqli
	 * $db = new EDB ('mysqli://localhost', 'username', 'password', 'database');
	 *
	 * # sqlite3
	 * $db = new EDB ('sqlite3:///file/path', $flag);
	 * </code>
	 *
	 * @see EDB_MYSQLI::__construct()
	 * @see EDB_SQLITE3::__construct()
	 * @access public
	 * @return object
	 * @param  string $host,... 데이터베이스 호스트
	 */
	function __construct () {
		$argc = func_num_args ();
		$argv = func_get_args ();

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
	 * sqlite는 지원하지 않는다.
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
	 * 이 method는 실 DB의 영향을 받는다. sqlite는
	 * 지원하지 않는다.
	 *
	 * @access public
	 * @return bool
	 * @param  string DB가 지원하는 문자셋 이름
	 */
	function set_charset ($char = 'utf8') {
		return $this->db->set_charset ($char);
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
	 * @param  mixed   $param1 (optional) 첫번째 bind 파라미터 값
	 * @param  mixed   $param2,... (optional) 두번째 bind 파라미터 값
	 */
	function query () {
		$r = $this->db->query (func_get_args ());
		return $r;
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

	// {{{ array) EDB::fetch_all (void)
	/**
	 * associative 개체로 모든 result row를 가져온다.
	 *
	 * @access public
	 * @return array 가져온 result row 배열 반환
	 */
	function fetch_all () {
		return $this->db->fetch_all ();
	}
	// }}}

	// {{{ (void) EDB::free_result (void)
	/**
	 * 주어진 문장 핸들에 대하여 메모리에 저장된 결과를 해제
	 *
	 * @access public
	 * @return void
	 */
	function free_result () {
		$this->db->free_result();
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
