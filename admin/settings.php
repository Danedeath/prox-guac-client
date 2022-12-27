<?php

include './header.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { 
    header("location: ./login/login.php");
    exit;
}

if (empty($running_nodes)) {
    $errorMSG = "No running nodes were discovered!";
    include $root."/extra/error.php";
    die();
}

?>

    <div class="row position-absolute main-body">
        <section class="content-header">
            <h2>
                Settings Overview
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
                <li class="active"> Settings</li>
            </ol>
        </section>
    </div>
</div>
</div>