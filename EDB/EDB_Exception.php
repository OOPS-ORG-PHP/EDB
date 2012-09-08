<?php
/**
 * Project: EDBException :: Exception API for EDB class
 * File:    EDB/EDB_Exception.php
 *
 * The EDBException class is exception api that used internally
 * on EDB class.
 *
 * @category    Database
 * @package     EDB
 * @subpackage  EDBException
 * @author      JoungKyun.Kim <http://oops.org>
 * @copyright   (c) 1997-2012 OOPS.org
 * @license     BSD License
 * @version     $Id: EDBException.php 4 2012-08-31 19:14:39Z oops $
 * @link        http://pear.oops.org/package/EDB
 * @filesource
 */

/**
 * Contorl EDB error messages with PHP exception
 *
 * @package EDB
 */
class EDBException extends Exception {
	// {{{ (object) EDBException::__construct ($message, $code, Exception $previous = null)
	/** 
	 * Initialize EDBException class
	 *
	 * @access public
	 * @return object
	 * @param  string error messages
	 * @param  string error code
	 * @param  string previous exception object
	 */
	public function __construct($message, $code = 0, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}
	// }}}

	// {{{ (array) EDBException::EDB_getTrace (void)
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

	// {{{ (object) EDBException::EDB_getPrevious (void)
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

	// {{{ (string) EDBException::EDB_getTraceAsString (void)
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

	// {{{ (array) EDBException::EDB_getTraceAsArray (void)
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

/**
 * User defined error handler for EDB class
 */
// {{{ (void) EDB_ErrorHandler ($errno, $errstr, $errfile, $errline)
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
// }}}

/**
 * Set php error handler to EDB_ErrorHandler api.
 *
 * This action is affects whole codes that is include EDB class
 */
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
