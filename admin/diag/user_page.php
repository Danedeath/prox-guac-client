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

if (isset($alert_msg)) { ?>
    <div class="alert alert-<?php echo ($alert_msg['status'] == 'success') ? 'success' : 'danger'; ?> alert-dismissible" role="alert"> 
        <?php echo $alert_msg['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" id="alertmsg"></button>
    </div>
<?php }

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
                            Last Active <?php echo explode(' ', $user['last_login'])[0]; ?>
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
                            <?php if (filter_var($user_perms['userman_reset'],FILTER_VALIDATE_BOOLEAN)) { ?>
                                <li style="color: #ddd;padding-left: 10px;padding-top:10px;">
                                    <a href="?data=<?php echo $requestHandler->protect(array('action', 'reset', $user['id'])); ?>">Reset Password</a>
                                </li>
                            <?php } ?>
                            <li style="color: #ddd;padding-left: 10px;padding-top:10px;">
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
                <a id="info-tab" data-bs-target="#acc_info" data-bs-toggle="tab" aria-expanded="true" type="button" class="active">User Information</a>
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
                    <?php  if (filter_var($user_perms['userman_edit'],FILTER_VALIDATE_BOOLEAN)) { ?>
                        <form id="accInfoForm" name="accInfoForm" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                            <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'edituser', implode(':!:', $user)));?>">
                    <?php } ?>
                            <div class="row mb-3 form-group required">
                                <label class="col-sm-1 control-label member-form-labels">Display Name</label>
                                <div class="col-sm-2">  
                                    <?php  if (filter_var($user_perms['userman_edit'],FILTER_VALIDATE_BOOLEAN)) { ?>                              
                                        <input type="text" class="form-control member-form-input" id="username" name="username" value="<?php echo $user['username']; ?>">
                                    <?php } else { ?>
                                        <input type="text" class="form-control member-form-input" id="username" name="username" value="<?php echo $user['username']; ?>" disabled>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="row mb-3 form-group required">
                                <label class="col-sm-1 control-label member-form-labels">Email Address</label>
                                <div class="col-sm-2">
                                    <?php  if (filter_var($user_perms['userman_edit'],FILTER_VALIDATE_BOOLEAN)) { ?>                              
                                        <input type="email" class="form-control member-form-input" id="email" name="email" value="<?php echo $user['email']; ?>">
                                    <?php } else { ?>
                                        <input type="email" class="form-control member-form-input" id="email" name="email" value="<?php echo $user['email']; ?>" disabled>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php  if (filter_var($user_perms['userman_reset'],FILTER_VALIDATE_BOOLEAN) && filter_var($user_perms['userman_edit'],FILTER_VALIDATE_BOOLEAN)) { ?>
                                <div class="row mb-3 form-group">
                                    <label class="col-sm-1 control-label member-form-labels">Password</label>
                                    <div class="col-sm-2">
                                        <input type="password" class="form-control member-form-input" id="password" name="password" value="">
                                    </div>                                
                                </div>
                                <div class="row mb-3 form-group required">
                                    <label class="col-sm-1 control-label member-form-labels">Confirm Password</label>
                                    <div class="col-sm-2">
                                        <input type="password" class="form-control member-form-input" id="password_conf" name="password_conf" value="">
                                    </div>
                                </div>
                            <?php } ?>
                    <?php  if (filter_var($user_perms['userman_edit'],FILTER_VALIDATE_BOOLEAN)) { ?>
                        </form>
                        <div align="center" class="box-footer">
                            <button type="submit" form="accInfoForm" class="btn btn-primary member-form-submit">Save</button>
                        </div>
                    <?php } ?>
                    </div>
                </div>
                <?php if (filter_var($user_perms['userman_conn'],FILTER_VALIDATE_BOOLEAN)) { ?>
                <div class="tab-pane fade" id="connections" role="tabpanel">
                    <div class="box bg-dark member-connections">
                        <?php include $root.'/admin/templates/connections_table.php'; ?>
                    </div>
                </div>
                <?php } if (filter_var($user_perms['userman_perm'],FILTER_VALIDATE_BOOLEAN)) { ?>
                <div class="tab-pane collapse" id="permissions" role="tabpanel">
                    <div class="box box-body member-form">
                        <form id="userPermsForm" name="userPermsForm" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                            <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'editperms', $user['id']));?>">
                            <div class="row col-md-12"> 
                                <div class="col-md-2">
                                <?php 
                                $perms = $userHandler->getUserPerms($user['id']);
                                $count = 0;
                                foreach($perms as $key => $value) {
                                    if (!is_numeric($key) && $key != 'id' && $key != 'user_id') {
                                        if ($count == 10) { ?> </div><div class="col-md-2"> <?php $count = 0;}
                                        $count++;
                                ?>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="perm_<?php echo $key; ?>" name="perm_<?php echo $key; ?>" <?php echo ($value == 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="flexSwitchCheckDefault"><?php echo $permHandler->getPermWord($key); ?></label>
                                    </div>
                                    <?php 
                                    }
                                    } 
                                    if ($count < 10) { ?> </div><?php }
                                    ?>
                            </div>
                        </form>
                        <div align="center" class="box-footer">
                            <button type="submit" form="userPermsForm" class="btn btn-primary member-form-submit">Save</button>
                        </div>
                    </div>
                </div>
                <?php } ?>
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
            <div class="box-body no-padding" style="min-height:200px; padding-bottom:50px;">
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
                            <td><?php echo $user['last_login']; ?></td>
                            <th>
                                <div class="btn-group" style="float:right">
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
                                        <li>
                                        <?php if ($user['is_locked'] == 1) { ?> 
                                            <li>
                                                <form id="unlock_form_<?php echo $user['id']; ?>" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                                                    <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'unlock', $user['id'])); ?>">
                                                </form>
                                                <a class="dropdown-item" href="#" onclick="document.getElementById('unlock_form_<?php echo $user['id']; ?>').submit();">Unlock</a>
                                            </li>
                                        <?php } ?>
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
