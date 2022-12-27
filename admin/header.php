<?php
    $include    = "1";
    $debug      = "0";
    
    require($_SERVER['DOCUMENT_ROOT']."/extra/classes.php");

    $root       = $_SERVER['DOCUMENT_ROOT'];
    $serverBase = ($INFO['proxy'] ? 'https://' : (empty($_SERVER['HTTPS']) ? 'http://' : 'https://')).$_SERVER['HTTP_HOST']; 

    $loginHanlder   = new GuacLoginHandler();
    $proxmox        = new Proxmox();
    $guacamole      = new GaucamoleHandler($INFO);
    $requestHandler = new RequestHandler();
    $userHandler    = new UserHandler($DB_login);
    $settings       = new SettingsHandler($DB_login);
    $dbLogin        = new LoginHandler($DB_login);
    $connManager    = new ConnectionManager($DB_login);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $nodes         = $proxmox->getNodes()->data;
    $running_nodes = array();

    if (isset($_SESSION['admin_loggedin']) && $_SESSION['admin_loggedin'] == true) {
        
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
?>
<!DOCTYPE html>
<html>
    <head> 
        <meta charset="utf-8">
        <title>Admin Panel</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Raleway">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/css/bootstrap.min.css">
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-u1OknCvxWvY5kfmNBILK2hRnQC3Pr17a+RTT6rIHI7NnikvbZlHgTPOOmMi466C8" crossorigin="anonymous"></script>
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js" ></script>
        <script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/jquery-ui.min.js"></script>‌​
        
        <link rel=stylesheet href="<?php echo $serverBase."/admin/extra/style.css?".time(); ?>" type="text/css">

        <script src="extra/admin.min.js"></script>
        <script>
            jQuery(document).ready(function ($) {
                // Get current path and find target link
                var path = window.location.pathname.split("/").pop();

                var target = $('li a[href="' + path + '"]');

                if (target.length == 0) {
                    target = $('a[href="' + path + '"]');
                    target.addClass('active');

                } else { 
                    target.closest('.treeview').addClass('menu-open');
                    target.parent().parent().parent().children().first().addClass('active');
                    target.addClass('active');
                }
            });
        </script>
        <style>
            .menu-open .treeview-menu {
                display: block;
            }
        </style>
    </head>
    <body class="text-light" style="overflow-x: hidden">
        
        <nav id="main-navbar" class="navbar navbar-expand-lg fixed-top navbar-lightblue">
            <!-- Container wrapper -->
            <div class="container-fluid">
                <a class="navbar-brand" href="">
                    <b>Proxmox Admin Panel</b>
                </a>  

                <ul class="navbar-nav ms-auto d-flex flex-row">
                    <li class="nav-item me-3 me-lg-0">
                        <a class="nav-link" href="<?php echo $serverBase; ?>/servers.php" target="_blank">
                            <i class="fa fa-home"></i>
                            Back to Site
                        </a>
                    </li>

                    <li class="nav-item me-3 me-lg-0">
                        <a class="nav-link" href="https://github.com/Danedeath/prox-guac-client" target="_blank">
                            <i class="fab fa-github"></i>
                        </a>
                    </li>

                    <!-- Avatar -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle hidden-arrow d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo $_SESSION['username']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end bg-dark" aria-labelledby="navbarDropdownMenuLink">
                            <li>
                                <a class="dropdown-item" href="#">My profile</a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#">Settings</a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="login/logout.php">Logout</a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
        <div class="container-fluid">
            <div class="row w-80">
                <div class="col-sm-3 col-md-2 sidebar sidebar-darkblue">
                    <nav id="sidebarMenu" class="collapse d-lg-block sidebar collapse">
                        <ul class="sidebar-menu list-group list-group-flush" data-widget="tree">
                            <a href="index.php" class="list-group-item list-group-item-action py-2 ripple mt-4" aria-current="true" id="homePage">
                                <i class="fas fa-tachometer-alt fa-fw me-3"></i>
                                <span>Overview</span>
                            </a>
                            <a href="connections.php" class="list-group-item list-group-item-action py-2 ripple" id="connectionsPage">
                                <i class="fas fa-chart-area fa-fw me-3"></i>
                                <span>Connections</span>
                            </a>
                            <li class="treeview">
                                <a href="#" class="list-group-item list-group-item-action py-2 ripple">
                                    <i class="fas fa-users fa-fw me-3"></i>
                                    <span>User Management</span>
                                </a>
                                <ul class="treeview-menu ms-4">
                                    <li>
                                        <b style="color:white; align:center;">Users</b>
                                    </li>
                                    <li class="pb-3">
                                        <a href="userman.php">Manage Users</a>
                                    </li>
                                    <li>
                                        <b style="color:white; align:center;">Role Management</b>
                                    </li>
                                    <li>
                                        <a href="groups.php">Groups</a>
                                    </li>
                                </ul>
                            </li>
                            <a href="settings.php" class="list-group-item list-group-item-action py-2 ripple">
                                <i class="fas fa-gears fa-fw me-3"></i>
                                <span>Settings</span>
                            </a>                               
                        </ul>
                    </nav>
                </div>               
<?php
    }
?>