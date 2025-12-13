<?php

$localhost = 'localhost';
$name = 'root';
$password = '';
$dbname = 'e-shkolla';


try{
    $pdo = new PDO("mysql:localhost=$localhost;dbname=$dbname;charset=utf8", $name, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

}catch(PDOException $e){
    echo "There's am error " . $e->getMessage();
}