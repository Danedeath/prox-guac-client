<?php

include './header.php';

if(!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) { 
    header("location: ./login/login.php?next=connections");
    exit;
}

if (empty($running_nodes)) {
    $errorMSG = "No running nodes were discovered!";
    include $root."/extra/error.php";
    die();
}

if (!filter_var($user_perms['connections'],FILTER_VALIDATE_BOOLEAN)) {
    $errorMSG = "You do not have permission to access this page!";
    include 'extra/error.php';
    die();
}


// functions for the creation, editing and deletion of connections
if (isset($_POST['data']) && $_POST['data'] != '') { // data being sent with a post, read it to determine the action requested!

    $data = $requestHandler->unprotect($_POST['data']);

    $state = $data[0];
    $type  = $data[1];

    if ($state != $_SESSION['state']) { 
        $errorMSG = "An error has occurred. Please try again.";
        include 'extra/error.php';
        exit();
    }

    if ($type == 'action') { // only handle actions being sent, otherwise ignore them and send an error

        $action = $data[2];
        
        if ($action == 'create') { 

            $conn_data = array(
                'name'      => $_POST['name'],
                'host'      => $_POST['host'],
                'port'      => $_POST['port'],
                'username'  => $_POST['username'],
                'password'  => $_POST['password'],
                'protocol'  => $_POST['protocol'],
                'os'        => $_POST['os'], 
                'node'      => $_POST['node'],
                'drive'     => $_POST['drive'],
                'owner'     => $_POST['owner'],
            );

            // $connManager->validateConn($conn_data); // validate the connection data (checks for empty fields

            $alert_msg = $connManager->createConnection($conn_data);

        } else if ($action == 'update') { 
            
            $conn_data = array(
                'id'        => $data[3],
                'name'      => $_POST['name'],
                'host'      => $_POST['host'],
                'port'      => $_POST['port'],
                'username'  => $_POST['username'],
                'password'  => $_POST['password'],
                'protocol'  => $_POST['protocol'],
                'os'        => $_POST['os'], 
                'node'      => $_POST['node'],
                'drive'     => $_POST['drive'],
                'owner'     => $_POST['owner'],
            );
            
            $alert_msg = $connManager->updateConnection($conn_data);

        } else if ($action == 'delete') { 

            $conn_data = $connManager->getConnection($data[3]);
            $alert_msg = $connManager->deleteConnection($conn_data);


        } else { 
            $errorMSG = "An invalid action was requested, how'd you do that?";
            include 'extra/error.php';
            exit();
        }

    } else { 
        $errorMSG = "An error has occurred. Please try again.";
        $returnPage = $_SERVER['SELF'];
        include 'extra/error.php';
        exit(); 
    }

}
?>

    <div class="row position-absolute main-body">
        <section class="content-header">
            <h2>
                Connection Management
                <small>
                    Proxmox Admin Panel v0.0.1
                </small>
            </h2>
            <ol class="breadcrumb pe-5 pull-right">
                <li>
                    <a href="index.php">
                        <i class="fa fa-dashboard"></i> 
                        Home 
                    </a>
                </li>
                <li class="active"> Connection Manager</li>
            </ol>
        </section>
        <div class="row pt-5 pe-5">
            <?php include "./diag/conn_page.php"; ?>
        </div>
    </div>
</div>