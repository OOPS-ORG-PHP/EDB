# PHP EDB(Extended DB) Class API

# Abstract

The EDB package provides abstraction layers for various DBs. The features of EDB package are as follows:

1. Support DBs
 1. MySQL (require MySQL extension)
 2. MySQLi (require MySQLi extension)
 3. SQLite2 (require sqlite2 extension)
 4. SQLite3 (require sqlite3 extension)
 5. PostgreSQL (require pgsql extension)
 6. MSSQL (require mssql extension)
 7. SQL Relay (require sqlrelay extension)
2. Simple and easy to use
3. Support bind query. If the DB API does not provide a bind query, it checks only the types of the binded variables.

# Reference

http://pear.oops.org/docs/EDB/EDB_Common/EDB.html

# Installation

use pear system

```sh
[root@host ~]$ pear channel-discover pear.oops.org
[root@host ~]$ pear install oops/EDB
[root@host ~]$ pear list -a
```

# Requires

1. PHP [myException](https://github.com/OOPS-ORG-PHP/myException) class

```sh
[root@host ~]$ pear install oops/myException
```

# Examples:

See also https://github.com/OOPS-ORG-PHP/EDB/tree/master/tests

```php
<?php
require_once 'EDB.php';

try {
    $db = new EDB (
        'mysqli://localhost:/var/run/mysqld/mysql.sock',
        'user', 'pwd', 'dbname'
    );

    $db->set_charset ('utf8'); // only mysql (SET NAMES utf8;)

    $rno = $db->query ('SELECT * FROM edb_test WHERE num > ?', 'i', 0);
    $r = $db->fetch_all ();
    $db->free_result ();

    print_r ($r);
} catch ( myException $e ) {
    fprintf (STDERR, "%s\n", $e->Message ());
    #print_r ($e);
    #print_r ($e->Trace ());
    #echo $e->TraceAsString () . "\n";
    print_r ($e->TraceAsArray ()) . "\n";
    $e->finalize ();
}
?>
```
