<?php

if (session_status() != PHP_SESSION_ACTIVE) { 
    session_start();
} else { 
    if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { 
        header("location: ./login/login.php");
        exit;
    }
}

header("location: ./servers.php");

?>