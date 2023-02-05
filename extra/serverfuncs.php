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
            $errorMSG = "An error has occurred. Please try again.";
            include 'error.php';
            exit();
        }

        if ($type == 'snapman') {

            $snapname = !empty($_POST['snapname']) ? filter_var($_POST['snapname'], FILTER_SANITIZE_STRING) : (!empty($_POST['snapname_del']) ? filter_var($_POST['snapname_del'], FILTER_SANITIZE_STRING) : '');
            $action   = isset($_POST['action']) ? filter_var($_POST['action'], FILTER_SANITIZE_STRING) : '';
            $description = isset($_POST['description']) ? filter_var($_POST['description'], FILTER_SANITIZE_STRING) : '';
            $vmid  = intval(end($data));
            $vm = $proxmox->getVM($vmid);

            if (!empty($action)) { 
                $invalid = False;
                switch($action) { 
                    case 'create': $proxmox->createSnap($vmid, $snapname); break;
                    case 'delete': $proxmox->deleteSnap($vmid, $snapname); break;
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
                sleep(10);
                header("Location: servers.php?msg={$requestHandler->protect($alert_msg)}");
            }
        } 

        if ($type == 'createVM') { 

            $temp_id = !empty($_POST['temp_id']) ? filter_var($_POST['temp_id'], FILTER_SANITIZE_STRING) : '';

            // make sure template is a valid template on the server!
            $templates   = $proxmox->getTemplates();
            $template_id = -1;
            $node        = '';

            
            // get the template ID!
            foreach ($templates as $temp) { 
                if ($temp['vmid'] == $temp_id) { 
                    $template_id = $temp['vmid'];
                    $node        = $proxmox->getNode($template_id);
                    break;
                }
            }

            if ($template_id == -1) { 
                $alert_msg = array(
                    'danger',
                    'Error:',
                    "The template '{$template}' is invalid!"
                );
                header("Location: servers.php?msg={$requestHandler->protect($alert_msg)}");
                
            } else { 

                if (!empty($node) && $template_id > 0) { 

                    $new_vmid       = $proxmox->getAvailableVMID();   
                    $vmname         = !empty($_POST['vm_name']) ? filter_var($_POST['vm_name'], FILTER_SANITIZE_STRING) : end(explode('-', $template)).'-'.$_SESSION['username'];
                    $storage        = !empty($_POST['storage']) ? filter_var($_POST['storage'], FILTER_SANITIZE_STRING) : $INFO['default_store'];

                    // get the template's description, which contains the default user/password
                    $template_desc = $proxmox->getVMConfig($template_id, $proxmox->getNode($template_id))->description;

                    $data = array( 
                        'newid'         => $new_vmid,
                        'name'          => (stripos($vmname, '-') === false) ? $vmname.'-'.$_SESSION['username'] : $vmname,
                        'description'   => $template_desc,
                        'node'          => $node,
                        'storage'       => $storage,
                        'full'          => True,
                    );

                    $result = $proxmox->cloneVM($template_id, $node, $data);

                    if (stripos($result, 'upid') != false) { 
                        $alert_msg = array(
                            'success',
                            'Success!',
                            "Creating '{$vmname}', please wait about 10 minutes for the VM to be created."
                        );
                    } else { 
                        $alert_msg = array(
                            'danger',
                            'Error:',
                            "The VM '{$vmname}' could not be created!"
                        );
                    }
                }
            }
        }
    } 
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {

    if (isset($_GET['data'])) { 

        $data = $requestHandler->unprotect(filter_var($_GET['data'], FILTER_SANITIZE_STRING));

        $state  = $data[0];
        $type   = $data[1];

        if ($state != $_SESSION['state']) { 
            $errorMSG = "An error has occurred. Please try again.";
            include 'error.php';
            exit();
        }

        if ($type == 'quickact') { 

            $vmid = intval(end($data));
            $vm_name = $proxmox->getVMConfig($vmid)->name;

            if (in_array($vmid, $owned_vmids)) { 
                
                if ($proxmox->getNode($vmid) != NULL) { 
                    $compare = NULL;
                    switch($data[2]) { 
                        case 'revert':  $proxmox->revertVM($vmid);            $compare = 'running'; break;
                        case 'delete':  $proxmox->deleteVM($data[3],  $vmid); break;
                        case 'start':   $proxmox->startVM($data[3],   $vmid); $compare = 'running'; break;
                        case 'stop':    $proxmox->stopVM($data[3],    $vmid); $compare = 'stopped'; break;
                        case 'restart': $proxmox->rebootVM($data[3],  $vmid); $compare = 'running'; break;
                        case 'resume':  $proxmox->resumeVM($data[3],  $vmid); $compare = 'running'; break;
                        case 'suspend': $proxmox->suspendVM($data[3], $vmid); $compare = 'paused'; break;
                    }
    
                    $status  = $proxmox->getVMStatus($data[3], $vmid);

                    $alert_msg = array(
                        'color' => 'success',
                        'head'   => 'Sucess!',
                        'msg'    => !endsWith($data[2], 'e') ? " The VM '$vm_name'was successfully {$data[2]}ed.": " The VM '$vm_name' was successfully {$data[2]}d."
                    );
                } else { 
                    $alert_msg = array(
                        'color' => 'danger',
                        'head'  => 'Error',
                        'msg'   => 'There was an issue processing the {$data[2]} request for VM {$vmid}.'
                    );
                }
            } else { 
                $alert_msg = array(
                    'color' => 'danger',
                    'head'  => 'Error',
                    'msg'   => 'You do not have permission to {$data[2]} the VM {$vmid}.'
                );
            } 

        } 
        
    }
}
?>