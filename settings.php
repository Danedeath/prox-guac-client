<?php 

include './header.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { 
  header("location: ./login/login.php");
  exit;
}

if (!filter_var($user_perms['profile'],FILTER_VALIDATE_BOOLEAN)) {
    $errorMSG = "You do not have permission to access this page!";
    include 'extra/error.php';
    die();
}

$user_info = $userHandler->getUserByName($_SESSION['username']);

if (isset($_POST['data']) && $_POST['data'] != '') { // data being sent with a post, read it to determine the action requested!
    $data = $requestHandler->unprotect($_POST['data']);

    $state = $data[0];
    $type  = $data[1];

    if ($state != $_SESSION['state']) { 
        $errorMSG = "An error has occurred. Please try again.";
        include 'extra/error.php';
        exit();
    }

    if ($type == 'action') { 

        $action  = $data[2];
        $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
                        
        $old_user = explode(":!:!", end($data));
        $old_user = $userHandler->getUserbyID($old_user[0]);

        if ($old_user['id'] != $user_info['id']) {
            $errorMSG = "You cannot perform this action on others!";
            include 'extra/error.php';
            exit();
        }

        if ($username != $user_info['username']) { 

           if (strcmp($_SESSION['username'], $username) != 0 && $userHandler->getUserByName($username)) { 
                $errorMSG = "Username $username is already in use, unable to update user ".$user_info['username']."!";
                include 'extra/error.php';
                exit();
            }

            // provided email and username are not being used by another user, lets update the user now...
            $new_userData = array(
                'id'       => $user_info['id'],
                'username' => $username,
                'email'    => $user_info['email'],

            );

            $update_result = $userHandler->updateUser($new_userData);

            if (array_key_exists('status', $update_result) && $update_result['status'] == 'success') { 
                $alert_msg = array(
                    'status' => 'success',
                    'message'  => 'Successfully updated the user <strong>'.$username.'</strong>!<br>Please note that the user will need to re-login to the system to see the changes.'
                );
            } else {
                $errorMSG = "An error has occurred while updating the user ".$user_info['username']."!";
                include 'extra/error.php';
                exit();
            }

        }

        $password = $_POST['password']; 
        $password_conf = $_POST['password_conf'];

        if (strcmp($password, $password_conf) == 0 && strcmp($password, $user_info['password']) != 0)  {// passwords match, go ahead and update to a new one!
            $userHandler->updatePassword($user_info['id'], $password);
        }
    }
} ?>
<script src="./admin/extra/letteravatar.js"></script>
<link href="https://gitcdn.github.io/bootstrap-toggle/2.2.2/css/bootstrap-toggle.min.css" rel="stylesheet">
<script src="https://gitcdn.github.io/bootstrap-toggle/2.2.2/js/bootstrap-toggle.min.js"></script>

<div role="main" class="container-fluid" style="max-width:1800px">
    <?php 
    
        if (isset($alert_msg)) { ?>
            <div class="alert alert-<?php echo ($alert_msg['status'] == 'success') ? 'success' : 'danger'; ?> alert-dismissible" role="alert"> 
                <?php echo $alert_msg['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" id="alertmsg"></button>
            </div>
        <?php }
        
        include './diag/user_diag.php';
    ?>
</div>