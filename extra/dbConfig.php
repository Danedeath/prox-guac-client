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
    $DBName = $INFO["sql_db"];
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

$DBHost_login = $INFO["sql_host"];
$DBPort_login = $INFO["sql_port"];
$DBUser_login = $INFO["sql_user"];
$DBPass_login = $INFO["sql_pass"];
$DBName_login = $INFO["sql_db"];

try { 
    $DB_login = new PDO("mysql:host=$DBHost_login;port=$DBPort_login;dbname=$DBName_login", $DBUser_login, $DBPass_login);
    $DB_login->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $DB_login->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, "SET NAMES 'utf8mb4'");
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