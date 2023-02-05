<?php 

if(!isset($include)) { die('Direct access not permitted'); }

function endsWith($haystack,$needle,$case=false) {
    if($case){return (strcmp(substr($haystack, strlen($haystack) - strlen($needle)),$needle)===0);}
    return (strcasecmp(substr($haystack, strlen($haystack) - strlen($needle)),$needle)===0);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['data'])) { 

        $data = $requestHandler->unprotect(filter_var($_POST['data'], FILTER_SANITIZE_STRING));
        $state = $data[0];
        $type  = $data[1];

        if ($state != $_SESSION['state']) { 
            $errorMSG = "Your session does not match. Please relogin.";
            include 'error.php';
            exit();
        }

        if ($type == 'snapman') { // snapshot creation requested, go ahead and create one using the provided data...

            $snapname       = filter_var($_POST['snapname'], FILTER_SANITIZE_STRING);
            $action         = isset($_POST['action']) ? filter_var($_POST['action'], FILTER_SANITIZE_STRING) : '';
            $description    = isset($_POST['description']) ? filter_var($_POST['description'], FILTER_SANITIZE_STRING) : '';
            $vmid           = intval($_POST['vmid']);
            $vm             = $proxmox->getVM($vmid);

            if (!empty($action)) { 
                $invalid = False;
                switch($action) { 
                    case 'create': $proxmox->createSnap($vmid, $snapname); break;
                    default: $invalid = True; break;
                }

                if ($invalid) { 
                    $alert_msg = array(
                        'danger',
                        'Error:',
                        "The action '{$action}' is invalid!"
                    );
                } else { 
                    $alert_msg = array(
                        'success',
                        'Success!',
                        "The snapshot '{$snapname}' for VM {$vm['name']} ".(!endsWith($action, 'e') ? "was successfully {$action}ed.": "was successfully {$action}d.")
                    );
                }
            }

            $data = $_POST['return_data']; 
            $data = $requestHandler->unprotect(filter_var($data, FILTER_SANITIZE_STRING));
            array_shift($data);
            array_push($data, $alert_msg[0], $alert_msg[1], $alert_msg[2]);
            $data = $requestHandler->protect($data);
            header("Location: connect.php?data=".$data);

        } else if ($type == 'conn_start') { 
            $temp = $data;
            $data = array(
                'state'  => $temp[0],
                'action' => $temp[1],
                'title'  => $temp[2],
                'server' => $INFO['guacd_host'],
                'port'   => $INFO['guacd_port'],
                'token'  => $temp[3],
                'vmid'   => $temp[4], 
                'node'   => $temp[5]
            );
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    

    if (isset($_GET['data'])) { 

        $data  = $requestHandler->unprotect(filter_var($_GET['data'], FILTER_SANITIZE_STRING));
        $state = $data[0];
        $type  = $data[1];
        $vmid  = null;

        if ($type == 'quickact') { 

            $owned_vmids = array();
            foreach ($owned_vms as $vm) { 
                array_push($owned_vmids, $vm['vmid']);
            }

            $vmid = intval($data[3]);
            $vm_name = $proxmox->getVMConfig($vmid)->name;

            if (in_array($vmid, $owned_vmids)) { 
                
                if ($proxmox->getNode($vmid) != NULL) { 
                    $compare = NULL;
                    switch($data[2]) { 
                        case 'revert':  $proxmox->revertVM($vmid);            $compare = 'running'; break;
                        case 'start':   $proxmox->startVM($data[3],   $vmid); $compare = 'running'; break;
                        case 'stop':    $proxmox->stopVM($data[3],    $vmid); $compare = 'stopped'; break;
                        case 'restart': $proxmox->rebootVM($data[3],  $vmid); $compare = 'running'; break;
                        case 'resume':  $proxmox->resumeVM($data[3],  $vmid); $compare = 'running'; break;
                        case 'suspend': $proxmox->suspendVM($data[3], $vmid); $compare = 'paused'; break;
                    }
    
                    $status  = $proxmox->getVMStatus($data[3], $vmid);

                    $alert_msg = array(
                        'success',
                        'Sucess!',
                        !endsWith($data[2], 'e') ? " The VM '$vm_name'was successfully {$data[2]}ed.": " The VM '$vm_name' was successfully {$data[2]}d."
                    );

                } else { 
                    $alert_msg = array(
                        'danger',
                        'Error',
                        'There was an issue processing the {$data[2]} request for VM {$vmid}.'
                    );
                }
            } else { 
                $alert_msg = array(
                    'danger',
                    'Error',
                    'You do not have permission to {$data[2]} the VM {$vmid}.'
                );
            } 

            $data = end($data); 
            $data = $requestHandler->unprotect(filter_var($data, FILTER_SANITIZE_STRING));
            array_shift($data);
            array_push($data, $alert_msg[0], $alert_msg[1], $alert_msg[2]);
            var_dump($data);
            $data = $requestHandler->protect($data);
            header("Location: connect.php?data=".$data);

        } else if ($type == 'conn_start') { 
            $temp = $data;
            $data = array(
                'state'  => $temp[0],
                'action' => $temp[1],
                'title'  => $temp[2],
                'server' => $INFO['guacd_host'],
                'port'   => $INFO['guacd_port'],
                'token'  => $temp[3],
                'vmid'   => $temp[4], 
                'node'   => $temp[5], 
                'vmid'   => $vmid,
                'alert'  => array(
                    'color' => $temp[6],
                    'title'  => $temp[7],
                    'msg'   => $temp[8]
                )
            );
        }
    }
}

?>