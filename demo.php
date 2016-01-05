<?php 

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once('SJMSQLayer.php');

// Create an SJMSQLayer object with a in-memory SQLite database
$db = new PDO('sqlite::memory:');
$dbl = new SJMSQLayer($db);

// Activate logging
$dbl->log = array();

// Add some data
$data = array('key1'=>'value1','key2'=>'value2','key3'=>'value3');
$dbl->query('CREATE TABLE IF NOT EXISTS foo (id integer primary key, i,key1,key2,key3);')->exec();
for ($i=0; $i<3; $i++) {
	$data['i'] = $i;
	$id = $dbl->query('INSERT INTO foo %I;',$data)->lastInsertId();
	echo "inserted {id: $id, i: $i}\n";
}

// Modify data
for ($i=0; $i<3; $i++) {
	$update = array('key2'=>$i*2);
	$where = array('i'=>$i);
	$dbl->query('UPDATE foo SET %S WHERE %W;',$update,$where)->exec();
}

// Get results
$r = $dbl->query('SELECT * FROM foo ORDER BY i DESC;')->getAll();
$d = $dbl->query('SELECT i, key2 as j FROM foo ORDER BY i DESC;')->getDict('i', 'j');

print_r($r);
print_r($d);
print_r($dbl->log);

