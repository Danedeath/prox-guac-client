<?php 

if(!isset($include)) { die('Direct access not permitted'); }

// collect actions and groupIDs if needed
$action_get = (isset($_GET['action'])) ? filter_var($_GET['action'], FILTER_SANITIZE_STRING) : '';
$roleID = (isset($_GET['id'])) ? filter_var($_GET['id'], FILTER_SANITIZE_STRING) : '';
$role   = NULL;

$breadcrumbs = array(
    'Role Management' => 'roleman.php'
);


if ($action_get != '') { 

    // obtain the group being modified, if it does exist
    $role = $roleHandler->getRole($roleID);

    if (empty($role)) {
        $errorMSG = "No role found with that ID!";
        $returnPage = "roleman.php";
        include "extra/error.php";
        die();
    }     

    $breadcrumbs['Editing '.$role['name']] = 'userman.php?action=edit&id=' . $roleID;
    $roleUsers = $roleHandler->getRoleUsers($roleID);
}


?>
    <script src="extra/letteravatar.js"></script>
    <link href="https://gitcdn.github.io/bootstrap-toggle/2.2.2/css/bootstrap-toggle.min.css" rel="stylesheet">
    <script src="https://gitcdn.github.io/bootstrap-toggle/2.2.2/js/bootstrap-toggle.min.js"></script>
    <div class="row position-absolute main-body">
        <section class="content-header pull-right">
            <h2>
                Role Management
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
        
        if ($action_get == 'edit') { ?>
            <div class="col-md-12">
                <div class="box row" style="height:180px">
                    <div class="col-md-10 box-widget widget-user-2">
                        <div class="widget-user-header">
                            <div class="widget-user-image" style="width:100px;">
                                <img width="100" height="100" style="border: 3px solid #d2d6de; width:100; border-radius: 5%; margin-top: 20px;" avatar="<?php echo $role['name']; ?>">
                            </div>
                            <h1 class="widget-user-username">
                                <strong><?php echo $role['name']; ?></strong>
                            </h1>
                            <p class="widget-user-desc">
                                <?php echo $role['description']; ?>
                            </p>
                            <p class="widget-user-desc text-muted" style="margin-top: 15px;">
                                Created <?php echo explode(' ', $role['creation'])[0]; ?>
                            </p>
                            <p class="widget-user-desc text-muted" style="margin-top: -15px;">
                                Modified <?php echo explode(' ', $role['modified'])[0]; ?>
                            </p>
                        </div>
                    </div>
                    <div class="col-md-2 box-widget widget-user-2">
                        <div class="widget-user-header btn-group dropstart" style="float:right">
                            <button type="button" class="btn  btn-danger btn-flat dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                Role Actions
                            </button>
                            <ul class="dropdown-menu text-light" style="background-color: #2f343d">
                                <li style="color: #ddd;padding-left: 10px;">
                                    <form id="deletion_form_<?php echo $role['id']; ?>" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                                        <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'delete', $role['id'])); ?>">
                                    </form>
                                    <a href="#" onclick="document.getElementById('deletion_form_<?php echo $role['id']; ?>').submit();">Delete</a>
                                </li>
                                <li style="color: #ddd;padding-left: 10px;padding-top:10px;">
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#addUsers_<?php echo $role['id']; ?>"> Add Members</a>
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
                    <a id="info-tab" data-bs-target="#info" data-bs-toggle="tab" aria-expanded="true" type="button" class="active">Role Information</a>
                </li>
                <li class="nav-item Tabs_item">
                    <a id="members-tab" data-bs-target="#members" data-bs-toggle="tab" aria-expanded="true" type="button">Members</a>
                </li>
                <li class="nav-item Tabs_item">
                    <a id="perms-tab" data-bs-target="#permissions" data-bs-toggle="tab" aria-expanded="true" type="button">Permissions</a>
                </li>
            </ul>
        </div>

        <div class="row pe-5">
            <div class="col-md-12">
                <div class="tab-content" style="margin-left: -10px !important;">
                    <div class="tab-pane collapse active" id="info" role="tabpanel">
                        <div class="box box-body member-form">
                            <form id="roleInfoForm" name="roleInfoForm" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                                <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'edituser', json_encode($role)));?>">
                                <div class="row mb-3 form-group required">
                                    <label class="col-sm-1 control-label member-form-labels">Name</label>
                                    <div class="col-sm-2">                                
                                        <input type="text" class="form-control member-form-input" id="username" name="name" value="<?php echo $role['name']; ?>">
                                    </div>
                                </div>
                                <div class="row mb-3 form-group required">
                                    <label class="col-sm-1 control-label member-form-labels">Description</label>
                                    <div class="col-sm-2">
                                        <input type="textarea" class="form-control member-form-input" id="email" name="description" value="<?php echo $role['description']; ?>">
                                    </div>
                                </div>
                            </form>
                            <div align="center" class="box-footer">
                                <button type="submit" form="roleInfoForm" class="btn btn-primary member-form-submit">Save</button>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane collapse" id="members" role="tabpanel">
                        <div class="box bg-dark member-connections" style="padding: 0px 50px 10px 50px;">
                            <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'usersSelection', $role['id'])); ?>">
                            <table id="latestMachines" class="table text-light table-dark">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email Address</th>
                                        <th>Creation Date</th>
                                        <th>Last Login</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($roleHandler->getRoleUsers($role['id']) as $user) { ?>
                                        <tr>
                                            <td><?php echo $user['username']; ?></td>
                                            <td><?php echo $user['email']; ?></td>
                                            <td><?php echo $user['reg_date']; ?></td>
                                            <td><?php echo $user['login_date']; ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu bg-dark">
                                                        <li>
                                                            <a class="dropdown-item" href="userman.php?action=edit&id=<?php echo $user['id']; ?>">Edit</a>
                                                        </li>
                                                        <li>
                                                            <form id="user_remove_form<?php echo $user['id']; ?>" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                                                                <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'removeUser', $user['id'], $role['id'])); ?>">
                                                                <input type="hidden" name="role" value="<?php echo $role['id']; ?>">
                                                                <input type="hidden" name="page" value="<?php echo $_SERVER[REQUEST_URI]; ?>">
                                                            </form>
                                                            <a type="submit" class="dropdown-item" href="#" onclick="document.getElementById('user_remove_form<?php echo $user['id']; ?>').submit();">Remove</a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane collapse" id="permissions" role="tabpanel">
                        <div class="box box-body member-form">
                            <form id="rolePermsForm" name="rolePermsForm" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                                <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'editperms'));?>">
                                <input type="hidden" name="role" value="<?php echo $role['id']; ?>">
                                <div class="row col-md-12"> 
                                    <div class="col-md-2">
                                    <?php 
                                    $perms = $roleHandler->getRolePerms($role['id'])[0];
                                    $count = 0;
                                    
                                    foreach($perms as $key => $value) {
                                        if (!is_numeric($key) && $key != 'id' && $key != 'role_id') {
                                            if ($count == 10) { ?> </div><div class="col-md-2"> <?php $count = 0;}
                                            $count++;
                                    ?>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="perm_<?php echo $key; ?>" name="perm_<?php echo $key; ?>" <?php echo ($value == 1) ? 'checked' : 'unchecked'; ?>>
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
                                <button type="submit" form="rolePermsForm" class="btn btn-primary member-form-submit">Save</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php } else { ?>

            <div id="groupListBox" class="box" style="overflow-x: none">
                <div class="box-header">
                    <h3 class="box-title">Groups</h3>
                    <span style="float:right">
                        <button class="btn btn-admin-panel pull-right" data-bs-toggle="modal" data-bs-target="#createRole">
                            Create Group
                        </button>
                    </span>
                </div>
                <div class="box-body no-padding" style="min-height:200px; padding-bottom:50px;">
                    <?php 
                        $allRoles = $roleHandler->getAllRoles();
                        if ($allRoles != null && count($allRoles) > 0) { 
                    ?>
                    <table class="table text-light table-dark">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Name</th>
                                <th>Members</th>
                                <th>Creation Date</th>
                                <th>Modified Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                            foreach($allRoles as $role) { 

                                $members = $roleHandler->getRoleUsers($role['id']);
                        ?>
                            <tr style="vertical-align: middle">
                                <td>
                                    <img width="34" height="34" class="img-circle" avatar="<?php echo $role['name']; ?>" alt="<?php echo $role['name']; ?>">
                                </td>
                                <td><?php echo $role['name']; ?></td>
                                <td><?php echo count($members); ?></td>
                                <td><?php echo $role['creation']; ?></td>
                                <td><?php echo $role['modified']; ?></td>
                                <th>
                                    <div class="btn-group" style="float:right">
                                        <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu bg-dark">
                                            <li><a class="dropdown-item" href="<?php echo $_SERVER['PHP_SELF']; ?>?action=edit&id=<?php echo $role['id']; ?>">Edit</a></li>
                                            <li>
                                                <form id="deletion_form_<?php echo $role['id']; ?>" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                                                    <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'delete', $role['id'])); ?>">
                                                </form>
                                                <a class="dropdown-item" href="#" onclick="document.getElementById('deletion_form_<?php echo $role['id']; ?>').submit();">Delete</a>
                                            </li>
                                        </ul>
                                    </div>
                                </th>
                            </tr>
                        <?php } ?>
                        </tbody>
                    
                    <?php } else { ?>
                        <div class="alert alert-info" role="alert">
                            No roles detected!
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <?php } ?>

        <!-- Role creation modal -->
        <div class="modal fade" id="createRole" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="createRole-Label" aria-hidden="true">
            <div id="createRole-diag" class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content bg-dark ">
                    <div class="modal-header border-dark">
                        <h5 class="modal-title text-light" id="createRole-Label">Create Role</h5>
                        <button type="button" class="btn-close text-muted" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="container"> 
                            <form class="row" id="createRole-form" name="frm-snap" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                                <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'create')); ?>"> 
                                <div id="Rolecreate-menu" class="row pt-4 mx-auto"> 
                                    <div class="row mb-3 form-group required">
                                        <label class="col-sm-3 control-label member-form-labels">Role Name</label>
                                        <div class="col-sm-7">
                                            <input type="text" class="form-control member-form-input" id="name" name="name">
                                        </div>
                                    </div>
                                    <div class="row mb-3 form-group required">
                                        <label class="col-sm-3 control-label member-form-labels">Description</label>
                                        <div class="col-sm-7">
                                            <input type="email" class="form-control member-form-input" id="description" name="description">
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="modal-footer border-dark mx-auto">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" form="createRole-form">Submit</button>
                    </div>
                </div>
            </div>
        </div>  

        <!-- User addition modal --> 
        <div class="modal fade" id="addUsers_<?php echo $role['id']; ?>" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addUsers-Label" aria-hidden="true">
            <div id="addUsers-diag" class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content bg-dark ">
                    <div class="modal-header border-dark">
                        <h5 class="modal-title text-light" id="addUsers-Label">Add User</h5>
                        <button type="button" class="btn-close text-muted" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="container"> 
                            <form class="row" id="addUsers-form" name="frm-snap" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                                <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'addUsers')); ?>"> 
                                <div id="Rolecreate-menu" class="row pt-4 mx-auto"> 
                                    <div class="row mb-3 form-group required">
                                        <label class="col-sm-3 control-label member-form-labels">User</label>
                                        <div class="col-sm-7">
                                            <select class="form-control member-form-input" id="user" name="users[]" multiple="multiple">
                                                <?php foreach ($userHandler->getAllUsers() as $user) { ?>
                                                    <option value="<?php echo $user['id']; ?>"><?php echo $user['username']; ?></option>
                                                <?php } ?>
                                            </select>
                                            <input type="hidden" name="role" value="<?php echo $role['id']; ?>">
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="modal-footer border-dark mx-auto">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" form="addUsers-form">Submit</button>
                    </div>
                </div>
            </div>
        </div>