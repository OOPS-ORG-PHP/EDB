<?php
/**
 * Project: EDB :: Extended DB class
 * File:    edb.php
 *
 * This class is support various db abstraction layer.
 *
 * @category    Database
 * @package     EDB
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 1997-2012 OOPS.org
 * @license     BSD License
 * @version     SVN: $Id$
 * @link        http://pear.oops.org/package/EDB
 * @since       File available since release 0.0.1
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
 * Base class for EDB API
 *
 * @package     EDB
 */
Class EDB
{
	// {{{ prpperties
	/**
	 * DB handler of EDB class
	 * @access private
	 * @var    object
	 */
	private $db;
	// }}}

	// {{{ (void) EDB::__construct (void)
	/**
	 * Initialize EDB class
	 *
	 * The supported db abstraction layer is mysqli and sqlite3.
	 *
	 * The examples:
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
	 * @param  string $host     Database host
	 * @param  string $...
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
		 * import Abstract DB array class
		 */
		require_once 'EDB/' . $dbtype . '.php';
		$this->db = new $dbtype ($argv);
	}
	// }}}

	// {{{ (array) EDB::get_charset (void)
	/**
	 * Get current db charset.
	 *
	 * This function is under control database type.
	 *
	 * @access public
	 * @return string The name of current charset
	 */
	function get_charset () {
		return $this->db->get_charset ();
	}
	// }}}

	// {{{ (bool) EDB::set_charset ($charset)
	/**
	 * Set database charset.
	 *
	 * This function is under control database type.
	 *
	 * @access public
	 * @return bool
	 * @param  string $char charset name that is supported database
	 */
	function set_charset ($char = 'utf8') {
		return $this->db->set_charset ($char);
	}
	// }}}

	// {{{ (int) EDB::query ($query, $param_type, $param1, $param2 ...)
	/**
	 * Performs a query on the database
	 *
	 * @access public
	 * @return integer The number of affected rows of false
	 * @param  string  $query  The query strings
	 * @param  string  $type   (optional) Bind parameter type
	 * @param  mixed   $param1 (optional) Bind parameter 1
	 * @param  mixed   $param2,... (optional) Bind parameter 2
	 */
	function query () {
		$r = $this->db->query (func_get_args ());
		return $r;
	}
	// }}}

	// {{{ (object) EDB::fetch (void)
	/**
	 * Fetch a result row as an associative object
	 *
	 * @access public
	 * @return object The object of fetched a result row or false
	 */
	function fetch () {
		return $this->db->fetch ();
	}
	// }}}

	// {{{ arrayvoid) EDB::fetch_all (void)
	/**
	 * Fetch all result rows as an associative object
	 *
	 * @access public
	 * @return array The fetched result rows
	 */
	function fetch_all () {
		return $this->db->fetch_all ();
	}
	// }}}

	// {{{ (void) EDB::free_result (void)
	/**
	 * Frees stored result memory for the given statement handle
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
