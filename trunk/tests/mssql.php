<?php
/*
 * pear_EDB mssql tests
 *
 * $Id$
 */

require_once './test-common.php';
require_once 'edb.php';

function get_bind_select () {
	global $db;

	$n = $db->query ('SELECT * FROM ttt WHERE num > ? ORDER by num DESC', 'i', 0);

	echo '*** Current columns are';
	$no = $db->num_fields ();
	for ( $i=0; $i<$no; $i++ )
		echo ' ' . $db->field_name ($i) . '(' . $db->field_type ($i, 'ttt') . ')';
	echo "\n\n";

	echo "*** move data couror to 2\n";
	$db->seek (2);
	$r = $db->fetch_all ();
	$db->free_result ();

	print_r ($r);
	echo "*** selected affected Rows is $n\n";
}

function get_select () {
	global $db;

	$n = $db->query ('SELECT * FROM ttt WHERE num > 0 ORDER by num DESC');
	$r = array ();
	while ( ($f = $db->fetch ()) )
		$r[] = $f;
	$db->free_result ();

	print_r ($r);
	echo "*** selected affected Rows is $n\n";
}

$create_table = <<<EOF
CREATE TABLE ttt (
	num int NOT NULL IDENTITY(1,1) PRIMARY KEY,
	nid char(30) NOT NULL UNIQUE default '',
	name char(30) NOT NULL default ''
);
EOF;

$host = 'mssql://localhost:1433';
$user = 'user';
$pass = 'password';
$db   = 'database';


try {

	$i=0;
	$db = new EDB ($host, $user, $pass, $db);

	##############################################################################
	# Create table test
	##############################################################################
	echo "\n\n*** Create table\n";
	#$r = $db->query ('drop table ttt');
	$r = $db->query ($create_table);
	printf ("*** Affected Rows is %d\n", $r);


	##############################################################################
	# Insert test
	##############################################################################
	echo "\n\n*** Insert test\n";
	for ( $i=0; $i<4; $i++ )
		$r = $db->query (
			"INSERT INTO ttt (nid, name) values (?, ?)",
			'ss',
			"Blah Blah~$i",
			'admin@host.com'
		);
	printf ("*** Affected Rows is %d\n", $r);
	$db->free_result ();

	get_select ();

	##############################################################################
	# Update test
	##############################################################################
	echo "\n\n*** Update date\n";
	$r = $db->query (
		"UPDATE ttt SET name = ? WHERE nid = ?",
		'ss',
		'Blar Blar~31',
		'Blah Blah~3');
	printf ("*** Affected Rows is %d\n", $r);
	$db->free_result ();

	get_bind_select ();

	##############################################################################
	# Delete test
	##############################################################################
	echo "\n\n*** Delete test\n";
	$r = $db->query ("DELETE FROM ttt WHERE nid = ?", 's', 'Blah Blah~3');
	printf ("*** Affected Rows is %d\n", $r);
	$db->free_result ();

	get_select ();

	##############################################################################
	# Delete table
	##############################################################################
	echo "\n\n*** Delete table\n";
	$r = $db->query ("DROP TABLE ttt");
	printf ("*** Affected Rows is %d\n", $r);
	$db->free_result ();

} catch ( EDBException $e ) {
	fprintf (
		STDERR, "Error: %s [%s:%d]\n",
		$e->getMessage (),
		preg_replace ('!.*/!', '', $e->getFile ()),
		$e->getLine ()
	);
	#print_r ($e);
	#print_r ($e->EDB_getTrace ());
	#echo $e->EDB_getTraceAsString () . "\n";
	print_r ($e->EDB_getTraceAsArray ()) . "\n";
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