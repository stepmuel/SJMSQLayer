# SJMSQLayer

A PHP class to simplify the creation of SQL queries in PHP using dictionaries. 

This repository was created for my [blog post](http://heap.ch/blog/2016/01/05/sjmsqlayer/) which contains additional information.

## Basic Usage

```php
<?php
// Open SQLite database
$db = new PDO('sqlite:db.sqlite');
// Create SJMSQLayer object
$dbl = new SJMSQLayer($db);
// Activate logging
$dbl->log = array();
// Use SJMSQLayer
$dbl->query("DROP TABLE %K", "books")->exec();
// Show all executed queries including error messages
print_r($dbl->log);
```

## Query Format Syntax

The `query` function works similar to `printf`. The following conversion specifications are available. The code at the end shows the results with an input dictionary `{"key1":"value1", "key2":"value2"}`.

**%@** -- quoted value / comma separated list `"value1","value2"` 
**%K** -- unquoted value / comma separated list `value1,value2` 
**%W** -- where (WHERE %W), key = value, connected with 'AND' `key1="value1" AND key2="value2"` 
**%S** -- assign dictionary key to value (UPDATE SET %A) `key1="value1",key2="value2"` 
**%I** -- insert (INSERT INTO %K %I) `(key1,key2) VALUES("value1","value2")` 

```php
<?php
// Common examples
$dbl->query('INSERT INTO books %I', $data);
$dbl->query('UPDATE foo SET %S WHERE %W', $data, $where);
$dbl->query('SELECT name FROM books WHERE %W', $where);
$dbl->query('SELECT name FROM books WHERE id IN (%@)', $ids);
$dbl->query('DELETE FROM books WHERE %W', $where);

// Advanced usage
$where = array();
// numbers are not quoted
$where['year'] = 1605;
// strings are
$where['name'] = "Don Quixote";
// null handling
$where['deleted'] = null;
// arrays generate IN (v1, v2)
$where['id'] = array(1, 2, "string");
// insert statement by using integer key
$where []= "pages < 1000";
// generate SQL
echo $dbl->query('SELECT name FROM books WHERE %W', $where)->query;
```

The last example will generate

```sql
SELECT name FROM books WHERE 
	year=1605 AND 
	name='Don Quixote' AND 
	deleted ISNULL AND 
	id IN (1,2,'string') AND 
	pages < 1000
```

## Using SJMSQLayerStatement

The `query` function returns a `SJMSQLayerStatement` object, which has the following properties:

```php
<?php
class SJMSQLayerStatement {
	public $sql = null;
	public function exec();
	public function get($key=false);
	public function getAll($key=false);
	public function getDict($dictKey, $valueKey=false);
	public function getGroup($groupKey, $valueKey=false);
}
```

`get` returns a single row from a `SELECT` query, `getAll` returns an array with all rows. `getDict` will also return all rows, but instead of an array, the result will be a dictionary where each row is addressed by its value of `$dictKey`. `getGroup` is used if the value by which the rows are addressed is not unique. It will return a dictionary of arrays. Using the optional parameters `$key` and `$valueKey` will fetch single values instead of the whole rows. 

```php
<?php
$stm = $dbl->query("SELECT * FROM books");
// get array of all book id's
$stm->getAll("id");
// get a dictionary of book titles by id
$stm->getDict("id", "title");
// get a dictionary of book id's by year
$stm->getDict("published", "id");
```


