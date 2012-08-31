<?php
// $Id: $
require_once 'edb.php';

$db = new EDB ('mysqli://localhost:/var/run/mysqld/mysql.sock', 'user', 'passwd', 'dbname');
if ( $db->error ) {
	printf ("Error : %s\n", $db->error);
	exit (1);
}
#print_r ($db);

#$db->set_charset ('euckr');
print_r ($db->get_charset ());

$r = $db->query ("SELECT no, num, idx FROM test WHERE no > ? and no < ? ORDER by no DESC", 'ii', 1113, '1118');
#$r = $db->query ("SELECT no, num, idx FROM test WHERE no > 1113 and no < 1118 ORDER by no DESC");
if ( $r === false ) {
	fprintf (STDERR, "%s\n", $db->error);
	exit (1);
}

printf ("*** Affected Rows is %d\n", $r);
unset ($r);

#$r = $db->fetch_all ();

$i = 0;
while ( ($f = $db->fetch ()) ) {
	$r[$i++] = $f;
}

$db->free_result ();

print_r ($r);

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
