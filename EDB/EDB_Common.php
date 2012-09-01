<?php
/**
 * PHP Version 5
 *
 * Copyright (c) 1997-2012 JoungKyun.Kim
 *
 * LICENSE: BSD
 *
 * @category    Database
 * @package     EDB_Common
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   1997-2012 OOPS.org
 * @license     BSD
 * @version     SVN: $Id: EDB_Common.php 4 2012-08-31 19:14:39Z oops $
 */

Class EDB_Common {
	// {{{ properties
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
	function get_param_number ($sql) {
		return strlen (preg_replace ('/[^?]/', '', $sql));
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
				case 'i' :
					if ( gettype ($param[$i]) != "integer" ) {
						throw new EDBException ("The ${no}th parameter type of query is not numeric type", E_ERROR);
						return false;
					}
					break;
				case 'd' : // for mysql
				case 'f' : // for sqlite
					if ( gettype ($param[$i]) != "double" ) {
						throw new EDBException ("The ${no}th parameter type of query is not double type", E_ERROR);
						return false;
					}
					break;
				case 'b' :
				case 's' :
					break;
				case 'n' :
					if ( $param[$i] ) {
						throw new EDBException ("The ${no}th parameter type of query is not null type", E_ERROR);
						return false;
					}
					break;
				default :
					throw new EDBException ("The ${no}th parameter type of query is unsupported type", E_ERROR);
					return false;
			}
		}

		return true;
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
