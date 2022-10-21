<?php

if(!isset($include)) { die('Direct access not permitted'); }

require "config.php";

if ($INFO['guac_login']) { 

    $DBHost = $INFO["guac_sql_host"];
    $DBPort = $INFO["guac_sql_port"];
    $DBUser = $INFO["guac_sql_user"];
    $DBPass = $INFO["guac_sql_pass"];
    $DBName = $INFO["guac_sql_database"];

} else if ($INFO['dash_login']) { 

    $DBHost = $INFO["sql_host"];
    $DBPort = $INFO["sql_port"];
    $DBUser = $INFO["sql_user"];
    $DBPass = $INFO["sql_pass"];
    $DBName = $INFO["sql_database"];
}

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



/*
$guacDB = new PDO($INFO["sql_host"], $INFO["sql_user"], $INFO["sql_pass"], $INFO["sql_database"]);
if ($guacDB->connect_error) {
    die("Connection failed: " . $guacDB->connect_error);
}
$guacDB->set_charset("utf8mb4");
*/
?>