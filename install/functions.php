<?php 

if(!isset($include)) { die('Direct access not permitted'); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // check if data is set
    if (isset($_POST['data'])) { 

        $data = $requestHandler->unprotect($_POST['data']);

        // check if data is valid

    }
}
?>