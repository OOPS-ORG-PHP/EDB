<?php

/*
 * Test common codes
 *
 * $Id: $
 */

if ( ! function_exists ('___ini_get') ) {
	function ___ini_get ($var) {
		return ini_get ($var);
	}
}

if ( ! function_exists ('___ini_set') ) {
	function ___ini_set ($var, $value) {
		return ini_set ($var, $value);
	}
}

$cwd = getcwd ();
$ccwd = basename ($cwd);
if ( $ccwd == 'tests' ) {
	$oldpath = ___ini_get ('include_path');
	$newpath = preg_replace ("!/{$ccwd}!", '', $cwd);
	___ini_set ('include_path', $oldpath . ':' . $newpath);
}

?>
