<?php

include './header.php';

if(!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) { 
    header("location: ./login/login.php?next=settings");
    exit;
}

if (empty($running_nodes)) {
    $errorMSG = "No running nodes were discovered!";
    include $root."/extra/error.php";
    die();
}

// check for permissions
if (!filter_var($user_perms['settings'],FILTER_VALIDATE_BOOLEAN)) {
    $errorMSG = "You do not have permission to access this page!";
    include 'extra/error.php';
    die();
}

if (isset($_POST['data']) && $_POST['data'] != '') { // data being sent with a post, read it to determine the action requested!

    $data = $requestHandler->unprotect($_POST['data']);

    $state = $data[0];
    $type  = $data[1];

    if ($state != $_SESSION['state']) {  // check for csrf, the state is a cyrptographically secure random string
        $errorMSG = "An error has occurred. Please try again.";
        include 'extra/error.php';
        exit();
    }

    // check if the user has the permission to modify settings
    if (!filter_var($user_perms['sett_edit'],FILTER_VALIDATE_BOOLEAN)) {
        $errorMSG = "You do not have permission to that operation!";
        include 'extra/error.php';
        die();
    }

    if ($type == 'action') { 

        $new_settings = array();
        $sett_names   = $settHandler->getSettingNames();
        $action = $data[2];

        if (isset($sett_names['status'])) {
            $errorMSG = "An error has occurred. Please try again.";
            include 'extra/error.php';
            exit();
        }

        if ($action == 'edit') { 

            foreach ($sett_names as $name) { // collect all the settings from the post request
                if (isset($_POST[$name])) {
                    $temp = explode('_', $name);
                    switch(end($temp)) { // encrypt settings that need to be encrypted...
                        case 'token':
                        case 'key':
                        case 'secret':
                        case 'password':
                            $new_settings[$name] = $requestHandler->encryptData($_POST[$name]);
                            break;
                        case 'login': 
                        case 'proxy':
                        case 'online':
                            $new_settings[$name] = (int) filter_var($_POST[$name], FILTER_VALIDATE_BOOLEAN);
                            break;
                        default:
                            $new_settings[$name] = $_POST[$name];
                            break;
                    }
                } else {
                    $new_settings[$name] = '';
                }
            }

            $alert_msg = $settHandler->updateSettings($new_settings); // update the settings in the database
        }    
    }
}

?>
        <?php include "./diag/settings_page.php"; ?>
    </div>
</div>