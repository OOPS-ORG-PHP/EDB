<?php
/**
 * Project: EDB :: Extended DB class
 * File:    edb.php
 *
 * PHP Version 5
 *
 * Copyright (c) 1997-2012 JoungKyun.Kim
 *
 * LICENSE: BSD
 *
 * @category    Database
 * @package     krisp
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   1997-2012 OOPS.org
 * @license     BSD
 * @version     SVN: $Id$
 * @link        http://pear.oops.org/package/EDB
 * @since       File available since release 0.0.1
 */

require_once 'EDB/EDB_Common.php';

/**
 * Base class for EDB API
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
	 * @access public
	 * @return object
	 * @param  string Database host [Example: mysqli://db.host.com]
	 *                The current Supported db type is mysqli
	 * @param  string Database user
	 * @param  string Database password
	 * @param  string Database name
	 */
	function __construct () {
		$argc = func_num_args ();
		$argv = func_get_args ();

		if ( preg_match ('!^([^:]+)://!', $argv[0], $matches) ) {
			$dbtype = 'EDB_' . strtoupper ($matches[1]);
			if ( ! file_exists ("EDB/{$dbtype}.php") ) {
				throw new EDBException ('Unsupported DB Engine');
				return;
			}
		} else
			$dbtype = 'EDB_MYSQLI';

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
	 * @param  void
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
	 * @param  string charset name that is supported database
	 */
	function set_charset ($char) {
		return $this->db->set_charset ($char);
	}
	// }}}

	// {{{ (int) EDB::query ($query, $param_type, $param1, $param2 ...)
	/**
	 * Performs a query on the database
	 *
	 * @access public
	 * @return integer The number of affected rows of false
	 * @param  string  The query strings
	 * @param  string  (optional) Bind parameter type
	 * @param  mixed   (optional) Bind parameter 1
	 * @param  mixed   (optional) Bind parameter 2 ..
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
	 * @param  void
	 */
	function fetch () {
		return $this->db->fetch ();
	}
	// }}}

	// {{{ (void) EDB::fetch_all (void)
	/**
	 * Fetch all result rows as an associative object
	 *
	 * @access public
	 * @return array The fetched result rows
	 * @param  void
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
	 * @param  void
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
