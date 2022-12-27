<?php 

if(!isset($include)) { die('Direct access not permitted'); }

// Check if user is logged in
$action_get = (isset($_GET['action'])) ? filter_var($_GET['action'], FILTER_SANITIZE_STRING) : '';
$userid = (isset($_GET['id'])) ? filter_var($_GET['id'], FILTER_SANITIZE_STRING) : '';
$user   = NULL;

$breadcrumbs = array(
    'Users' => 'userman.php'
);

if ($action_get != '') {

    $user = $userHandler->getUserByID(intval($userid));

    if (empty($user) || !$user) {
        $errorMSG = "No user found with that ID!";
        $returnPage = "userman.php";
        include "extra/error.php";
        die();
    }     

    $breadcrumbs['Editing '.$user['username']] = 'userman.php?action=edit&id=' . $userid;
    $owned_vms = $proxmox->getOwnedVMs($user['username'], $running_nodes);
}


?>
    <script src="extra/letteravatar.js"></script>
    <link href="https://gitcdn.github.io/bootstrap-toggle/2.2.2/css/bootstrap-toggle.min.css" rel="stylesheet">
    <script src="https://gitcdn.github.io/bootstrap-toggle/2.2.2/js/bootstrap-toggle.min.js"></script>
    <section class="content-header pull-right">
        <h2>
            User Management
            <small>
                Proxmox Admin Panel v0.0.1
            </small>
        </h2>
        <ol class="breadcrumb pe-5">
            <li>
                <a href="index.php">
                    <i class="fa fa-dashboard"></i> 
                    Home 
                </a>
            </li>
            <?php foreach ($breadcrumbs as $key => $value) {
                if (array_key_last($breadcrumbs) == $key) {
                    echo '<li class="active">'.$key.'</li>';
                } else {
                    echo '<li><a href="'.$value.'">'.$key.'</a></li>';
                }
            }
            ?>
        </ol>
    </section>
    <div class="row pt-5 pe-5">

<?php

if (isset($alert_msg)) { 
    if ($alert_msg['status'] == 'success') {
        ?> <div class="alert alert-success" role="alert"> <?php echo $alert_msg['message']; ?> </div> <?php
    } else {
        ?> <div class="alert alert-danger" role="alert"> <?php echo $alert_msg['message']; ?> </div> <?php
    }
}

// edit an existing user!
if ($action_get == 'edit') { ?>
        <div class="col-md-12">
            <div class="box row" style="height:180px">
                <div class="col-md-10 box-widget widget-user-2">
                    <div class="widget-user-header">
                        <div class="widget-user-image" style="width:100px;">
                            <img width="100" height="100" style="border: 3px solid #d2d6de; width:100; border-radius: 5%; margin-top: 20px;" avatar="<?php echo $user['username']; ?>">
                        </div>
                        <h1 class="widget-user-username">
                            <strong><?php echo $user['username']; ?></strong>
                        </h1>
                        <p class="widget-user-desc">
                            <?php echo $user['email']; ?>
                        </p>
                        <p class="widget-user-desc text-muted" style="margin-top: 15px;">
                            Joined <?php echo explode(' ', $user['reg_date'])[0]; ?>
                        </p>
                        <p class="widget-user-desc text-muted" style="margin-top: -15px;">
                            Last Active <?php echo explode(' ', $user['login_date'])[0]; ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-2 box-widget widget-user-2">
                    <div class="widget-user-header btn-group dropstart" style="float:right">
                        <button type="button" class="btn  btn-danger btn-flat dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            Account Actions
                        </button>
                        <ul class="dropdown-menu text-light" style="background-color: #2f343d">
                            <li style="color: #ddd;padding-left: 10px;">
                                <a href="?data=<?php echo $requestHandler->protect(array('action', 'suspend', $user['id'])); ?>">Suspend</a>
                            </li>
                            <li style="color: #ddd;padding-left: 10px;">
                                <a href="?data=<?php echo $requestHandler->protect(array('action', 'reset', $user['id'])); ?>">Reset Password</a>
                            </li>
                            <li style="color: #ddd;padding-left: 10px;">
                                <form id="deletion_form_<?php echo $user['id']; ?>" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                                    <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'delete', $user['id'])); ?>">
                                </form>
                                <a href="#" onclick="document.getElementById('deletion_form_<?php echo $user['id']; ?>').submit();">Delete</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="nav-tabs-custom">
        <ul class="nav nav-tabs" role="tablist" id="memberTabs">
            <li class="nav-item Tabs_item">
                <a id="acc-tab" data-bs-toggle="tab" data-bs-target="#acc_info" type="button" role="tab" aria-controls="user-info-tab" aria-selected="true">User Information</a>
            </li>
            <li class="nav-item Tabs_item">
                <a data-bs-target="#connections" data-bs-toggle="tab" aria-expanded="true" type="button">Connections</a>
            </li>
            <li class="nav-item Tabs_item">
                <a data-bs-target="#permissions" data-bs-toggle="tab" aria-expanded="true" type="button">Permissions</a>
            </li>
        </ul>
    </div>
    <div class="row pe-5">
        <div class="col-md-12">
            <div class="tab-content" style="margin-left: -10px !important;">
                <div class="tab-pane fade show active" id="acc_info" role="tabpanel">
                    <div class="box box-body member-form">
                        <form id="accInfoForm" name="accInfoForm" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                            <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'edituser', implode(':!:', $user)));?>">
                            <div class="row mb-3 form-group required">
                                <label class="col-sm-1 control-label member-form-labels">Display Name</label>
                                <div class="col-sm-2">                                
                                    <input type="text" class="form-control member-form-input" id="username" name="username" value="<?php echo $user['username']; ?>">
                                </div>
                            </div>
                            <div class="row mb-3 form-group required">
                                <label class="col-sm-1 control-label member-form-labels">Eamil Address</label>
                                <div class="col-sm-2">
                                    <input type="email" class="form-control member-form-input" id="email" name="email" value="<?php echo $user['email']; ?>">
                                </div>
                            </div>
                            <div class="row mb-3 form-group">
                                                                
                            </div>
                                
                        </form>
                        <div align="center" class="box-footer">
                            <button type="submit" form="accInfoForm" class="btn btn-primary member-form-submit">Save</button>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="connections" role="tabpanel">
                    <div class="box bg-dark member-connections">
                        <table id="latestMachines" class="table text-light table-dark">
                            <thead>
                                <tr>
                                    <th>VM Name</th>
                                    <th>IP</th>
                                    <th>OS</th>
                                    <th>CPU</th>
                                    <th>RAM</th>
                                    <th>Storage</th>
                                    <th>Uptime</th>
                                    <th>Status</th>
                                    <th>Node</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                    $statColor = array('running' => '#009933', 'stopped' => '#bf9000', 'unknown' => '#cc0000');
                                    $osColor   = array('mswindows' => '#009933', 'kali' => '#45818e', 'linux' => '#bf9000', 'missing agent' => '#cc0000');

                                    foreach ($owned_vms as $vm) {

                                        $connInfo = $vm['conn'];
                                        $upSec   = str_pad($vm['uptime'] % 60, 2, '0', STR_PAD_LEFT);
                                        $upMins  = str_pad(floor(($vm['uptime']% 3600) / 60), 2, '0', STR_PAD_LEFT);
                                        $upHours = str_pad(floor(($vm['uptime']% 86400) / 3600), 2, '0', STR_PAD_LEFT);
                                        $upDays  = str_pad(floor(($vm['uptime']% 2592000) / 86400), 2, '0', STR_PAD_LEFT);

                                        ?>

                                        <tr>
                                            <td><?php echo $vm['name']; ?></td>
                                            <td><?php echo $vm['conn']; ?></td>
                                            <td style='color:<?php echo $osColor[$vm['os']]; ?> !important'><?php echo $vm['os']; ?></td>
                                            <td><?php echo $vm['cpus']; ?></td>
                                            <td><?php echo $vm['maxmem']; ?></td>
                                            <td><?php echo $vm['disk']; ?></td>
                                            <td>&nbsp; <?php echo "{$upDays}D {$upHours}:{$upMins}:{$upSec}"; ?></td>
                                            <td style='color:<?php echo $statColor[$vm['status']]; ?> !important'><?php echo $vm['status']; ?></td>
                                            <td><?php echo $vm['node']; ?></td>
                                            <td class="machine-col">
                                                <div class="dropdown">
                                                    <button class="fa-solid fa-sliders p-2 bg-dark text-light border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    </button>
                                                    
                                                    <!-- <a class="fa-bars p-2" type="button" id="vm-<?php echo $vm['vmid']; ?>-menu" data-bs-toggle="dropdown" aria-expanded="false"></a> -->
                                                    <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="vm-<?php echo $vm['vmid']; ?>-menu">
                                                    <?php if ($vm['status'] == 'running') { ?>
                                                        <li><a class="dropdown-item" href="<?php echo $serverBase; ?>/servers.php?data=<?php echo $requestHandler->protect(array('quickact', 'stop', $vm['vmid'])); ?>">Stop</a></li>
                                                        <li><a class="dropdown-item" href="<?php echo $serverBase; ?>/servers.php?data=<?php echo $requestHandler->protect(array('quickact', 'suspend', $vm['vmid'])); ?>">Pause</a></li>
                                                    <?php } else if ($vm['status'] == 'paused') { ?>
                                                        <li><a class="dropdown-item" href="<?php echo $serverBase; ?>/servers.php?data=<?php echo $requestHandler->protect(array('quickact', 'resume', $vm['vmid'])); ?>">Resume</a></li>
                                                    <?php } else { ?>
                                                        <li><a class="dropdown-item" href="<?php echo $serverBase; ?>/servers.php?data=<?php echo $requestHandler->protect(array('quickact', 'start', $vm['vmid'])); ?>">Start</a></li>

                                                    <?php } ?>
                                                    <li><a class="dropdown-item" href="<?php echo $serverBase; ?>/servers.php?data=<?php echo $requestHandler->protect(array('quickact', 'revert', $vm['vmid'])); ?>">Revert</a></li>
                                                    <li><a class="dropdown-item" href="<?php echo $serverBase; ?>/servers.php?data=<?php echo $requestHandler->protect(array('quickact', 'delete', $vm['vmid'])); ?>">Remove</a></li>
                                                    <li><a type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#vm-mng-<?php echo $vm['vmid']; ?>">Snapshot Management</a></li>
                                                    <?php if ($vm['status'] == 'running') { ?>
                                                        <div class="dropdown-divider"></div>
                                                        <li>
                                                        <form id="<?php echo $vm['vmid'];?>-conn" action="<?php echo $serverBase; ?>/connect.php" method="POST">
                                                            <input type="hidden" name="data" value="<?php echo $requestHandler->protect($vm['token']); ?>">
                                                            <input id="connection_width" type="hidden" name="width" value="1024">
                                                            <input id="connection_height" type="hidden" name="height" value="720">
                                                            <input type="submit" form="<?php echo $vm['vmid'];?>-conn" class="dropdown-item" value="Console">
                                                        </form>
                                                        </li>
                                                    <?php } ?>
                                                    </ul>
                                                </div>
                                                <div class="modal fade" id="vm-mng-<?php echo $vm['vmid']; ?>" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="vm-mng-<?php echo $vm['vmid']; ?>Label" aria-hidden="true">
                                                    <div id="vm-mng-diag<?php echo $vm['vmid']; ?>" class="modal-dialog modal-dialog-centered">
                                                        <div class="modal-content bg-dark ">
                                                            <div class="modal-header border-dark">
                                                                <h5 class="modal-title text-light" id="vm-mng-<?php echo $vm['vmid']; ?>Label">VM-<?php echo $vm['vmid']; ?> Snapshot Manager</h5>
                                                                <button type="button" class="btn-close text-muted" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="container"> 
                                                                    <form class="row" id="vm-snap-<?php echo $vm['vmid']; ?>" name="frm-snap" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                                                                        <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('snapman', $_SESSION['username'], $vm['vmid'])); ?>"> 
                                                                        <div class="input-group">
                                                                            <span class="input-group-text" id="basic-addon1">Select an Action</span>
                                                                            <select id="vmSnapMng-action-<?php echo $vm['vmid']; ?>" name="action" class="form-select">
                                                                            <option selected></option>
                                                                            <option value="create">Create</option>
                                                                            <option value="delete">Delete</option>
                                                                            </select>
                                                                        </div>
                                                                        <div id="vm-snap-<?php echo $vm['vmid']; ?>-create-menu" class="row pt-4 mx-auto"> 
                                                                            <div class="form-floating mb-3">
                                                                            <input type="text" class="form-control" id="floatingInput" placeholder="Enter snapshot name" name="snapname" value="">
                                                                            <label class="text-dark ps-3" for="floatingInput">Snapshot name</label>
                                                                            </div>
                                                                            <div class="form-floating">
                                                                            <textarea class="form-control" placeholder="Enter snapshot description" id="floatingTextarea" name="description" value=""></textarea>
                                                                            <label class="text-dark ps-3" for="floatingTextarea">Description</label>
                                                                            </div>
                                                                        </div>
                                                                        <div id="vm-snap-<?php echo $vm['vmid']; ?>-delete-menu" class="row pt-4"> 
                                                                            <div class="col"> 
                                                                            <div class="input-group mb-1">
                                                                                <span class="input-group-text" id="basic-addon1">Select Snapshot</span>
                                                                                <select id="vmSnapMng-remove-<?php echo $vm['vmid']; ?>" name="snapname_del" class="form-select">
                                                                                <option value="" selected></option>
                                                                                <?php foreach($vm['snaps'] as $snap) { 
                                                                                    if ($snap->name != 'current') { ?>
                                                                                    <option value="<?php echo $snap->name; ?>"><?php echo $snap->name; ?></option>
                                                                                <?php }} ?>
                                                                                </select>
                                                                            </div>
                                                                            </div>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer border-dark mx-auto">
                                                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-danger" form="vm-snap-<?php echo $vm['vmid']; ?>">Submit</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td> 
                                        </tr>
                                <?php
                                    }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
// delete a user
} else if ($action_get == 'delete') { 


} else if ($action_get == 'add') {


// Default action, load the user list!
} else { 
?>
        <div id="memberListBox" class="box">
            <div class="box-header">
                <h3 class="box-title">Members</h3>
                <span style="float:right">
                    <button class="btn btn-admin-panel pull-right" data-bs-toggle="modal" data-bs-target="#createUser">
                        Create User
                    </button>
                </span>
            </div>
            <div class="box-body no-padding" style="min-height:200px">
                <?php 
                    $allUsers = $userHandler->getAllUsers();
                    if ($allUsers != null && count($allUsers) > 0) { 
                ?>
                <table class="table text-light table-dark">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Username</th>
                            <th>Email Address</th>
                            <th>Creation Date</th>
                            <th>Last Login</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                        foreach($allUsers as $user) { 
                    ?>
                        <tr style="vertical-align: middle">
                            <td>
                                <img width="34" height="34" class="img-circle" avatar="<?php echo $user['username']; ?>" alt="<?php echo $user['username']; ?>">
                            </td>
                            <td><?php echo $user['username']; ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td><?php echo $user['reg_date']; ?></td>
                            <td><?php echo $user['login_date']; ?></td>
                            <th>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu bg-dark">
                                        <li><a class="dropdown-item" href="<?php echo $_SERVER['PHP_SELF']; ?>?action=edit&id=<?php echo $user['id']; ?>">Edit</a></li>
                                        <li>
                                            <form id="deletion_form_<?php echo $user['id']; ?>" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                                                <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'delete', $user['id'])); ?>">
                                            </form>
                                            <a class="dropdown-item" href="#" onclick="document.getElementById('deletion_form_<?php echo $user['id']; ?>').submit();">Delete</a>
                                        </li>
                                    </ul>
                                </div>
                            </th>
                        </tr>
                    <?php } ?>
                    </tbody>
                
                <?php } else { ?>
                    <div class="alert alert-info" role="alert">
                        No users found!
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- user creation modal -->
    <div class="modal fade" id="createUser" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="createUser-Label" aria-hidden="true">
        <div id="createUser-diag" class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark ">
                <div class="modal-header border-dark">
                    <h5 class="modal-title text-light" id="createUser-Label">Create User</h5>
                    <button type="button" class="btn-close text-muted" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container"> 
                        <form class="row" id="createUser-form" name="frm-snap" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                            <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('newuser', $_SESSION['username'])); ?>"> 
                            <div id="usercreate-menu" class="row pt-4 mx-auto"> 
                                <div class="row mb-3 form-group required">
                                    <label class="col-sm-3 control-label member-form-labels">Display Name</label>
                                    <div class="col-sm-7">
                                        <input type="text" class="form-control member-form-input" id="username" name="username">
                                    </div>
                                </div>
                                <div class="row mb-3 form-group required">
                                    <label class="col-sm-3 control-label member-form-labels">Email Address</label>
                                    <div class="col-sm-7">
                                        <input type="email" class="form-control member-form-input" id="email" name="email">
                                    </div>
                                </div>
                                <div class="row mb-3 form-group required">
                                    <label class="col-sm-3 control-label member-form-labels">Password</label>
                                    <div class="col-sm-7">
                                        <input type="password" class="form-control member-form-input" id="password" name="password">
                                    </div>
                                </div>
                                <div class="row mb-3 form-group required">
                                    <label class="col-sm-3 control-label member-form-labels">Confirm Password</label>
                                    <div class="col-sm-7">
                                        <input type="password" class="form-control member-form-input" id="conf_password" name="conf_password">
                                    </div>
                                </div>

                            </div>
                        </form>
                    </div>
                </div>
                <div class="modal-footer border-dark mx-auto">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" form="createUser-form">Submit</button>
                </div>
            </div>
        </div>
    </div>  
        
<?php } ?>
