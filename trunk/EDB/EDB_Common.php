<?php
/**
 * Project: EDB_Common :: Common API for EDB class
 * File:    EDB/EDB_Common.php
 *
 * The EDB_Common class is common api that used internally
 * on EDB class.
 *
 * @category    Database
 * @package     EDB
 * @subpackage  EDB_Common
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 2015 JoungKyun.Kim
 * @license     BSD License
 * @version     $Id: EDB_Common.php 4 2012-08-31 19:14:39Z oops $
 * @link        http://pear.oops.org/package/EDB
 * @filesource
 */

/**
 * Common API of EDB
 *
 * @package EDB
 */
Class EDB_Common {
	// {{{ properties
	/**
	 * Store sql query in session
	 * @access public
	 * @var array
	 */
	public $queryLog = null;
	/**
	 * Result marking for free
	 * @access private
	 * @var    boolean
	 */
	protected $free = false;
	/**
	 * DB result handler
	 * @access private
	 * @var    object
	 */
	protected $result;
	// }}}

	// {{{ (bool) EDB_Common:: file_exists (void)
	/**
	 * Checks whether a file or directory exists
	 * 
	 * If don't find file, and re-search include_path
	 *
	 * @access public
	 * @return boolean Returns TRUE if the file or directory specified by filename exists; FALSE otherwise.
	 * @param  string  Path to the file or directory.
	 */
	function file_exists ($file) {
		if ( file_exists ($file) )
			return true;

		try {
			$buf = ini_get ('include_path');
		} catch ( Exception $e ) {
			# for AnNyung LInux
			$buf = ___ini_get ('include_path');
		}

		$path = preg_split ('/:/', $buf);
		array_shift ($path);
		foreach ( $path as $dir ) {
			if ( file_exists ($dir . '/' . $file) )
				return true;
		}

		return false;
	}
	// }}}

	// {{{ (int) EDB_Common:: switch_freemark (void)
	/**
	 * Change free marking
	 *
	 * @access public
	 * @return void
	 * @param  void
	 */
	function switch_freemark () {
		if ( ! $this->free )
			$this->free = true;
		else
			$this->free = false;
	}
	// }}}

	// {{{ (int) EDB_Common:: get_param_number ($sql)
	/**
	 * Get number of query parameters
	 *
	 * @access public
	 * @return integer The number of parameters
	 * @param  string Bind query string
	 */
	function get_param_number (&$sql, $type = '') {
		$sql = preg_replace ('/[\x5c]\?/', '=-=-', $sql);
		$r = strlen (preg_replace ('/[^?]/', '', $sql));

		switch ($type) {
			case 'pgsql' :
				for ( $i=0; $i<$r; $i++ )
					$sql = preg_replace ('/\?/', '\$' . ($i+1), $sql, 1);
				break;
			//case 'sqlrelay' :
			//	for ( $i=0; $i<$r; $i++ )
			//		$sql = preg_replace ('/\?/', ':param' . ($i + 1), $sql, 1);
			//	break;
		}
		$sql = preg_replace ('/=-=-/', '\?', $sql);

		return $r;
	}
	// }}}

	// {{{ (bool) EDB_Common::check_param ($parameters)
	/**
	 * Check parameter type and parameters
	 *
	 * @access public
	 * @return bool
	 * @param  array The parameter of bind query
	 */
	function check_param ($param) {
		if ( ! is_array ($param) )
			return false;

		if ( count ($param) < 2 )
			return false;

		$type = array_shift ($param);
		$len = strlen ($type);
		if ( $len != count ($param) )
			return false;

		for ( $i=0; $i<$len; $i++ ) {
			$no = $i + 1;
			switch ($type[$i]) {
				case 'i' : // integer
					if ( is_numeric ($param[$i]) === false ) {
						throw new myException (
							"The ${no}th parameter type of query is not numeric type",
							E_USER_ERROR
						);
						return false;
					}
					break;
				case 'f' : // float, double
					if ( is_numeric ($param[$i]) !== false && is_float ($param[$i]) !== false ) {
						throw new myException (
							"The ${no}th parameter type of query is not double type",
							E_USER_ERROR
						);
						return false;
					}
					break;
				case 'n' : // null
					if ( $param[$i] ) {
						throw new myException (
							"The ${no}th parameter type of query is not null type",
							E_USER_ERROR
						);
						return false;
					}
					break;
				case 'b' : // blob
				case 'c' : // clob
				case 's' : // string. by pass
					break;
				default :
					throw new myException (
						"The ${no}th parameter type of query is unsupported type",
						E_USER_ERROR
					);
					return false;
			}
		}

		return true;
	}
	// }}}

	// {{{ (string) EDB_Common::bind_param ($sql, $param)
	/**
	 * replace bind parameters to parameter's value
	 * 
	 * @access public
	 * @return string
	 * @param  string SQL query statement
	 * @param  array  array of parameter values
	 */
	function bind_param ($sql, $params) {
		if ( ! is_array ($params) )
			return $sql;

		$types = array_shift ($params);
		$c = count ($params);

		$sql = preg_replace ('/[\x5c]\?/', '=-=-', $sql);
		for ( $i=0; $i<$c; $i++ ) {
			if ( ! strncmp ('unquote:', $params[$i], 8) ) {
				$params[$i] = substr ($params[$i], 8);
				$buf = preg_replace ('/\?/', '%s', $sql, 1);
			} else
				$buf = preg_replace ('/\?/', "'%s'", $sql, 1);
			$sql = sprintf ($buf, $params[$i]);
		}
		$sql = preg_replace ('/=-=-/', '\?', $sql);

		return $sql;
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
