<?php 
// disable direct access...
if(!isset($include)) { die('Direct access not permitted'); }

// alert status bar!
if (isset($alert_msg)) { 
    if ($alert_msg['status'] == 'success') {
        ?> <div class="alert alert-success" role="alert"> <?php echo $alert_msg['message']; ?> </div> <?php
    } else {
        ?> <div class="alert alert-danger" role="alert"> <?php echo $alert_msg['message']; ?> </div> <?php
    }
}

// build the table of connections...
$connections = $connManager->getConnections();

if (isset($_GET['action']) && $_GET['action'] == 'edit') { // display the connection modification page, otherwise display all connections

    $reqConn = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

    $conn = $connManager->getConnection($reqConn);

    if ($conn == null) {
        $alert_msg = array('status' => 'error', 'message' => '<strong>Connection not found</strong>, unable to display the requested connection!');
    } 

?>  
    <script src="extra/letteravatar.js"></script>
    <div class="col-md-12">
        <div class="box row" style="height:180px">
            <div class="col-md-10 box-widget widget-user-2">
                <div class="widget-user-header">
                    <div class="widget-user-image" style="width:100px;">
                        <img width="100" height="100" style="border: 3px solid #d2d6de; width:100; border-radius: 5%; margin-top: 20px;" avatar="<?php echo $conn['name']; ?>">
                    </div>
                    <h1 class="widget-user-username">
                        <strong><?php echo $conn['name']; ?></strong>
                    </h1>
                    <p class="widget-user-desc">
                        <?php echo $conn['username']; ?>
                    </p>
                    <p class="widget-user-desc text-muted" style="margin-top: 15px;">
                        Modified <?php echo explode(' ', $conn['modified'])[0]; ?>
                    </p>
                    <p class="widget-user-desc text-muted" style="margin-top: -15px;">
                        Last Active <?php echo explode(' ', $conn['lastactive'])[0]; ?>
                    </p>
                </div>
            </div>
            <div class="col-md-2 box-widget widget-user-2">
                <div class="widget-user-header btn-group dropstart" style="float:right">
                    <button type="button" class="btn  btn-danger btn-flat dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        Connection Actions
                    </button>
                    <ul class="dropdown-menu text-light" style="background-color: #2f343d">
                        <li style="color: #ddd;padding-left: 10px;">
                            <a href="?data=<?php echo $requestHandler->protect(array('action', 'suspend', $conn['id'])); ?>">Suspend</a>
                        </li>
                        <li style="color: #ddd;padding-left: 10px;">
                            <form id="deletion_form_<?php echo $conn['id']; ?>" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                                <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'delete', $conn['id'])); ?>">
                            </form>
                            <a href="#" onclick="document.getElementById('deletion_form_<?php echo $conn['id']; ?>').submit();">Delete</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="nav-tabs-custom">
        <ul class="nav nav-tabs" role="tablist" id="memberTabs">
            <li class="nav-item Tabs_item">
                <a id="acc-tab" data-bs-toggle="tab" data-bs-target="#acc_info" type="button" role="tab" aria-controls="user-info-tab" aria-selected="true">Connection Information</a>
            </li>
        </ul>
    </div>
    <div class="col-md-12">
        <div class="tab-content" style="margin-left: -10px !important;">
            <div class="tab-pane fade show active" id="acc_info" role="tabpanel">
                <div class="box box-body member-form">
                    <form id="accInfoForm" name="accInfoForm" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                        <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'update', json_encode($conn)));?>">
                        <div class="row mb-3 form-group required">
                            <label class="col-sm-3 control-label member-form-labels">Connection Name</label>
                            <div class="col-sm-2">
                                <input type="text" class="form-control member-form-input" id="name" name="name" value="<?php echo $conn['name']; ?>">
                            </div>
                        </div>
                        <div class="row mb-3 form-group required">
                            <label class="col-sm-3 control-label member-form-labels">User Account</label>
                            <div class="col-sm-2">
                                <input type="text" class="form-control member-form-input" id="username" name="username" value="<?php echo $conn['username']; ?>">
                            </div>
                        </div>
                        <div class="row mb-3 form-group required">
                            <label class="col-sm-3 control-label member-form-labels">Host</label>
                            <div class="col-sm-2">
                                <input type="text" class="form-control member-form-input" id="host" name="host" value="<?php echo $conn['host']; ?>">
                            </div>
                        </div>
                        <div class="row mb-3 form-group required">
                            <label class="col-sm-3 control-label member-form-labels">Password</label>
                            <div class="col-sm-2">
                                <input type="password" class="form-control member-form-input" id="password" name="password" value="<?php echo $conn['password']; ?>">
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
        </div>
    </div>
<?php
} else {

?>
    <div id="connListBox" class="box">
        <div class="box-header">
            <h3 class="box-title">Connections</h3>
            <span style="float:right">
                <button class="btn btn-admin-panel pull-right" data-bs-toggle="modal" data-bs-target="#createConn">
                    Create Connection
                </button>
            </span>
        </div>
        <div class="box-body no-padding" style="min-height:200px">
            <?php 
                if ($connections != null && count($connections) > 0) { 
            ?>
            <table class="table text-light table-dark">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Hostname</th>
                        <th>Owner</th>
                        <th>Protocol</th>
                        <th>Operating System</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($connections as $conn) { ?> 
                        <tr>
                            <td><?php echo $conn['name']; ?></td>
                            <td><?php echo $conn['hostname']; ?></td>
                            <td><?php echo $userHandler->getUserByID($conn['owner'])['username']; ?></td>
                            <td><?php echo $conn['protocol']; ?></td>
                            <td><?php echo $conn['os']; ?></td>
                            <td>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu bg-dark">
                                        <li><a class="dropdown-item" href="<?php echo $_SERVER['PHP_SELF']; ?>?action=edit&id=<?php echo $conn['id']; ?>">Edit</a></li>
                                        <li>
                                            <form id="deletion_form_<?php echo $conn['id']; ?>" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                                                <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'delete', $conn['id'])); ?>">
                                            </form>
                                            <a class="dropdown-item" href="#" onclick="document.getElementById('deletion_form_<?php echo $conn['id']; ?>').submit();">Delete</a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <?php } else { ?>
                <div class="alert alert-info" role="alert">
                    No Connections found!
                </div>
            <?php } ?>
        </div>
    </div>
<?php
}
?>
<!-- connection creation modal -->
<div class="modal fade" id="createConn" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="createConn-Label" aria-hidden="true">
    <div id="createConn-diag" class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark ">
            <div class="modal-header border-dark">
                <h5 class="modal-title text-light" id="createConn-Label">Create Connection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="container">
                    <form class="row" id="createConn-form" name="form-create" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                        <input type="hidden" class="form-control member-form-input" id="data" name="data" value="<?php echo $requestHandler->protect(array('action', 'create')); ?>">
                        <div id="vm-create-menuform" class="row pt-4 mx-auto"> 
                            <div class="col-md-6 form-floating mb-3">
                                <input class="form-control" id="name" name="name" placeholder="">
                                <label class="control-label required text-dark ps-3">Name</label>
                            </div>
                            <div class="col-md-6 form-floating mb-3">
                                <input class="form-control" id="host" name="host" placeholder="">
                                <label class="control-label required text-dark ps-3">Host</label>
                            </div>
                            <div class="col-md-6 form-floating mb-3">
                                <input class="form-control" id="port" name="port" placeholder="">
                                <label class="control-label required text-dark ps-3">Port</label>
                            </div>
                            <div class="col-md-6 form-floating mb-3">
                                <select class="form-select" id="protocol" name="protocol" aria-label="protocol of the VM">
                                    <option value="rdp" selected>Remote Desktop</option>
                                </select>
                                <label class="control-label required text-dark ps-3">Protocol</label>
                            </div>
                            <div class="col-md-6 form-floating mb-3">
                                <input class="form-control" id="username" name="username" placeholder="">
                                <label class="control-label required text-dark ps-3">Username</label>
                            </div>
                            <div class="col-md-6 form-floating mb-3">
                                <input type="password" class="form-control" id="password" name="password" placeholder="">
                                <label class="control-label required text-dark ps-3">Password</label>
                            </div>
                            <div class="col-md-6 form-floating mb-3">
                                <select class="form-select" id="os" name="os" aria-label="operating system of the VM">
                                    <option value=""></option>
                                    <option value="mswindows">Windows</option>
                                    <option value="linux">Linux</option>
                                    <option value="macos">MacOS</option>
                                    <option value="kali">Kali</option>
                                    <option value="ubuntu">Ubuntu</option>
                                    <option value="debian">Debian</option>
                                    <option value="other">Other</option>
                                </select>
                                <label class="control-label required text-dark ps-3">Operating System</label>
                            </div>
                            <div class="col-md-6 form-floating mb-3">
                                <select class="form-select" id="owner" name="owner" aria-label="the owner of the new connection">
                                    <option value=""></option>
                                    <?php foreach ($userHandler->getAllUsers() as $user) { ?>
                                        <option value="<?php echo $user['id']; ?>"><?php echo $user['username']; ?></option>
                                    <?php } ?>
                                </select>
                                <label class="control-label required text-dark ps-3">Owner</label>
                            </div>
                            <div class="col-md-6 form-floating mb-3">
                                <div class="form-floating">
                                    <select class="form-select" id="node" name="node" aria-label="the node that contains the VM">
                                        <option value=""></option>
                                        <?php foreach ($proxmox->getNodes()->data as $node) { ?>
                                            <option value="<?php $node->node; ?>"><?php echo $node->node; ?></option>
                                        <?php } ?>
                                    </select>
                                    <label for="node" class="text-dark ps-3">Node</label>
                                </div>
                            </div>
                            <div class="col-md-6 form-floating mb-3">
                                <input class="form-control" id="drive" name="drive" placeholder="" value="<?php echo $INFO['guacd_drive']; ?>">
                                <label for="drive" class="text-dark ps-3">Shared Drive</label>
                            </div>
                        </div>   
                    </form>
                </div>  
            </div>
            <div class="modal-footer border-dark mx-auto">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" form="createConn-form">Submit</button>
            </div>
        </div>
    </div>
</div>