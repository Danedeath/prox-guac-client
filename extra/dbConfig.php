<?php

if(!isset($include)) { die('Direct access not permitted'); }

require "config.php";

$DBHost = $INFO["sql_host"];
$DBPort = $INFO["sql_port"];
$DBUser = $INFO["sql_user"];
$DBPass = $INFO["sql_pass"];
$DBName = $INFO["sql_db"];

try { 
    $DB = new PDO("mysql:host=$DBHost;port=$DBPort;dbname=$DBName", $DBUser, $DBPass);
    $DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $DB->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, "SET NAMES 'utf8mb4'");
} catch(PDOException $e) {
    # echo 'ERROR: ' . $e->getMessage();
    $msg = "Database connection failed. Please contact your system administrator.";
    include ('error.php');
    die();
}

?>