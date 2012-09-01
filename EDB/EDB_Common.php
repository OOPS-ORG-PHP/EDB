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
			switch ($type[$i]) {
				case 'i' :
					if ( gettype ($param[$i]) != "integer" ) {
						$this->error = sprintf ('The %dth parameter type of query is not numeric type', $i + 1);
						return false;
					}
					break;
				case 'd' : // for mysql
				case 'f' : // for sqlite
					if ( gettype ($param[$i]) != "double" ) {
						$this->error = sprintf ('The %dth parameter type of query is not double type', $i + 1);
						return false;
					}
					break;
				case 'b' :
				case 's' :
					break;
				case 'n' :
					if ( $param[$i] ) {
						$this->error = sprintf ('The %dth parameter type of query is not null type', $i + 1);
						return false;
					}
					break;
				default :
					$this->error = sprintf ('The %dth parameter type of query is unsupported type', $i + 1);
					return false;
			}
		}

		return true;
	}
	// }}}

}

function EDB_ErrorHandler ($errno, $errstr, $errfile, $errline) {
	$errEvent = array (
		E_ERROR => 'ERROR',
		E_WARNING => 'WARNING'
	);

	switch ( $errno ) {
		case E_ERROR:
		case E_WARNING:
			if ( $errstr == 'Division by zero' )
				break;
			throw new Exception ($errEvent[$errno] . ':' . $errstr . ' in ' . preg_replace ('!.*/!', '', $errfile) . ':' . $errline);
			break;
	}
}

function EDB_ExceptionHandler ($exception) {
	echo "*** " . $exception->getMessage () . "\n";
}

set_error_handler('EDB_ErrorHandler');
#set_exception_handler('EDB_ExceptionHandler');
