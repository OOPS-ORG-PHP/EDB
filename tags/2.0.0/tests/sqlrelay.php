<?php
/*
 * pear_EDB sqlrelay tests
 *
 * This is test of mysql backend
 *
 * $Id$
 */

require_once './test-common.php';
require_once 'edb.php';

function get_charset () {
	$r = $GLOBALS['db']->query ('SHOW variables WHERE Variable_name = ?', 's', 'character_set_client');
	if ( $r != 1 )
		return 'Unsupport';

	$row = $GLOBALS['db']->fetch ();
	$GLOBALS['db']->free_result ();
	return $row->Value;
}

function set_charset ($char) {
	return $GLOBALS['db']->query ("SET NAMES {$char}");
}

$scheme = <<<EOF
CREATE TABLE edb_test (
	num  int(6) NOT NULL auto_increment,
	cid char(30) NOT NULL default '',
	cname char(30) NOT NULL default '',
	bdata blob,
	PRIMARY KEY  (num),
	UNIQUE KEY nid (cid)
) CHARSET=utf8;
EOF;

$host = 'sqlrelay://172.16.13.3:9000';
$user = 'sqlr';
$pass = 'sqlr';
$db   = 'WebBoard';

try {

	$i=0;
	$db = new EDB ($host, $user, $pass, $db);

	##############################################################################
	# Charset test
	##############################################################################
	echo "*** Charset test\n";
	printf ("   + Current Charset : %s\n", get_charset ());
	set_charset ('euckr');
	printf ("   + Change charset  : %s\n", get_charset ());
	set_charset ('utf8');
	printf ("   + Change charset  : %s\n", get_charset ());


	##############################################################################
	# Create table test
	##############################################################################

	$r = $db->query ('SHOW TABLES WHERE Tables_in_WebBoard = ?', 's', 'edb_test');
	$db->free_result ();

	if ( $scheme && ! $r ) {
		echo "\n\n*** Create table\n";
		$r = $db->query ($scheme);
		printf ("    => Affected Rows is %d\n", $r);
	}


	##############################################################################
	# Insert test
	##############################################################################
	echo "\n\n*** Insert test\n";
	$r = 0;

	# get blob data
	$imgs = file_get_contents ('./test.png');

	# insert bind query test
	$sql = 'INSERT INTO edb_test (cid, cname, bdata) VALUES (?, ?, ?)';

	$img->data = $imgs;
	$img->len = filesize ('./test.png');
	for ( $i=0; $i<4; $i++ ) {
		$n = $db->query ($sql, 'ssb', 'cid_' . $i, $db->escape ('c\'name_' . $i), $img);
		$db->free_result ();
		$r += $n;
	}

	# SQLRelay don't support static query with blob data !!!!!
	/*
	for ( $i=2; $i<4; $i++ ) {
		$sql = sprintf (
			'INSERT INTO edb_test (cid, cname, bdata) VALUES (\'%s\', \'%s\', \'%s\')',
			'cid_' . $i,
			$db->escape ('c\'name_' . $i),
			$db->escape ($imgs)
		);
		# It's ok too from MySQL 5.1
		#$sql = sprintf (
		#	'INSERT INTO edb_test (cid, cname, bdata) VALUES (\'%s\', \'%s\', 0x%s)',
		#	'cid_' . $i,
		#	$db->escape ('c\'name_' . $i),
		#	bin2hex ($imgs)
		#);
		$n = $db->query ($sql);
		$db->free_result ();
		$r += $n;
	}
	 */

	printf ("    => Affected Rows is %d\n", $r);

	##############################################################################
	# Select test
	##############################################################################
	echo "\n\n*** Select test\n";
	$r = $db->query ('SELECT * FROM edb_test WHERE num > ?', 'i', 0); 
	printf ("    => Selected Rows is %d (Bind query)\n", $r);

	printf ('    => Current columns are');
	$fno = $db->num_fields ();
	for ( $i=0; $i<$fno; $i++ )
		printf (' %s (%s)', $db->field_name ($i), $db->field_type ($i, 'edb_test'));

	printf ("\n    => Move data cousor to 2\n");
	$db->seek (2);
	$row = $db->fetch_all ();
	printf ("    => Fetched data is %d lows\n\n", count ($row));

	$db->free_result ();
	unset ($row);

	$r = $db->query ('SELECT * FROM edb_test WHERE num > 0'); 
	printf ("    => Selected Rows is %d\n", $r);

	for ( $i=0; $i<$r; $i++ ) {
		$row[] = $db->fetch ();
	}
	printf ("    => Fetched data is %d lows\n\n", count ($row));

	printf ("    => Original image's md5 is %s\n", md5_file ('test.png'));
	$fp = fopen ('test_new.png', 'wb');
	if ( is_resource ($fp) ) {
		fwrite ($fp, $row[3]->bdata);
		fclose ($fp);
	}

	if ( file_exists ('test_new.png') ) {
		printf ("    =>  Selected data's md5 is %s\n", md5_file ('test_new.png'));
		printf ("    =>  Selected data's size is %d\n", filesize ('test_new.png'));
		unlink ('test_new.png');
	}

	$db->free_result ();
	unset ($row);


	##############################################################################
	# Update test
	##############################################################################
	echo "\n\n*** Update date\n";
	$r = $db->query (
		"UPDATE edb_test SET cname = ? WHERE cid = ?",
		'ss',
		'cname_22',
		'cid_2');
	printf ("    => Affected Rows is %d\n", $r);
	$db->free_result ();

	$r = $db->query ('SELECT * FROM edb_test WHERE cid = ?', 's', 'cid_2'); 
	$row = $db->fetch ();

	if ( $row->cname == 'cname_22' )
		printf ("    => Changed data is %s\n", $row->cname);
	else
		printf ("    => Don't changed data is %s\n", $row->cname);

	$db->free_result ();
	unset ($row);


	##############################################################################
	# Delete test
	##############################################################################
	echo "\n\n*** Delete test\n";
	$r = $db->query ("DELETE FROM edb_test WHERE cid = ?", 's', 'cid_2');
	$db->free_result ();
	printf ("    => Affected Rows is %d\n", $r);

	$r = $db->query ('SELECT * FROM edb_test WHERE num > 0'); 
	printf ("    => Selected Rows is %d\n", $r);

	$row = $db->fetch_all ();
	printf ("    => Fetched data is %d lows\n\n", count ($row));

	$db->free_result ();
	unset ($row);

	##############################################################################
	# Delete table
	##############################################################################
	/*
	 * SQLRelay가 붙어 있는 동안 mysql table drop이 안된다! 
	 *
	echo "\n\n*** Delete table\n";
	$r = $db->query ("DROP TABLE edb_test");
	printf ("*** Affected Rows is %d\n", $r);
	$db->free_result ();
	 */

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
