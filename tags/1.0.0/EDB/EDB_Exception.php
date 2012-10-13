<?php
/**
 * PHP Version 5
 *
 * Copyright (c) 1997-2012 JoungKyun.Kim
 *
 * LICENSE: BSD
 *
 * @category    Database
 * @package     EDB_Exception
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   1997-2012 OOPS.org
 * @license     BSD
 * @version     SVN: $Id: EDB_Common.php 4 2012-08-31 19:14:39Z oops $
 */

class EDBException extends Exception {
	public function __construct($message, $code = 0, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}

	// {{{ (array) EDB_Exception::EDB_getTrace (void)
	/**
	 * Returns the Exception stack trace.
	 *
	 * @access public
	 * @return array  Returns the Exception stack trace as an array.
	 * @param  void
	 */
	function EDB_getTrace () {
		$r = $this->getPrevious ();
		if ( $r instanceof Exception )
			return $r->getTrace();

		return $this->getTrace ();
	}
	// }}}

	// {{{ (object) EDB_Exception::EDB_getPrevious (void)
	/**
	 * Returns previous Exception (the third parameter of EDBException::__construct()).
	 *
	 * @access public
	 * @return object exception object 
	 * @param  void
	 */
	function EDB_getPrevious () {
		$r = $this->getPrevious ();
		if ( $r instanceof Exception )
			return $r->getPrevious ();

		return $this->getPrevious ();
	}
	// }}}

	// {{{ (string) EDB_Exception::EDB_getTraceAsString (void)
	/**
	 * Returns the Exception stack trace as a string.
	 *
	 * @access public
	 * @return string Returns the Exception stack trace as a string.
	 * @param  void
	 */
	function EDB_getTraceAsString () {
		$r = $this->getPrevious ();
		if ( $r instanceof Exception )
			return $r->getTraceAsString ();

		return $this->getTraceAsString ();
	}
	// }}}

	// {{{ (array) EDB_Exception::EDB_getTraceAsArray (void)
	/**
	 * Returns the Exception stack trace as a array
	 *
	 * @access public
	 * @return array  Returns the Exception stack trace as a array.
	 * @param  void
	 */
	function EDB_getTraceAsArray () {
		$r = $this->getPrevious ();
		if ( $r instanceof Exception )
			$str = $r->getTraceAsString ();
		else
			$str = $this->getTraceAsString ();

		$buf = preg_split ('/[\r\n]+/', $str);
		$no = count ($buf) - 1;

		for ( $i=$no, $j=0; $i>-1; $i--,$j++ ) {
			$ret[$j] = preg_replace ('/^#[0-9]+[\s]*/', '', $buf[$i]);
		}
		return $ret;
	}
	// }}}
}

function EDB_ErrorHandler ($errno, $errstr, $errfile, $errline) {
	switch ($errno ) {
		case E_NOTICE :
		case E_USER_NOTICE :
		case E_STRICT :
		case E_DEPRECATED :
		case E_USER_DEPRECATED :
			break;
		default :
			throw new Exception ($errstr, $errno);
	}
}

set_error_handler('EDB_ErrorHandler');

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
