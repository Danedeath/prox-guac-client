<?php 
// disable direct access...
if(!isset($include)) { die('Direct access not permitted'); }


?>
<table id="latestMachines" class="table text-light table-dark">
    <thead>
        <tr>
            <th>VM Name</th>
            <?php  if (filter_var($user_perms['conn_viewip'],FILTER_VALIDATE_BOOLEAN)) { echo ' <th>IP Address</th>'; } ?>
            <th>OS</th>
            <th>CPU</th>
            <th>RAM</th>
            <th>Storage</th>
            <th>Uptime</th>
            <th>Status</th>
            <?php  if (filter_var($user_perms['conn_node'],FILTER_VALIDATE_BOOLEAN)) { echo ' <th>Node</th>'; } ?>
        </tr>
    </thead>
    <tbody>
        <?php
            $statColor = array('running' => '#009933', 'stopped' => '#bf9000', 'unknown' => '#cc0000');
            $osColor   = array('mswindows' => '#009933', 'kali' => '#45818e', 'linux' => '#bf9000', 'missing agent' => '#cc0000', 'agent not running' => '#cc0000', 'unknown' => '#cc0000');

            foreach ($owned_vms as $vm) {

                $connInfo = $vm['conn'];
                $upSec   = str_pad($vm['uptime'] % 60, 2, '0', STR_PAD_LEFT);
                $upMins  = str_pad(floor(($vm['uptime']% 3600) / 60), 2, '0', STR_PAD_LEFT);
                $upHours = str_pad(floor(($vm['uptime']% 86400) / 3600), 2, '0', STR_PAD_LEFT);
                $upDays  = str_pad(floor(($vm['uptime']% 2592000) / 86400), 2, '0', STR_PAD_LEFT);

                ?>

                <tr>
                <?php 
                    echo "<td>".$vm['name']."</td>";
                    if (filter_var($user_perms['conn_viewip'],FILTER_VALIDATE_BOOLEAN)) { echo "<td>".$vm['conn']."</td>"; }
                    echo "<td style='color:".$osColor[$vm['os']]." !important'>".$vm['os']."</td>";
                    echo "<td>".$vm['cpus']."</td>";
                    echo "<td>".$vm['maxmem']."</td>";
                    echo "<td>".$vm['disk']."</td>";
                    echo "<td>".$vm['uptime']."</td>";
                    echo "<td style='color:".$statColor[$vm['status']]." !important'>".$vm['status']."</td>";
                    if (filter_var($user_perms['conn_node'],FILTER_VALIDATE_BOOLEAN)) { echo "<td>".$vm['node']."</td>"; }
                ?>
                    <td class="machine-col">
                        <div class="dropdown">
                            <button class="fa-solid fa-sliders p-2 bg-dark text-light border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            </button>
                            
                            <!-- <a class="fa-bars p-2" type="button" id="vm-<?php echo $vm['vmid']; ?>-menu" data-bs-toggle="dropdown" aria-expanded="false"></a> -->
                            <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="vm-<?php echo $vm['vmid']; ?>-menu">
                            <?php if (filter_var($user_perms['conn_status'],FILTER_VALIDATE_BOOLEAN)) { ?>

                                <?php if ($vm['status'] == 'running') { ?>
                                    <li><a class="dropdown-item" href="<?php echo $serverBase; ?>/servers.php?data=<?php echo $requestHandler->protect(array('quickact', 'stop', $vm['vmid'])); ?>">Stop</a></li>
                                    <li><a class="dropdown-item" href="<?php echo $serverBase; ?>/servers.php?data=<?php echo $requestHandler->protect(array('quickact', 'suspend', $vm['vmid'])); ?>">Pause</a></li>
                                <?php } else if ($vm['status'] == 'paused') { ?>
                                    <li><a class="dropdown-item" href="<?php echo $serverBase; ?>/servers.php?data=<?php echo $requestHandler->protect(array('quickact', 'resume', $vm['vmid'])); ?>">Resume</a></li>
                                <?php } else { ?>
                                    <li><a class="dropdown-item" href="<?php echo $serverBase; ?>/servers.php?data=<?php echo $requestHandler->protect(array('quickact', 'start', $vm['vmid'])); ?>">Start</a></li>
                                <?php }
                            } ?>
                            <?php if (filter_var($user_perms['conn_revert'],FILTER_VALIDATE_BOOLEAN)) { ?>
                                <li><a class="dropdown-item" href="<?php echo $serverBase; ?>/servers.php?data=<?php echo $requestHandler->protect(array('quickact', 'revert', $vm['vmid'])); ?>">Revert</a></li>
                            <?php } ?>
                            <?php if (filter_var($user_perms['conn_delete'],FILTER_VALIDATE_BOOLEAN)) { ?>
                                <li><a class="dropdown-item" href="<?php echo $serverBase; ?>/servers.php?data=<?php echo $requestHandler->protect(array('quickact', 'delete', $vm['vmid'])); ?>">Remove</a></li>
                            <?php } ?>
                            <?php if (filter_var($user_perms['conn_snap'],FILTER_VALIDATE_BOOLEAN)) { ?>
                                <li><a type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#vm-mng-<?php echo $vm['vmid']; ?>">Snapshot Management</a></li>
                            <?php } ?>
                            <?php if ($vm['status'] == 'running' && filter_var($user_perms['conn_conn'],FILTER_VALIDATE_BOOLEAN)) { ?>
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
                                                    <input type="text" class="form-control" id="floatingInput" placeholder="Enter snapshot name" name="snapname" value="" pattern="[a-zA-Z0-9]+" title="Only alphanumeric characters are allowed.">
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
        <?php } ?>
    </tbody>
</table>
<script> 
    <?php 
    foreach ($owned_vms as $vm) { 
        echo '
        $("#vm-snap-'.$vm['vmid'].'-create-menu").addClass("d-none");
        $("#vm-snap-'.$vm['vmid'].'-delete-menu").addClass("d-none");
        $("#vmSnapMng-action-'.$vm['vmid'].'").on("change",function(){
            if ($(this).val() === "create") {
            $("#vm-snap-'.$vm['vmid'].'-create-menu").removeClass("d-none");
            $("#vm-snap-'.$vm['vmid'].'-delete-menu").addClass("d-none");
            } else if ($(this).val() === "delete") {
            $("#vm-snap-'.$vm['vmid'].'-create-menu").addClass("d-none");
            $("#vm-snap-'.$vm['vmid'].'-delete-menu").removeClass("d-none");
            } else { 
            $("#vm-snap-'.$vm['vmid'].'-create-menu").addClass("d-none");
            $("#vm-snap-'.$vm['vmid'].'-delete-menu").addClass("d-none");
            }
        });';
    }
    ?>
</script> 