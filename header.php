<?php
    $include    = "1";
    $debug      = "0";
    
    require($_SERVER['DOCUMENT_ROOT']."/extra/classes.php");

    $root       = $_SERVER['DOCUMENT_ROOT'];
    $serverBase = ($INFO['proxy'] ? 'https://' : (empty($_SERVER['HTTPS']) ? 'http://' : 'https://')).$_SERVER['HTTP_HOST']; 

    $proxmox        = new Proxmox();
    $guacamole      = new GaucamoleHandler($INFO);
    $requestHandler = new RequestHandler();

    $dbLogin        = new LoginHandler($DB);
    $userHandler    = new UserHandler($DB);
    $permHandler    = new PermissionHandler($DB);
    $roleHandler    = new roleHandler($DB);


    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // collect the IP address of the user for the session!
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP']) && $_SERVER['HTTP_CF_CONNECTING_IP'] != '') {
        $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] != '') {
        $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else { 
        $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
    }

    $nodes         = $proxmox->getNodes()->data;
    $running_nodes = array();

    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] == true) {

        $user          = $userHandler->getUserByName($_SESSION['username']);
        $user_perms    = $permHandler->getPermissions($user['id']);  

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
<?php if (!stripos($_SERVER['PHP_SELF'], 'login') && !stripos($_SERVER['PHP_SELF'], 'connect')) { ?>
    <!DOCTYPE html>
    <html>
    <title>Dashboard</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="./extra/style.css"  type="text/css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Raleway">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-iYQeCzEYFbKjA/T2uDLTpkwGzCiq6soy8tYaI1GyVh/UjpbCx/TYkiZhlZB6+fzT" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-u1OknCvxWvY5kfmNBILK2hRnQC3Pr17a+RTT6rIHI7NnikvbZlHgTPOOmMi466C8" crossorigin="anonymous"></script>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js" ></script>
    <script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/jquery-ui.min.js"></script>‌​
    <body class="bg-dark text-light">

        <!-- Top container -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark pe-5 ps-5">
            <div class="container-fluid">
                <a class="navbar-brand fs-3" href="index.php">Guacamole Dashboard</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent"></div>
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="fs-4 nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Welcome, <?php echo $_SESSION["username"]; ?>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end me-5" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="settings.php"> Edit settings </a></li>
                            <?php if (filter_var($user_perms['profile'],FILTER_VALIDATE_BOOLEAN)) { ?>
                                <li><a class="dropdown-item" href="admin/index.php"> Admin Panel </a></li>
                            <?php } ?>
                            <li><a class="dropdown-item" href="<?php echo $INFO['cape_sandbox']; ?>"> Cape Sandbox </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../login/logout.php"> Logout </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
<?php } ?>