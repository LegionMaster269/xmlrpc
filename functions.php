<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'roshan';

$connection = new mysqli($host, $user, $pass, $dbname);

if ($connection->connect_error) {
    die("Connection failed: " . $connection->connect_error);
}

function MysqlQuery($query) {
    global $connection;

    $result = $connection->query($query);

    if (!$result) {
        die("Query Error: " . $connection->error);
    }

    return $result;
}

function SanitizeString($var) {
    global $connection;

    $var = htmlentities($var);
    $var = stripslashes($var);
    $var = strip_tags($var);

    return $connection->real_escape_string($var);
}
?>