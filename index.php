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

if (!empty($running_nodes)) { 
    
    $running_vms = 0;
    $online_nodes = count($running_nodes);
    $alert_msg = array();
    $owned_vmids = array();
    
    // sort the array of owned virtual machines by their respective VMID's
    usort($owned_vms, function($a, $b) {
              
        return $a['vmid'] > $b['vmid'];
    });

    foreach ($owned_vms as $vm) { 
        if ($vm['status'] == 'running') { 
            $running_vms += 1;
        }
        array_push($owned_vmids, $vm['vmid']);
    }

    $stats = array(
        array('name' => 'Online Nodes', 'value' => floor($online_nodes / count($nodes) * 100)),
        array('name' => 'Running VMs',  'value' => floor($running_vms / count($owned_vms) * 100)),
        array('name' => 'Total VMs',    'value' => floor(count($owned_vms) / $INFO['max_instances'] * 100))
    );

    $header_bar = array(
      
      'machines_on' => array(
          'icon' => 'fa-cubes',
          'color' => ((count($owned_vms) == $running_vms) > 2) ? 'success' : (((count($owned_vms) - $running_vms) < 1) ? 'danger' : 'warning'),
          'value' => $running_vms,
          'title' => 'Active Machines'
        ),
      'free_slots' => array(
          'icon' => 'fa-eye',
          'color' => (($INFO['max_instances'] - count($owned_vms)) < 2) ? 'warning' : ((($INFO['max_instances'] - count($owned_vms)) < 1) ? 'danger' : 'success'),
          'value' => $INFO['max_instances'] - count($owned_vms),
          'title' => 'Free Machines'
        ),
      'your_slots' => array(
          'icon' => 'fa-share-alt',
          'color' => 'info',
          'value' => $INFO['max_instances'],
          'title' => 'Maxmium Machines'
        ),
      'nodes' => array(
          'icon'  => 'fa-network-wired',
          'color' => (count($nodes) != count($running_nodes)) ? 'danger' : 'success',
          'value' => count($running_nodes),
          'title' => 'Online Nodes'
        )
      ); 
    
    include $root."/extra/serverfuncs.php";   
    ?>

    <!-- CONTENT -->
    <div role="main" class="container-xl">
      <div class="row margin-bottom pb-5 pt-5">
        <?php foreach ($header_bar as $header) { ?>
          <div class="col-lg-3">
            <div class="d-flex flex-column h-100 p-3 bg-<?php echo $header['color']; ?> rounded border-<?php echo $header['color']; ?>" style="max-height:138px !important">
                <div class="d-inline-flex">
                  <i class="p-1 fa <?php echo $header['icon']; ?>" style="font-size:36px !important" ></i>
                  <h2 class="p-1" ><?php echo $header['value']; ?></h2>
                </div>
                <h4><?php echo $header['title']; ?></h4>
            </div>
          </div>
        <?php } ?>
      </div>
      <?php 
        include $root.'/extra/servernots.php'; 
      ?>
      <?php if ($online_nodes == 0) { ?>
        <div class="alert alert-danger" role="alert">
          <h4 class="alert-heading">No nodes are online!</h4>
          <p>There are no nodes online, please contact an administrator.</p>
        </div>
      <?php } ?>
      
      <div class="row margin-bottom pb-5">
        <div class="d-flex">
          <h5 class="justify-content-start" ><i class="fa fa-laptop p-3"></i>Virtual Machines</h5>
          <button type="button" class="btn btn-sm btn-primary justify-content-end" style="margin-left:auto; max-height: 75%;" data-bs-toggle="modal" data-bs-target="#createVMModal">Create VM</button>
        </div>

        <div class="p-3" style="margin:0 -16px">
          <div class="container">
            <table class="table table-dark">
              <?php foreach ($owned_vms as $vm) { 

                // check if machine is available in guacamole
                $connInfo = $vm['conn'];
              ?>
                <tr class="machine-row" id="vm-<?php echo $vm['vmid']; ?>">
                  <td class="machine-col"><i class="fa fa-user text-primary p-3"></i>&nbsp; <?php echo $vm['name']; ?></td>
                  <td class="machine-col"><i class="fa fa-server text-primary p-3"></i>&nbsp; <?php echo $vm['node']; ?></td>
                  <td class="machine-col"><i class="fa fa-microchip text-primary p-3"></i>&nbsp; <?php echo $vm['cpus']." Cores" ; ?></td>
                  <td class="machine-col"><i class="fa fa-memory text-primary p-3"></i>&nbsp; <?php echo $vm['maxmem']."GB" ; ?></td>
                  <td class="machine-col"><i class="fa fa-clock text-primary p-3"></i>&nbsp; <?php echo $vm['uptime']; ?></td>
                  <td class="machine-col"><i class="fa fa-signal text-<?php echo ($vm['status'] == 'running') ? 'success' : (($vm['status']) == 'stopped' ? 'danger' : 'warning'); ?> p-3"></i>&nbsp; <?php echo $vm['status']; ?></td>
                  <td class="machine-col">
                    <div class="dropdown">
                      <button class="fa-solid fa-sliders p-2 bg-dark text-light border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                      </button>
                      <!-- <a class="fa-bars p-2" type="button" id="vm-<?php echo $vm['vmid']; ?>-menu" data-bs-toggle="dropdown" aria-expanded="false"></a> -->
                      <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="vm-<?php echo $vm['vmid']; ?>-menu">
                        <?php if ($vm['status'] == 'running') { ?>
                          <li><a class="dropdown-item" href="?data=<?php echo $requestHandler->protect(array('quickact', 'stop', $vm['vmid'])); ?>">Stop</a></li>
                          <li><a class="dropdown-item" href="?data=<?php echo $requestHandler->protect(array('quickact', 'suspend', $vm['vmid'])); ?>">Pause</a></li>
                        <?php } else if ($vm['status'] == 'paused') { ?>
                          <li><a class="dropdown-item" href="?data=<?php echo $requestHandler->protect(array('quickact', 'resume', $vm['vmid'])); ?>">Resume</a></li>
                        <?php } else { ?>
                          <li><a class="dropdown-item" href="?data=<?php echo $requestHandler->protect(array('quickact', 'start', $vm['vmid'])); ?>">Start</a></li>

                        <?php } ?>
                        <li><a class="dropdown-item" href="?data=<?php echo $requestHandler->protect(array('quickact', 'revert', $vm['vmid'])); ?>">Revert</a></li>
                        <li><a class="dropdown-item" href="?data=<?php echo $requestHandler->protect(array('quickact', 'delete', $vm['vmid'])); ?>">Remove</a></li>
                        <li><a type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#vm-mng-<?php echo $vm['vmid']; ?>">Snapshot Management</a></li>
                        <?php if ($vm['status'] == 'running') { ?>
                          <div class="dropdown-divider"></div>
                          <li>
                            <form id="<?php echo $vm['vmid'];?>-conn" action="connect.php" method="POST">
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
                            <h5 class="modal-title text-light" id="vm-mng-<?php echo $vm['vmid']; ?>Label">VM-<?php echo $vm['vmid']; ?> 190 Manager</h5>
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
            </table>
          </div>
        </div>
      </div>

      <!-- Status Progress bars -->
      <div class="row margin-bottom pb-5">
        <h5><i class="fa fa-bell p-3"></i>Statistics</h5>
        <div class="p-3" style="margin:0 -16px">
          <div class="container">
            <?php foreach ($stats as $stat) { ?> 
              <div class="progress bg-dark" style="height: 55px">
                <div class="progress-bar bg-primary" style="width: <?php echo $stat['value']; ?>%" role="progressbar" aria-valuenow="<?php echo $stat['value']; ?>" aria-valuemin="0" aria-valuemax="100">
                  <?php if ($stat['value'] > 10) { ?>
                    <?php echo $stat['name']; ?>: <?php echo $stat['value']; ?>%
                  <?php } ?>
                </div>
                <div class="progress-bar bg-<?php echo ((100 - $stat['value']) > 50) ? 'danger' : 'warning'; ?>" style="width: <?php echo 100 - $stat['value']; ?>%" role="progressbar" aria-valuenow="<?php echo 100 - $stat['value']; ?>" aria-valuemin="0" aria-valuemax="100">
                  <?php if ($stat['value'] < 10) { ?>
                    <?php echo $stat['name']; ?>: <?php echo $stat['value']; ?>%
                  <?php } ?>
                </div>
              </div>
              <br>
            <?php } ?>
          </div>
        </div>
      </div>

      <!-- Create VM Modal -->
      <div class="modal fade" id="createVMModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="createVMModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
          <div class="modal-content bg-dark text-light">
            <div class="modal-header border-dark">
              <h5 class="modal-title" id="createVMModalLabel">VM Creation</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="container"> 
                <form class="row g-3" id="frm_clone_temp" name="frm_clone_temp" action="<?= $_SERVER['PHP_SELF'] ?>" method="post">
                  <div class="input-group">
                    <span class="input-group-text" id="basic-addon1">Select Template</span>
                    <select id="temp_id" name="temp_id" class="form-select" onchange=>
                      <option selected></option>
                      <?php 
                        foreach ($templates as $template) {
                          echo '<option value="'.$template['vmid'].'">'.$template['name'].'</option>';
                        }
                      ?> 
                    </select>
                  </div>
                  <div id="vm-create-menuform" class="row pt-4 mx-auto"> 
                    <div class="col-md-6 form-floating mb-3">
                      <input class="form-control" id="vm_clone_name" name="vm_name" placeholder="">
                      <label for="vm_name" class="text-dark ps-3">Name</label>
                    </div>
                    <div class="col-md-6 form-floating mb-3">
                      <div class="form-floating">
                        <select class="form-select" id="core_count" name="core_count" aria-label="number of cores for the VM">
                          <option value="2">2 Cores</option>
                          <option value="4">4 Cores</option>
                          <option value="6" selected>6 Cores</option>
                          <option value="8">8 Cores</option>
                          <option value="10">10 Cores</option>
                        </select>
                        <label for="core_count" class="text-dark ps-3">Core Count</label>
                      </div>
                    </div>
                    <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('createVM', $_SESSION['username'], $vm['vmid'])); ?>"> 
                  </div>
                </form>
              </div>
            </div>
            <div class="modal-footer border-dark mx-auto">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-primary" form="frm_clone_temp">Submit</button>
            </div>
          </div>
        </div>
      </div>
      <hr>
      <br>
    </div>
    <?php } ?>
    <script> 
      $("#vm-create-menuform").addClass('d-none');
      $('#temp_id').on('change',function(){
          if( $(this).val()!==""){
            $("#vm-create-menuform").removeClass('d-none');
          } else {
            $("#vm-create-menuform").addClass('d-none');
          }
      });

      function getWidth() {
        return Math.max(
          document.body.scrollWidth,
          document.documentElement.scrollWidth,
          document.body.offsetWidth,
          document.documentElement.offsetWidth,
          document.documentElement.clientWidth
        );
      }

      function getHeight() {
        return Math.max(
          document.body.scrollHeight,
          document.documentElement.scrollHeight,
          document.body.offsetHeight,
          document.documentElement.offsetHeight,
          document.documentElement.clientHeight
        );
      }

      $('#connection_width').val(getWidth());
      $('#connection_height').val(getHeight());
      
    </script>
    <script> 
      <?php 
      foreach ($owned_vmids as $vm) { 
        echo '
        $("#vm-snap-'.$vm.'-create-menu").addClass("d-none");
        $("#vm-snap-'.$vm.'-delete-menu").addClass("d-none");
        $("#vmSnapMng-action-'.$vm.'").on("change",function(){
          if ($(this).val() === "create") {
            $("#vm-snap-'.$vm.'-create-menu").removeClass("d-none");
            $("#vm-snap-'.$vm.'-delete-menu").addClass("d-none");
          } else if ($(this).val() === "delete") {
            $("#vm-snap-'.$vm.'-create-menu").addClass("d-none");
            $("#vm-snap-'.$vm.'-delete-menu").removeClass("d-none");
          } else { 
            $("#vm-snap-'.$vm.'-create-menu").addClass("d-none");
            $("#vm-snap-'.$vm.'-delete-menu").addClass("d-none");
          }
        });';
      }
      ?>
    </script>    
  </body>
</html>