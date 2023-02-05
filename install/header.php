<?php 

// Include the database connection file
require($_SERVER['DOCUMENT_ROOT']."/extra/classes.php");

$requestHandler = new RequestHandler();

// collect the IP address of the user for the session!
if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
} else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else { 
    $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
}


if (session_status() === PHP_SESSION_NONE) {
    session_start();
    $_SESSION['ip']    = $_SERVER['REMOTE_ADDR'];
    $_SESSION['state'] = 'installation';
}
$root       = $_SERVER['DOCUMENT_ROOT'];
$serverBase = ($INFO['proxy'] ? 'https://' : (empty($_SERVER['HTTPS']) ? 'http://' : 'https://')).$_SERVER['HTTP_HOST']; 

?>
<html>
    <head> 
        <meta charset="utf-8">
        <title>Install Panel</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Raleway">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/css/bootstrap.min.css">
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-u1OknCvxWvY5kfmNBILK2hRnQC3Pr17a+RTT6rIHI7NnikvbZlHgTPOOmMi466C8" crossorigin="anonymous"></script>
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js" ></script>
        <script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/jquery-ui.min.js"></script>‌​
        
        <link rel=stylesheet href="<?php echo $serverBase."/admin/extra/style.css?".time(); ?>" type="text/css">

        <script src="extra/admin.min.js"></script>

    </head>
    <body class="text-center bg-dark text-light">
        
        <nav id="main-navbar" class="navbar navbar-expand-lg fixed-top navbar-lightblue">
            <!-- Container wrapper -->
            <div class="container-fluid">
                <a class="navbar-brand server-brand" href="index.php">
                    <b>Proxmox Access Panel</b>
                </a>  

            </div>
        </nav>

