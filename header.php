<?php
    $include    = "1";
    $debug      = "0";
    
    require($_SERVER['DOCUMENT_ROOT']."/extra/classes.php");

    $root       = $_SERVER['DOCUMENT_ROOT'];
    $serverBase = ($INFO['proxy'] ? 'https://' : (empty($_SERVER['HTTPS']) ? 'http://' : 'https://')).$_SERVER['HTTP_HOST']; 

    $loginHanlder   = new GuacLoginHandler();
    $proxmox        = new Proxmox();
    $guacamole      = new GaucamoleHandler($INFO);
    $requestHanlder = new RequestHandler();

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $nodes             = $proxmox->getNodes()->data;
    $running_nodes = array();

    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true) {
        if ($nodes) { 
            foreach ($nodes as $node) { 
                if ($node->status == "online") { 
                    array_push($running_nodes, array(
                        'name' => $node->node,
                        'status' => $node->status,
                        'id' => $node->id
                    ));
                }
            }
        }

        if (!empty($running_nodes)) {
            $owned_vms         = $proxmox->getOwnedVMs($_SESSION['username'], $running_nodes);
            $templates         = $proxmox->getTemplates('temp');
        } else { 
            $errorMSG = "There are no nodes online, please contact an administrator.";
            include $_SERVER['DOCUMENT_ROOT']."/extra/error.php";
            die();
        }
    }
?>