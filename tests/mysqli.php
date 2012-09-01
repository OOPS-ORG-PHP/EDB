<?php
/*
 * pear_EDB mysqli tests
 *
 * $Id$
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


require_once 'edb.php';

$host = 'mysqli://localhost:/var/run/mysqld/mysql.sock';
$user = 'user';
$pass = 'password';
$db   = 'database';

$query = 'SELECT no, num, idx FROM jsboard_oopsQnA ' .
		'WHERE no > 1113 and no < 1118 ORDER by no DESC';

$bind_query = 'SELECT no, num, idx FROM jsboard_oopsQnA ' .
		'WHERE no > ? and no < ? ORDER by no DESC';

try {
	$db = new EDB ($host, $user, $pass, $db);

	##############################################################################
	# Charset test
	##############################################################################
	echo "*** Charset test\n";
	printf ("   + Current Charset : %s\n", $db->get_charset ());
	$db->set_charset ('euckr');
	printf ("   + Change charset  : %s\n", $db->get_charset ());
	$db->set_charset ('utf8');
	printf ("   + Change charset  : %s\n", $db->get_charset ());

	##############################################################################
	# Bind select test
	##############################################################################
	echo "\n\n*** Bind select query test\n";
	echo "   + QEURY: {$bind_query}\n";
	$no = $db->query ($bind_query, 'ii', 1113, 1118);
	printf ("    + Affected Rows is %d\n", $r);

	##############################################################################
	# Result fetch test
	##############################################################################
	echo "\n\n*** Result fetch test\n";

	$i = 0;
	while ( ($f = $db->fetch ()) ) {
		$r[$i++] = $f;
	}

	$db->free_result ();

	print_r ($r);

	##############################################################################
	# Nomal select test
	##############################################################################
	echo "\n\n*** Bind select query test\n";

	echo "   + QEURY: {$query}\n";
	$no = $db->query ($query);
	printf ("    + Affected Rows is %d\n", $r);

	##############################################################################
	# Result fetch_all test
	##############################################################################
	echo "\n\n*** Result fetch_all test\n";

	$r = $db->fetch_all ();
	print_r ($r);

} catch ( EDBException $e ) {
	fprintf (
		STDERR, "Error: %s [%s:%d]\n",
		$e->getMessage (),
		preg_replace ('!.*/!', '', $e->getFile ()),
		$e->getLine ()
	);
	print_r ($e);
	#print_r ($e->EDB_getTrace ());
	echo $e->EDB_getTraceAsString () . "\n";
	#print_r ($e->EDB_getTraceAsArray ()) . "\n";
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
