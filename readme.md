# dbAnt Overview
dbAnt is PHP library for database IO. As of this version 1.0.0, it supports only PHP PDO extension.

## Requirements

- It requires PHP version 5.x
- vilshub/helpers library

## Installation
It supports composer installation, with link **composer require vilshub/dbant** 


## Features

- Single query execution with prepared statement support
- Batch query execution with prepared statement support



**Features to be included in v1.1.0**

- User authenticator

## Demo
**Single query execution**

```php
<?php
        //$pdo as PDO connection handler
        
        use vilshub\dbant\dbAnt;
        $dbAnt = new dbAnt($pdo);
        $sqlPrepared = "INSERT INTO cars SET
      	    name = ?,
            model = ?,
            color = ?,
            price = ?,
            available = ?";

        $data = ["Nissan", "Primera", "Blue", "3000", "30"];
        $exec =  $dbAnt->run($sqlPrepared, $data);
        if($exec["status"]){
            //suucess
        }
?>
```


**Batch query execution**

```php
    <?php
        //$pdo as PDO connection handler
        
        use vilshub\dbant\dbAnt;
        $dbAnt = new dbAnt($pdo);
        $sqlPrepared = "INSERT INTO cars SET
      	    name = ?,
            model = ?,
            color = ?,
            price = ?,
            available = ?";

        $data = [
            array("Nissan", "Primera", "Blue", "3000", "30"),
            array("Nissan", "Primera", "Blue", "3000", "30"),
            array("Nissan", "Primera", "Blue", "3000", "30"),
            array("Nissan", "Primera", "Blue", "3000", "30"),
            array("Nissan", "Primera", "Blue", "3000", "30"),
            array("Nissan", "Primera", "Blue", "3000", "30"),
            array("Nissan", "Primera", "Blue", "3000", "30")
        ]

        $exec =  $dbAnt->batchRun($sqlPrepared, $data);
        if($exec){
            //suucess
        }
    ?>
```




## Documentation ##

**run() method**

The run method executes an SQL statement via PDO extention.

**Syntax**

```php 
    $obj->run($query, [$data]) 
```

The second argument $data is optional, only used when data is to be supplied for query.it returns the following:

    [
        "status"=>false | true,
        "rowCount"=>null | totalAffectRow,
        "lastInsertId"=>null | lastInsertedID,
        "data"=>null | fetchedResult
    ]


**batchRun()  method**

The batchRun method executes multiple SQL queries using the suplied data on a single SQL query. It executes SQL statement via PDO extention.

**Syntax**
```php 
$obj->batchRun($query, $data)
```

The second argument $data must be numeric array of arrays of values to be executed on the supplied query. It returns TRUE on successfull batch execution.



More comprehensive documentation for this library will be provided soon, you may **Watch**, this repo for further updates.
