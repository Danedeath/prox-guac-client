<?php

include './header.php';

if(!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) { 
    header("location: ./login/login.php");
    exit;
}

if (empty($running_nodes)) {
    $errorMSG = "No running nodes were discovered!";
    include $root."/extra/error.php";
    die();
}

// POST functions for user creation and deletion!
if (isset($_POST['data']) && $_POST['data'] != '') { // data being sent with a post, read it to determine the action requested!
    
    $data = $requestHandler->unprotect($_POST['data']);

    $state = $data[0];
    $type  = $data[1];

    if ($state != $_SESSION['state']) { 
        $errorMSG = "An error has occurred. Please try again.";
        include 'extra/error.php';
        exit();
    }

    if ($type == 'newuser') { // new user requested, lets make one...

        $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
        $password = filter_var($_POST['password'], FILTER_SANITIZE_STRING);
        $email    = filter_var($_POST['email'],    FILTER_SANITIZE_EMAIL);
        
        // check for a valid email address!
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMSG = "Invalid email address!";
            include 'extra/error.php';
            exit();
        }

        if ($password == filter_var($_POST['conf_password'], FILTER_SANITIZE_STRING)) { // passwords match, lets make the user!

            $alert_msg = $dbLogin->registerUser($_POST);
            
        } else {
            $errorMSG = "Passwords do not match!";
            include 'extra/error.php';
            exit();
        }
    }

    else if ($type == 'action') { 

        $action = $data[2];
        $user   = filter_var($data[3], FILTER_SANITIZE_STRING);

        if ($action == 'delete') { // delete user requested, lets delete the user!
            $delete_resp = $userHandler->deleteUser($userHandler->getUserByID($user));
            $alert_msg = $delete_resp;
        }

        else if ($action == 'edituser') { // edit user requested, lets edit the user!

            $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
            $email    = filter_var($_POST['email'],    FILTER_SANITIZE_EMAIL);
                        
            $old_user = explode(":!:!", $data[4]);
            $old_user = $userHandler->getUserbyID($old_user[0]);

            // check if user exists, otherwise we cannot edit the requested user!


            // check for a valid email address!
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorMSG = "Invalid email address!";
                include 'extra/error.php';
                exit();
            }

            if ($email != $user['email'] || $username != $user['username']) { 

                if ($dbLogin->getUser($user['email'])) { 
                    $errorMSG = "Email address $email is already in use, unable to update user ".$user['username']."!";
                    include 'extra/error.php';
                    exit();
                    
                } else if ($userHandler->getUserByName($username)) { 
                    $errorMSG = "Username $username is already in use, unable to update user ".$user['username']."!";
                    include 'extra/error.php';
                    exit();
                }

                // provided email and username are not being used by another user, lets update the user now...
                $new_userData = array(
                    'id'       => $user['id'],
                    'username' => $username,
                    'email'    => $email,

                );

                $update_result = $userHandler->updateUser($new_userData);

                if (array_key_exists('status', $update_result) && $update_result['status'] == 'success') { 
                    $alert_msg = array(
                        'status' => 'success',
                        'message'  => 'Successfully updated the user '.$user['username'].'! Please note that the user will need to re-login to the system to see the changes.'
                    );
                } else {
                    $errorMSG = "An error has occurred while updating the user ".$user['username']."!";
                    include 'extra/error.php';
                    exit();
                }

            }

        }

        else if ($action == 'suspend') { // suspend the login ability of the requested user! 

            // make sure the account making the request isnt being suspended!
            if ($user['id'] == $_SESSION['user_id']) { 
                $alert_msg = array(
                    'status' => 'error',
                    'message'  => 'You cannot suspend your own account!'
                );
            }

            // make sure the account requested actually exists on the system
            $user = $userHandler->getUserByID($user);
            if (!$user) { 
                $alert_msg = array(
                    'status' => 'error',
                    'message'  => 'The requested user does not exist!'
                );
            } else { 
                $userHandler->suspendUser($user);
            }
        }
    }
    
    
}

?>    <div class="row position-absolute main-body text-light">

<?php
include "./extra/user_page.php";
?>
</div>
</div>