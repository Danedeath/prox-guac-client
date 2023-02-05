<?php

include './header.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { 
    header("location: ./login/login.php?next=roleman");
    exit;
}

// check if the user has the correct permissions to access this page
if (!filter_var($user_perms['roles'],FILTER_VALIDATE_BOOLEAN)) {
    $errorMSG = "You do not have permission to access this page!";
    include 'extra/error.php';
    die();
}

if (isset($_POST['role']) && isset($_POST['data'])) { 

    // unprotect the data first, then prepare the data for the action by building all the fields into an array object
    $data = $requestHandler->unprotect($_POST['data']);

    $state   = $data[0];
    $type    = $data[1];
    $action  = $data[2];


    if (isset($_POST['users'])) { $users   = $_POST['users']; }

    if ($state != $_SESSION['state']) { 
        $errorMSG   = "An error has occurred. Please try again.";
        $returnPage = "groupman.php";
        include 'extra/error.php';
        exit();
    }

    if ($type == 'action') { 
        $roleID = filter_var($_POST['role'], FILTER_SANITIZE_NUMBER_INT);
        $role   = $roleHandler->getRole($roleID);
        

        if (!$role) { 
            $alert_msg = array(
                'status' => 'error',
                'message'  => 'The requested role does not exist!'
            );
        } else { 
            switch($action) {         // process the action requested, if the action is invalid, then return an error

                case 'create': 
                    $alert_msg = $roleHandler->createGroup(array('name' => $_POST['name'], 'description' => $_POST['description'])); 
                    break;
                case 'update': 
                    $alert_msg = $roleHandler->updateGroup(array('name' => $_POST['name'], 'description' => $_POST['description']));
                    break;
                case 'delete': 
                    $alert_msg = $roleHandler->deleteGroup(int_val(filter_var($data[3]), FILTER_SANITIZE_NUMBER_INT));
                    break;
                case 'addUsers':
                    $alert_msg = $roleHandler->addUsersRole(
                        $users,
                        $roleID
                    );
                    break;
                case 'removeUser': 
                    $alert_msg = $roleHandler->removeUserRole(
                        $data[3], 
                        $roleID
                    );
                    break;
                case 'editperms': 
                    $role  = $roleHandler->getRole($roleID);
                    $old_perms = $roleHandler->getRolePerms($roleID)[0];
                    $new_perms = array();

                    foreach($old_perms as $key => $value) { 
                        if ($key != 'id' && $key != 'role_id') { 
                            if (isset($_POST['perm_' . $key])) { 
                                $new_perms[$key] = '1';
                            } else { 
                                $new_perms[$key] = '0';
                            }
                        }
                    }
                    
                    $alert_msg = $roleHandler->updateRolePerms(
                        $roleID, $new_perms
                    );
                    break;
                case 'default': 
                    $alert_msg = array('status' => 'error', 'message' => 'An error has occurred, an invalid action was requested.'); break;
            }
        }

        
    }
}

?>

    <?php include "./diag/role_page.php"; ?>
    </div>
</div>