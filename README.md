# cleanPDO

A simple DB class written to utilize MYSQL &amp; PDO in PHP. The aim of this project is to keep the DB interaction modern and concise yet still retain all the most commonly used features. Any push requests are welcome.

### Sample Usage

#### Instantiation 

```php
require('db.pdo.mysql.php');
$db = new db('db_sample_settings.json');
```
#### SELECT (Returns record set [array]) [SHOW statements also return a record set]

```php
$query = 'select name from users where userID = :id';
$db->addParam(':id',1,PDO::PARAM_INT);
$result = $db->processQuery($query);
```
#### INSERT (Returns row(s) affected [int]) [DELETE, UPDATE statements also return row(s) affected]

```php
$query = 'insert into users (name) values (:username)';
$db->addParam(':username',"David",PDO::PARAM_STR);
$result = $db->processQuery($query);
```

#### Notes

- In case of query error or PDO / code exception the "processQuery" method will return the error text (string).
- All query errors and PDO / code exceptions are logged to the default PHP errors log configured on machine. 