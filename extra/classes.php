<?php

    if(!isset($include)) { die('Direct access not permitted'); }

    include $_SERVER['DOCUMENT_ROOT'].'/extra/dbConfig.php';
    include $_SERVER['DOCUMENT_ROOT'].'/extra/config.php';
    require $_SERVER['DOCUMENT_ROOT'].'/../composer/vendor/autoload.php';

    use Proxmox\Request;
    use Proxmox\Access;
    use Proxmox\Nodes;
    use Proxmox\Cluster;
    use Proxmox\Storage;

    use \Curl\Curl;

    use Duo\DuoUniversal\Client;
    use Duo\DuoUniversal\DuoException;

    class LoginHandler { 

        protected $db;
        protected $pepper;

        function __construct($DB) {
            global $INFO;

            $this->db = $DB; 
            $this->pepper = $INFO['pepper'];

        }

        // this is a private function that will be used to run sql queries. It will return a PDOStatement object or an array containing an error message.
        private function _query($query, $query_params) { 
            $stmt = null;
            try { 
                $stmt = $this->db->prepare($query);
                $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            return $stmt;
        }

        /* 
        * getUser will get the user from the database and return it as an array
        * @param $user - the username of the user
        * @return array - the user as an array
        */
        public function getUser($user) { 
            $user = filter_var($user, FILTER_SANITIZE_STRING);

            $query = 'select * from users where email = :user or username = :user';
            $query_params = array(
                ':user' => $user
            );

            $stmt = $this->_query($query, $query_params);

            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            return $stmt->fetch();
        }

        /* hash the password with the pepper and the salt from the database! 
         * @param $pass - the password to hash
         * return string - the hashed password
        */
        public function hashPass($pass) { 

            // encrypt the combined password!
            $pass = password_hash($pass . $this->pepper, PASSWORD_DEFAULT);

            if ($pass === false) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to hash password!'
                );
            } else { 
                return $pass;
            }
        }

        /* 
        * updateLastLogin will update the last login time of the user
        * @param $user - the username of the user
        */
        public function updateLastLogin($user) { 
            // set the last successful login time in the database!
            $query = 'update users set last_login = :time where username = :user or email = :user';
            $query_params = array(
                ':time' => date('Y-m-d H:i:s', time()),
                ':user' => $user
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                die("Failed to run query: " . $ex->getMessage());
            }
        }

        /* 
        * checkLogin will check the login credentials of the user
        * @param $user - the email or username of the user
        * @param $pass - the password of the user
        * @return boolean - true if the login is successful, false if not
        */
        public function login($user, $pass) { 

            $user = filter_var($user, FILTER_SANITIZE_EMAIL);
            $user_info = $this->getUser($user);
            
            if ($user_info['is_locked'] == 1) { 
                return array(
                    'status' => 'error',
                    'message' => 'Your account has been locked due to too many failed login attempts. Please contact an administrator to unlock your account.'
                );
            }

            // verify the password is correct!
            if (password_verify($pass, $user_info['password'])) { 

                // check if the password needs to be reshashed!
                if (password_needs_rehash($user_info['password'], PASSWORD_DEFAULT)) {

                    $new_hash = $this->hashPass($pass);
                    $this->updatePassword($user, $new_hash);
                }

                $this->updateLastLogin($user);
                $this->unlockLogin($user_info['id']);
                $this->updateSession($user_info['id']);

                return true;

            } else {

                $this->loginAttempt($user_info['id']);

                return array(
                    'status' => 'error',
                    'message' => 'Incorrect username or password!'
                );
            }
        }

        /*
        * logout will log a user out of the system, and remove their session from the database, and destroy the session
        * @param $userid - the id of the user
        */
        public function logout($userid) { 

            unset($_SESSION['username']);
            unset($_SESSION['state']);

            $_SESSION = array();
            // Destroy the session.
            session_destroy();

            $query = 'delete from user_sessions where user_id = :userid and session_id = :session';
            $query_params = array(
                ':userid' => $userid,
                ':session' => session_id()
            );

            $stmt = $this->_query($query, $query_params);

            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }
        }

        public function updateSession($userid) { 

            $query = 'insert into user_sessions (user_id, session_id, user_ip, expires) values (:userid, :session, :ip, :expires)';
            $query_params = array(
                ':userid' => $userid,
                ':session' => session_id(),
                ':ip' => $_SERVER['REMOTE_ADDR'],
                ':expires' => date('Y-m-d H:i:s', time() + 3600)
            );

            $stmt = $this->_query($query, $query_params);

            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }
        }

        /* 
        * updatePassword will update the password of the user
        * @param $user - the username of the user
        * @param $pass - the new password of the user
        */
        public function updatePassword($user, $pass) { 

            // update the password in the database!
            $query = 'update users set password = :pass where username = :user';
            $query_params = array(
                ':pass' => $pass,
                ':user' => $user
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                die("Failed to run query: " . $ex->getMessage());
            }
        }

        /*
        * registerUser will create a new user in the database, if the email is not already in use
        * @param $data - an array containing the user information
        * @return array - the status of the registration
        */
        public function registerUser($data) { 
            $data['username'] = filter_var($data['username'], FILTER_SANITIZE_STRING);
            $data['password'] = $this->hashPass($data['password']);
            $data['email']    = filter_var($data['email'], FILTER_SANITIZE_STRING);           
            
            if ($data['email'] === false) { 
                return array(
                    'status' => 'error',
                    'message' => 'Invalid email address!'
                );
            }

            // check if the email is already in use!
            $existing = $this->getUser($data['email']);


            if ($existing) { 
                return array(
                    'status' => 'error',
                    'message' => 'Email address already in use!'
                );
            } else { 
                if (is_array($data['password'])) { 
                    return $data['password'];
                    
                } else { 
    
                    $query = "insert into users (username, password, email) values (:user, :pass, :email)";
                    $query_params = array(
                        ':user' => $data['username'],
                        ':pass' => $data['password'],
                        ':email' => $data['email']
                    );
    
                    try { 
                        $stmt = $this->db->prepare($query);
                        $result = $stmt->execute($query_params);
                    } catch(PDOException $ex) { 
                        return array(
                            'status' => 'error',
                            'message' => 'Failed to run query: ' . $ex->getMessage()
                        );
                    }
    
                    return array(
                        'status' => 'success',
                        'message' => 'Successfully registered the user, <strong>'.$data['username'].'</strong>!'
                    );
                }
            }           
        }
             
        /* 
        * incrementAttempt will increment by 1 the number of login attempts for the user
        * @param $userID - the id of the user
        */
        private function incrementAttempt($userID) { 

            $query = 'update users set attempts = attempts + 1 where id = :id';
            $query_params = array(
                ':id' => $userID
            );

            $stmt = $this->_query($query, $query_params);

            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            return $stmt->rowCount() > 0;
        }

        /*
        * lockAccount will lock the account of the user
        * @param $userID - the id of the user
        */
        private function lockAccount($userID) { 

            // check if the number of attempts is > 5
            $query = 'update users set locked = 1 where id = :id';
            $query_params = array(
                ':id' => $userID
            );

            $stmt1 = $this->_query($query, $query_params);

            $query = 'update users set lock_date = :date where id = :id';
            $query_params = array(
                ':date' => date('Y-m-d H:i:s', time()),
                ':id' => $userID
            );

            $stmt2 = $this->_query($query, $query_params);

            return $stmt1->rowCount() > 0 && $stmt2->rowCount() > 0;
        }

        /* 
        * loginAttempt will check if the user has more than 5 login attempts, if so, the account will be locked
        * @param $userID - the id of the user
        * @return boolean - true if the account is locked, false if not
        */
        private function loginAttempt($userID) { 
            $this->incrementAttempt($userID);

            $query = 'select attempts from users where id = :id';
            $query_params = array(
                ':id' => $userID
            );

            $stmt = $this->_query($query, $query_params);

            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            $attmpts = $stmt->fetch()['attempts'];

            if ($attmpts >= 5) { 
                return $this->lockAccount($userID);
            }

            return false;
        }

        /*
        * unlockLogin will unlock the account of the user
        * @param $userID - the id of the user
        */
        private function unlockLogin($userID) { 

            $query = 'update users set attempts = 0 where id = :id';
            $query_params = array(
                ':id' => $userID
            );
            $this->_query($query, $query_params);

            $query = 'update users set locked = 0 where id = :id';
            $query_params = array(
                ':id' => $userID
            );
            $this->_query($query, $query_params);

            $query = 'update users set lock_date = null where id = :id';
            $query_params = array(
                ':id' => $userID
            );
            $this->_query($query, $query_params);
        }

    }

    class SettingsHandler { 

        protected $db;

        public function __construct($db) { 
            global $DB;

            if ($db instanceof PDO) { 
                $this->db = $db;
            } else { 
                $this->db = $DB;
            }
        }

        // this is a private function that will be used to run sql queries. It will return a PDOStatement object or an array containing an error message.
        private function _query($query, $query_params) { 
            $stmt = null;
            try { 
                $stmt = $this->db->prepare($query);
                $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            return $stmt;
        }

        public function getSetting($name) { 

            $name = filter_var($name, FILTER_SANITIZE_STRING);

            $query = 'select * from settings where name = :name';
            $query_params = array(
                ':name' => $name
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return false;
            }

            $row = $stmt->fetch();

            if ($row) { 
                return $row['value'];
            } else { 
                return false;
            }
        }

        public function setSetting($name, $value) { 

            $name  = filter_var($name, FILTER_SANITIZE_STRING);
            $value = filter_var($value, FILTER_SANITIZE_STRING);

            $query = 'update settings set value = :value, where name = :name';
            $query_params = array(
                ':name' => $name,
                ':value' => $value
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return false;
            }
            
            return $stmt->rowCount() > 0;
        }

        public function getSettings() { 
            $query = 'select * from settings';

            $stmt = $this->_query($query, array());

            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }
            
            $settings = array();

            foreach($stmt->fetchAll() as $row) { 
                $settings[$row['name']] = $row['value'];
            }

            return $settings;
        }

        public function getSettingNames() { 

            $query = 'select name from settings';

            $stmt = $this->_query($query, array());

            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            $settings = array();
            
            foreach($stmt->fetchAll() as $row) { 
                $settings[] = $row['name'];
            }

            return $settings;
        }

        public function getSettingsByType($cat) { 

            $query = 'select * from settings where `group` like :cat order by `name` asc';
            $query_params = array(
                ':cat' => filter_var($cat, FILTER_SANITIZE_STRING)
            );

            $stmt = $this->_query($query, $query_params);

            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            return $stmt->fetchAll();
        }

        public function getSettingTypes() { 
            $query = 'select distinct `group` from settings order by `group` asc';

            $stmt = $this->_query($query, array());

            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            $types = array();

            foreach($stmt->fetchAll() as $row) { 
                $types[] = $row['group'];
            }

            return $types;
        }

        public function updateSettings($settings) { 

            $query = 'update settings set value = :value where name = :name';
            $updated = array();

            foreach($settings as $name => $value) { 
                $query_params = array(
                    ':name' => filter_var($name, FILTER_SANITIZE_STRING),
                    ':value' => filter_var($value, FILTER_SANITIZE_STRING)
                );
                $stmt = $this->_query($query, $query_params);
                if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                    return $stmt;
                }

                if ($stmt->rowCount() > 0) { 
                    $updated[] = $name;
                }
            }

            if (count($updated) > 0) {
                return array(
                    'status' => 'success',
                    'message' => 'Settings updated successfully.'
                );
            } else {
                return array(
                    'status' => 'error',
                    'message' => 'No settings were updated.'
                );
            }
        }

    }

    class RoleHandler { 

        protected $db;
        protected $userHandler;


        public function __construct($db) { 
            global $DB;

            if ($db instanceof PDO) { 
                $this->db = $db;
            } else { 
                $this->db = $DB;
            }

            $this->userHandler = new UserHandler($db);
        }
        

        // this is a private function that will be used to run sql queries. It will return a PDOStatement object or an array containing an error message.
        private function _query($query, $query_params) { 
            $stmt = null;
            try { 
                $stmt = $this->db->prepare($query);
                $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            return $stmt;
        }

        public function getRole($roleID) { 
            $query = 'select * from roles where id = :roleID';
            $query_params = array(':roleID' => $roleID);

            $stmt = $this->_query($query, $query_params);

            // check if $stmt is an array and contains a status key
            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            return $stmt->fetch();
        }

        public function updateRole($role_data) { 

            $query = 'update roles set name = :name, description = :description where id = :roleID';
            $query_params = array(
                ':name' => $role_data['name'],
                ':description' => $role_data['description'],
                ':roleID' => $role_data['id']
            );

            $stmt = $this->_query($query, $query_params);

            // check if $stmt is an array and contains a status key
            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            if ($stmt->rowCount() == 0) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to update the role <strong>' . $role_data['name'] . '</strong>!'
                );
            }

            return array(
                'status' => 'success',
                'message' => 'Successfully update the role <strong>' . $role_data['name'] . '</strong>!'
            );
        }

        public function deleteRole($roleID) { 
            /* 
            * deleteRole will delete a role from the database. It will also remove the role from any users that have been assigned it.
            * @param $roleID - the ID of the role to be deleted
            * return array
            */

            $count = $this->_countRoles();
            $role  = $this->getRole($roleID);

            // check if $count is an array and contains a status key
            if (is_array($count) && array_key_exists('status', $count)) { 
                return $count;
            }

            if ($count == 1) { 
                return array(
                    'status' => 'error',
                    'message' => 'You cannot delete the only role!'
                );
            }

            $query = 'delete from roles where id = :roleID';
            $query_params = array(':roleID' => $roleID);

            $stmt = $this->_query($query, $query_params);

            // check if $stmt is an array and contains a status key
            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            if ($stmt->rowCount() == 0) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to delete the role!'
                );
            }

            return array(
                'status' => 'success',
                'message' => 'Successfully deleted the role <strong>' . $role['name'] . '</strong>!'
            );

        }

        private function _countRoles() { 

            /* 
            * _countRoles will count the number of roles in the database. This is used to make sure that the last role is not deleted.
            * return number of roles
            */

            // check to make sure the requested role is not the only role.
            $query = 'select count(*) as count from roles';
            $query_params = array();

            $stmt = $this->_query($query, $query_params);

            // check if $stmt is an array and contains a status key
            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            return $stmt->fetch()['count'];
        }

        public function getUserRoles($userID) {
            
            /* 
            * getUserRoles will collect the roles that have been assigned to a specific user.
            * @param $userID - the ID of the user
            * return array, containing the IDs of the roles
            */

            // collect the roles for the user and return an array containing the IDs
            $query = 'select role_id from user_roles where user_id = :userID';
            $query_params = array(':userID' => $userID);

            $stmt = $this->_query($query, $query_params);

            // check if $stmt is an array and contains a status key
            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            $roles = array();
            foreach($stmt->fetchAll() as $role) { 
                array_push($roles, $role['role_id']);
            }

            return $roles;
        }

        public function getRoleUsers($roleID) { 

            /* 
            * getRoleUsers will collect the users that have been assigned a role.
            * @param $roleID - the ID of the role
            * return array
            */

            // collect the users for the role and return an array containing the IDs
            $query = 'select user_id from user_roles where role_id = :roleID';
            $query_params = array(':roleID' => $roleID);

            $stmt = $this->_query($query, $query_params);

            // check if $stmt is an array and contains a status key
            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            $users = array();
            foreach($stmt->fetchAll() as $user) { 
                array_push($users, $this->userHandler->getUserByID($user['user_id']));
            }

            return $users;
        }

        public function getAllRoles() { 
                
            /* 
            * getAllRoles will collect all of the roles in the database.
            * return array
            */

            $query = 'select * from roles';
            $query_params = array();

            $stmt = $this->_query($query, $query_params);

            // check if $stmt is an array and contains a status key
            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            return $stmt->fetchAll();
        }

        public function checkRole($userID, $roleID) { 

            /* 
            * checkRole will check if a user has a role.
            * It will return true if the user has the role and false if the user does not have the role.
            * @param $userID - the ID of the user
            * @param $roleID - the ID of the role
            * return boolean
            */

            $query = 'select * from user_roles where user_id = :userID and role_id = :roleID';
            $query_params = array(
                ':userID' => $userID,
                ':roleID' => $roleID
            );

            $stmt = $this->_query($query, $query_params);

            // check if $stmt is an array and contains a status key
            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            if ($stmt->rowCount() == 0) { 
                return false;
            }

            return true;
        }

        public function addUserRole($userID, $roleID) { 

            /* 
            * addUserRole will add a user to a role.
            * @param $userID - the ID of the user to add to the role
            * @param $roleID - the ID of the role to add the user to
            * @return array - an array containing the status and message
            */

            // check and see if the role actually exists.
            $role = $this->getRole($roleID);
            $user = $this->userHandler->getUserByID($userID);

            if (!is_array($user)) { 
                return array(
                    'status' => 'error',
                    'message' => 'The user does not exist!'
                );
            }

            // check if $role is an array and contains a status key
            if (is_array($role) && array_key_exists('status', $role)) { 
                return $role;
            }

            if ($this->checkRole($userID, $roleID)) { 
                return array(
                    'status' => 'error',
                    'message' => 'The user already has the role <strong>' . $role['name'] . '</strong>!'
                );
            } 

            $query = 'insert into user_roles (user_id, role_id) values (:userID, :roleID)';
            $query_params = array(
                ':userID' => $userID,
                ':roleID' => $roleID
            );

            $stmt = $this->_query($query, $query_params);

            // check if $stmt is an array and contains a status key
            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            if ($stmt->rowCount() == 0) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to add the role <strong>' . $role['name'] . '</strong> to the user <strong>'. $user['username'] . '</strong>!'
                );
            }

            return array(
                'status' => 'success',
                'message' => 'Successfully added the role <strong>' . $role['name'] . '</strong> to the user <strong>'. $user['username'] . '</strong>!'
            );
        }

        public function removeUserRole($userID, $roleID) { 

            /* 
            * removeUserRole will remove a role from a user.
            * @param $userID - the ID of the user to remove the role from
            * @param $roleID - the ID of the role to remove from the user
            * @return array - an array containing the status and message of the operation
            */
            $role = $this->getRole($roleID);
            $user = $this->userHandler->getUserByID($userID);

            if (!is_array($user)) { 
                return array(
                    'status' => 'error',
                    'message' => 'The user does not exist!'
                );
            }

            // check if $role is an array and contains a status key
            if (is_array($role) && array_key_exists('status', $role)) { 
                return $role;
            }

            $query = 'delete from user_roles where user_id = :userID and role_id = :roleID';
            $query_params = array(
                ':userID' => $userID,
                ':roleID' => $roleID
            );

            $stmt = $this->_query($query, $query_params);

            // check if $stmt is an array and contains a status key
            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            if ($stmt->rowCount() == 0) { 
                return array(
                    'status' => 'error',
                    'message' => 'The user <strong>'. $user['username'] . '</strong> does not have the role <strong>' . $role['name'] . '</strong>!'
                );
            } 

            return array(
                'status' => 'success',
                'message' => 'Successfully removed the role <strong>' . $role['name'] . '</strong> from the user <strong>'. $user['username'] . '</strong>!'
            );
        }
    
        public function getRolePerms($roleID) { 
            /* 
            * getRolePerms will get the permissions for a role
            * @param $roleID - the ID of the role
            * @return array - an array containing the permissions for the role
            */

            // collect the permissions for the role and return an array containing the IDs
            $query = 'select * from role_permissions where role_id = :roleID';
            $query_params = array(':roleID' => $roleID);

            $stmt = $this->_query($query, $query_params);

            // check if $stmt is an array and contains a status key
            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            return $stmt->fetchAll();
        }
        
        public function updateRolePerms($roleID, $perms) { 

            if (!is_array($perms)) { 
                return array(
                    'status' => 'error',
                    'message' => 'The permissions must be an array!'
                );
            }

            // check and see if the role actually exists.
            $role = $this->getRole($roleID);

            // check if $role is an array and contains a status key
            if (is_array($role) && array_key_exists('status', $role)) { 
                return $role;
            }

            $fails = array();
            $succ  = array();

            foreach($perms as $key => $value) { 
                $query = 'update role_permissions set `'.$key.'` = :value where role_id = :roleID';
                $query_params = array(
                    ':value' => $value,
                    ':roleID' => $roleID
                );

                $stmt = $this->_query($query, $query_params);
                                
                if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                    return $stmt;
                } else { 
                    $succ[] = $key;
                }
            }

            if (count($fails) > 0 || count($succ) == 0) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to update the permissions for the roles <strong>' . implode(',', $fails) . '</strong>!'
                );
            }

            return array(
                'status' => 'success',
                'message' => 'Successfully updated the permissions for the role <strong>' . $role['name'] . '</strong>!'
            );
        }

        public function addUsersRole($users, $roleID) { 

            $role = $this->getRole($roleID);

            // check if $role is an array and contains a status key
            if (is_array($role) && array_key_exists('status', $role)) { 
                return $role;
            }

            if (is_array($users) && count($users) < 0) { 
                return array(
                    'status' => 'error',
                    'message' => 'The users must be an array or needs users!'
                );
            }

            $fails = array();
            foreach($users as $user) {
                $res = $this->addUserRole($user, $roleID);
                if ($res['status'] == 'error') { 
                    array_push($fails, $user);
                }
            }

            return array(
                'status' => 'success',
                'message' => 'Successfully added the role <strong>' . $role['name'] . '</strong> to the users <strong>' . implode(',', $fails) . '</strong>!'
            );

        }

        public function checkRolePerms($userID, $roleID, $perm) { 

            // sanitize the input
            $userID = filter_var($userID, FILTER_SANITIZE_NUMBER_INT);
            $roleID = filter_var($roleID, FILTER_SANITIZE_NUMBER_INT);
            $perm = filter_var($perm, FILTER_SANITIZE_STRING);

            $rolePerms = $this->getRolePerms($roleID);

            if (is_array($rolePerms) && array_key_exists('status', $rolePerms)) { 
                return false;
            }

            foreach($rolePerms as $key=>$value){ 
                if ($perm == $key && $value == 1) { 
                    return true;
                }
            }

            return false;
        }

    }

    class PermissionHandler { 

        protected $db; 
        protected $role;
        protected $user;

        public function __construct($db) { 
            global $DB;

            if ($db instanceof PDO) { 
                $this->db = $db;
            } else { 
                $this->db = $DB;
            }

            $this->role = new RoleHandler($this->db);
            $this->user = new UserHandler($this->db);
        }

        // this is a private function that will be used to run sql queries. It will return a PDOStatement object or an array containing an error message.
        private function _query($query, $query_params) { 

            try { 
                $stmt = $this->db->prepare($query);
                $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            return $stmt;
        }

        /* 
        getRolePermission will return the permission value for the role. If the role does not have the permission, it will return false.
        @param $roleID - the ID of the role
        @param $perm - the name of the permission
        @return boolean - true or false depending on if the role has the permission and it does exist
        */
        public function getRolePermission($roleID, $perm) {

            // sanitize the input
            $roleID = filter_var($roleID, FILTER_SANITIZE_NUMBER_INT);
            $perm = filter_var($perm, FILTER_SANITIZE_STRING);

            $query = 'select :perm from role_permissions where role_id = :roleID';
            $query_params = array(':roleID' => $roleID, ':perm' => $perm);

            $stmt = $this->_query($query, $query_params);

            // check if $stmt is an array and contains a status key
            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return false;
            } else { 
                $row = $stmt->fetch();
                return $row[$perm];
            }
        }

        /* 
        getUserPermissions will return the permission value for the user. If the user does not have the permission, it will return false. 
        @param $userID - the user id of the user
        @param $perm - the permission to check for
        @return boolean - true if the user has the permission (1), false if the user does not have the permission or it does not exist.
        */
        public function getUserPermission($userID, $perm) { 

            // sanitize the input
            $userID = filter_var($userID, FILTER_SANITIZE_NUMBER_INT);
            $perm = filter_var($perm, FILTER_SANITIZE_STRING);

            $query = 'select :perm from user_permissions where user_id = :user_id';
            $query_params = array(':user_id' => $userID, ':perm' => $perm);

            $stmt = $this->_query($query, $query_params);

            // check if $stmt is an array and contains a status key
            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return false;
            } else { 
                $row = $stmt->fetch();
                return $row[$perm];
            }
        }

        public function getPermissions($userID) { 

            $userData = $this->user->getUserByID(filter_var($userID, FILTER_SANITIZE_NUMBER_INT));
            $perms    = array();

            if ($userData == null) { 
                $query = 'select `key` from permissions';
                $stmt = $this->_query($query, array());

                if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                    return false;
                };
                
                foreach($stmt->fetchAll() as $row) { 
                    $perms[$row['key']] = 0;
                }                
            }

            $user_p = $this->user->getUserPerms($userData['id']);
            $perms  = array();

            // collect the permissions from all the user's roles, to a bitwise OR on all the permissions
            $query = 'select * from user_roles as ur inner join role_permissions as rp on ur.role_id = rp.role_id where user_id = :userID;';
            $query_params = array(':userID' => $userData['id']);

            $stmt = $this->_query($query, $query_params);

            // check if $stmt is an array and contains a status key
            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return false;
            } 

            $user_r = $stmt->fetch();

            foreach($user_p as $key => $value) { 
                if ($key != 'id' && $key != 'user_id' && $key != 'role_id' && !is_numeric($key) && gettype($value) == 'string') {
                    $perms[$key] = (int)($value || $user_r[$key]);
                }
            }

            return $perms;
        }

        /* 
        getUserStatus will obtain the 'status' column from the users table. The status column determines whether or not a user is active or not.
        @param $userID - the id of the user
        @return boolean - true if the user is active (1 in the status column), false if the user is not active
        */
        public function getUserStatus($userID) { 

            // sanitize the input
            $userID = filter_var($userID, FILTER_SANITIZE_NUMBER_INT);

            $query = 'select status from users where id = :user_id';
            $query_params = array(':user_id' => $userID);

            $stmt = $this->_query($query, $query_params);

            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return false;
            } else { 
                $row = $stmt->fetch();
                return $row['status'] == 1;
            }
        }

        public function checkPerm($userID, $perm) { 

            // sanitize the input
            $userID = filter_var($userID, FILTER_SANITIZE_NUMBER_INT);
            $perm   = filter_var($perm, FILTER_SANITIZE_STRING);

            $userPerms = $this->user->getUserPerms($userID);
            $roles     = $this->role->getUserRoles($userID);

            if (is_array($userPerms) && array_key_exists('status', $userPerms)) { 
                return false;
            }

            // check if the user has the permission to access the admin panel, continue checking if they do not.
            if (isset($userPerms[$perm]) && $userPerms[$perm] == 1) return true;

            // get the roles permissions for the user
            foreach($roles as $role) { 
                $role = $role['role_id'];
                if (isset($userPerms[$role])) { 
                    $userPerms[$role] = $userPerms[$role] | $this->getRolePermission($role, $perm);
                } else { 
                    $userPerms[$role] = $this->getRolePermission($role, $perm);
                }
            }

            return (isset($userPerms[$perm]) && $userPerms[$perm] == 1);
        }

        public function getPermWord($perm) { 

            $perm = filter_var($perm, FILTER_SANITIZE_STRING);

            $query = 'select word from permissions where `key` = :perm';
            $query_params = array(':perm' => $perm);

            $stmt = $this->_query($query, $query_params);

            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $perm;
            } else { 
                $row = $stmt->fetch();
                return $row['word'];
            }

        }
    }

    class UserHandler { 

        protected $db; 
        protected $login;

        public function __construct($db) { 
            global $DB;

            if ($db instanceof PDO) { 
                $this->db = $db;
            } else { 
                $this->db = $DB;
            }

            $this->login = new LoginHandler($this->db);
        }

        private function _query($query, $query_params) { 

            try { 
                $stmt = $this->db->prepare($query);
                $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            return $stmt;
        }

        /* 
        * getPerms will collect the permissions of the user
        * @param $userid - the userid of the user
        * @return array - the permissions of the user
        */
        public function getUserPerms($userid) { 

            $userid = (int) filter_var($userid, FILTER_SANITIZE_NUMBER_INT);

            $query = 'select * from user_permissions where user_id = :userid';
            $query_params = array(
                ':userid' => $userid
            );

            $stmt = $this->_query($query, $query_params);

            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            $row = $stmt->fetch();

            if ($row) { 
                return $row;
            } else { 
                return false;
            }
        }

        public function checkUserPerm($userid, $perm) { 

            $userid = (int) filter_var($userid, FILTER_SANITIZE_NUMBER_INT);
            $perm = filter_var($perm, FILTER_SANITIZE_STRING);

            $query = 'select :key from user_permissions where user_id = :userid';
            $query_params = array(
                ':userid' => $userid,
                ':key' => $perm
            );

            $stmt = $this->_query($query, $query_params);
            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            $row = $stmt->fetch();

            if (isset($row[$perm]) && $row[$perm] == 1) { 
                return true;
            } else { 
                return false;
            }
        }

        /*
        * getPerm will collect the provided permission of the user
        * @param $userid - the userid of the user
        * @param $perm - the permission to collect
        * @return boolean - the permission of the user
        */
        public function getPerm($userid, $perm) { 

            $userid = (int) filter_var($userid, FILTER_SANITIZE_NUMBER_INT);
            $perm = filter_var($perm, FILTER_SANITIZE_STRING);
            
            $query = 'select * from user_permissions where user_id = :userid';
            $query_params = array(
                ':userid' => $userid,
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return false;
            }

            $row = $stmt->fetch();

            if ($row) { 
                return $row[$perm];
            } else { 
                return false;
            }
        }
        
        public function updatePerms($userid, $perms) { 

            $userid = (int) filter_var($userid, FILTER_SANITIZE_NUMBER_INT);

            if (!is_array($perms)) { 
                return array(
                    'status' => 'error',
                    'message' => 'The permissions must be an array!'
                );
            }

            // check and see if the role actually exists.
            $user = $this->getUserByID($userid);

            // check if $role is an array and contains a status key
            if (is_array($user) && array_key_exists('status', $user)) { 
                return $user;
            }

            $fails = array();
            $succ  = array();

            foreach($perms as $key => $value) { 
                $query = 'update user_permissions set `'.$key.'` = :value where user_id = :userID';
                $query_params = array(
                    ':value' => $value,
                    ':userID' => $userid
                );

                $stmt = $this->_query($query, $query_params);
                                
                if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                    return $stmt;
                } else { 
                    $succ[] = $key;
                }
            }

            if (count($fails) > 0 || count($succ) == 0) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to update the permissions for the user <strong>' . implode(',', $fails) . '</strong>!'
                );
            }

            return array(
                'status' => 'success',
                'message' => 'Successfully updated the permissions for the user <strong>' . $user['username'] . '</strong>!'
            );
           
        }

        /* 
        * updatePerm will update the provided permission of the user
        * @param $userid - the userid of the user
        * @param $perm - the permission to update
        * @param $value - the value to set the permission to
        * @return boolean - the status of the update
        */
        public function updatePerm($userid, $perm, $value) { 

            $userid = (int) filter_var($userid, FILTER_SANITIZE_NUMBER_INT);
            $perm = filter_var($perm, FILTER_SANITIZE_STRING);
            $value = (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);

            if ($value === 1 || $value === true || $value === 'true') { 
                $value = 1;
            } else { 
                $value = 0;
            }
                
            $query = 'update user_permissions set ' . $perm . ' = :value where user_id = :userid';
            $query_params = array(
                ':userid' => $userid,
                ':value' => $value
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return false;
            }

            return $stmt->rowCount() > 0;
        }

        public function getUserSettings($userid) { 

            $userid = (int) filter_var($userid, FILTER_SANITIZE_NUMBER_INT);
                
            $query = 'select * from user_settings where user_id = :userid';
            $query_params = array(
                ':userid' => $userid
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return false;
            }

            $row = $stmt->fetch();

            if ($row) { 
                return $row;
            } else { 
                return false;
            }
        }

        public function getUserSetting($userid, $setting) { 
           
            $userid  = (int) filter_var($userid, FILTER_SANITIZE_NUMBER_INT);
            $setting = filter_var($setting, FILTER_SANITIZE_STRING);

            $query = 'select * from user_settings where user_id = :userid';
            $query_params = array(
                ':userid' => $userid,
                ':setting' => $setting
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return false;
            }

            $row = $stmt->fetch();

            if ($row) { 
                return $row[$setting];
            } else { 
                return false;
            }
        }

        public function updateUserSetting($userid, $setting, $value) { 
            
            $userid  = (int) filter_var($userid, FILTER_SANITIZE_NUMBER_INT);
            $setting = filter_var($setting, FILTER_SANITIZE_STRING);
            $value   = filter_var($value, FILTER_SANITIZE_STRING);

            $query = 'update user_settings set ' . $setting . ' = :value where user_id = :userid';
            $query_params = array(
                ':userid' => $userid,
                ':value' => $value
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return false;
            }

            return true;
        }

        public function getAllUsers() { 
            $query = 'select id, username, email, reg_date, last_login, is_locked from users';

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute();
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            return $stmt->fetchAll();
        }

        /* 
        * getUserByID will return the user with the provided id
        * @param $userid - the id of the user
        * @return array - the user data
        */
        public function getUserByID($userid) { 
            $query = 'select id, username, email, reg_date, last_login, password  from users where id = :userid';
            $query_params = array(
                ':userid' => $userid
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            return $stmt->fetch();
        }

        /* 
        * getUserByEmail will return the user with the provided email
        * @param $email - the email of the user
        * @return array - the user or false if not found
        */
        public function getUserByEmail($email) { 
            $query = 'select * from users where email = :email';
            $query_params = array(
                ':email' => $email
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            $result = $stmt->fetch();
            if ($result) { 
                return $result;
            } else { 
                return false;
            }
        }

        /* 
        * getUserByName will return the user with the provided username
        * @param $username - the username of the user
        * @return array - the user or false if not found
        */        
        public function getUserByName($username) { 
            $query = 'select * from users where username = :username';
            $query_params = array(
                ':username' => $username
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            $result = $stmt->fetch();
            if ($result) { 
                return $result;
            } else { 
                return false;
            }
        }

        public function updateUser($userData) { 

            $query = 'update users set username = :username, email = :email where id = :id';
            $query_params = array(
                ':username' => $userData['username'],
                ':email' => $userData['email'],
                ':id' => $userData['id']
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            return array(
                'status' => 'success',
                'message' => 'User updated successfully'
            );
        }

        public function deleteUser($userData) { 

            // ensure that the user is not the only admin (if at all)...
            $query = 'select user_id as id from user_permissions where admin = 1';
            
            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute();
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }
            
            if ($stmt->rowCount() == 1 && $stmt->fetch()['id'] == $userData['id']) { // only one admin and it's the user we're deleting, so don't allow it
                return array(
                    'status' => 'error',
                    'message' => 'You cannot delete the only admin user'
                );
            } else { // multiple admins avaiable and we're not deleting the only one, so delete the user
                
                // create an array of queries to run when deleting a user
                $queries = array(
                    1 => array( 0 => 'delete from users where id = :id', 1 => array(':id' => $userData['id']))
                );

                foreach ($queries as $query) { 

                    try { 
                        $stmt = $this->db->prepare($query[0]);
                        $result = $stmt->execute($query[1]);
                    } catch(PDOException $ex) { 
                        return array(
                            'status' => 'error',
                            'message' => 'Failed to run query: ' . $ex->getMessage()
                        );
                    }
                }

                return array(
                    'status' => 'success',
                    'message' => 'User deleted successfully'
                );
            }
        }

        public function suspsendUser($userData) { 

            // make sure the user is not the only admin
            $query = 'select id from users where is_admin = 1 and id != :id;';
            $query_params = array(
                ':id' => $userData['id']
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            if (!empty($stmt->fetchAll())) { 

                $query = 'update users set is_active = 0 where id = :id';
                $query_params = array(
                    ':id' => $userData['id']
                );

                try { 
                    $stmt = $this->db->prepare($query);
                    $result = $stmt->execute($query_params);
                } catch(PDOException $ex) { 
                    return array(
                        'status' => 'error',
                        'message' => 'Failed to run query: ' . $ex->getMessage()
                    );
                }

                // assume the update was successful, and then return the result
                $result = $stmt->rowCount();
                if ($result > 0) { 
                    return array(
                        'status' => 'success',
                        'message' => 'The user <strong>' . $userData['username'] . '</strong> has been suspended'
                    );
                } else { 
                    return array(
                        'status' => 'error',
                        'message' => 'The user <strong>' . $userData['username'] . '</strong> could not be suspended'
                    );
                }

            } else { 
                return array(
                    'status' => 'error',
                    'message' => 'You cannot suspend the only admin user'
                );
            }

        }

        /*
        * resetUser will reset the password of the user, provided the token is valid
        * @param $data - an array containing the user information
        *   - $data['token']    - the token to reset the password
        *   - $data['password'] - the new password
        *   - $data['user']     - the username of the user
        * @return array - the status of the reset
        */
        public function resetUser($data) { 

            $data['user']  = filter_var($data['user'], FILTER_SANITIZE_STRING);
            $data['email'] = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
            $data['token'] = filter_var($data['token'], FILTER_SANITIZE_STRING);
            
            if ($data['email'] === false) { 
                return array(
                    'status' => 'error',
                    'message' => 'Invalid email address!'
                );
            }

            // check if the token is valid!
            $query = 'select * from reset_tokens where username = :user and reset_token = :token and valid = 1';
            $query_params = array(
                ':user' => $data['user'],
                ':token' => $data['token']
            );

            $stmt = $this->_query($query, $query_params);

            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }
            $row = $stmt->fetch();

            if ($row) { 

                // check if the token is expired!
                if ($row['expires'] < time()) { 
                    return array(
                        'status' => 'error',
                        'message' => 'Token has expired!'
                    );

                } else { 

                    // update the password in the database!
                    $query = 'update users set password = :pass where username = :user';
                    $query_params = array(
                        ':pass' => $this->hashPass($data['password']),
                        ':user' => $data['user']
                    );

                    try { 
                        $stmt = $this->db->prepare($query);
                        $result = $stmt->execute($query_params);

                    } catch(PDOException $ex) { 
                        return array(
                            'status' => 'error',
                            'message' => 'Failed to run query: ' . $ex->getMessage()
                        );
                    }

                    return $this->deleteToken($data);
                }
            } else { 
                return array(
                    'status' => 'error',
                    'message' => 'The requested token is not valid or does not exist!'
                );
            }
        }

        /*
        * deleteToken will remove the token from the database
        * @param $data - an array containing the user information
        *   - $data['token'] - the token to reset the password
        *   - $data['user']  - the username of the user
        * @return array - the status of the reset
        */
        public function deleteUserToken($data) { 

            // delete the token from the database!
            $query = 'update reset_tokens set expired = 1 where user = :user and reset_token = :token';
            $query_params = array(
                ':user' => $data['user'],
                ':token' => $data['token']
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            return array(
                'status' => 'success',
                'message' => 'Password reset successfully!'
            );
        }

        public function invalidateTokens($data) { 

            $data['email'] = filter_var($data['email'], FILTER_SANITIZE_STRING);
            $data['user'] = filter_var($data['user'], FILTER_SANITIZE_STRING);

            // set the isvalid flag to 0
            $query = 'update reset_tokens set valid = 0 where username = :user and email = :email';
            $query_params = array(
                ':user' => $data['user'],
                ':email' => $data['email']
            );

            $stmt = $this->_query($query, $query_params);

            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            if ($stmt->rowCount() > 0) { 
                return array(
                    'status' => 'success',
                    'message' => 'The tokens for the user <strong>'.$data['user'].' have been invalidated successfully!'
                );
            } else { 
                return array(
                    'status' => 'error',
                    'message' => 'The tokens for the user <strong>'.$data['user'].' could not be invalidated!'
                );
            }
        }

        /* 
        * addUserToken will add a new password reset token to the database for the user
        * @param $data - an array containing the user information
        *   - $data['user']  - the username of the user
        *   - $data['email'] - the email address of the user
        * @return array - the status of the reset
        */
        public function addUserToken($data) { 

            $data['user']  = filter_var($data['user'], FILTER_SANITIZE_STRING);
            $data['email'] = filter_var($data['email'], FILTER_VALIDATE_EMAIL);

            $token = $this->generateToken();



            $query = 'insert into reset_tokens (username, reset_token, expires, valid) values (:user, :token, :expires, 1)';
            $query_params = array(
                ':user' => $data['user'],
                ':token' => $token,
                ':expires' => time() + 3600
            );

            $stmt = $this->_query($query, $query_params);

            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            if ($stmt->rowCount() > 0) { 
                return array(
                    'status' => 'success',
                    'message' => 'Successfully created the reset token, <strong> for the user!',
                    'token' => $token
                );
            } else { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to add token for the user <strong>.'.$data['user'].'!'
                );
            }
        }

        public function generateToken() { 

            $data = random_bytes(16);

            if (strlen($data) !== 16) { 
                throw new Exception('Unable to generate a secure token from random_bytes()');
            }

            $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }

        public function getUserRoles($userID) { 

            $query = 'select role_id from user_roles where user_id = :user_id';
            $query_params = array(
                ':user_id' => $userID
            );

            $stmt = $this->_query($query, $query_params);

            if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                return $stmt;
            }

            $roles = array();

            while ($row = $stmt->fetchAll(PDO::FETCH_ASSOC)) { 
                $roles[] = $row['role'];
            }

            return $roles;
        }

        public function updatePassword($userID, $pass) { 

            // check if the user exists!
            $user = $this->getUserByID($userID);

            if (is_array($user) && array_key_exists('username', $user)) { 
                
                $query = 'update users set password = :pass where id = :id';
                $query_params = array(
                    ':pass' => $this->login->hashPass($pass),
                    ':id' => $userID
                );


                $stmt = $this->_query($query, $query_params);

                if (is_array($stmt) && array_key_exists('status', $stmt)) { 
                    return $stmt;
                }

                if ($stmt->rowCount() > 0) { 
                    return array(
                        'status' => 'success',
                        'message' => 'Successfully updated the password for the user <strong>'.$user['username'].'!</strong>'
                    );
                } else { 
                    return array(
                        'status' => 'error',
                        'message' => 'Failed to update the password for the user <strong>'.$user['username'].'!</strong>'
                    );
                }
            }
        }
    }

    class ConnectionManager { 

        protected $db;
        protected $proxmox;
        protected $user;
        protected $request;
        
        public function __construct($db) { 
            global $DB;

            if ($db instanceof PDO) { 
                $this->db = $db;
            } else { 
                $this->db = $DB;
            }

            $this->proxmox = new Proxmox();
            $this->user    = new UserHandler($this->db);
            $this->request = new RequestHandler();
        }

        // getAllConnections will collect all of the connections from the database
        public function getConnections() { 

            $query = "select * from connections";

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute();
            } catch(PDOException $ex) { 
                return false;
            }

            $rows = $stmt->fetchAll();

            return $rows;
        }

        // getConnection will collect the connection information for the provided connection ID
        // @param $id - the connection ID
        // @return array - the connection information
        public function getConnection($id) { 

            $id = (int) filter_var($id, FILTER_SANITIZE_NUMBER_INT);


            $query = 'select * from connections where id = :id';
            $query_params = array(
                ':id' => $id
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return false;
            }

            return $stmt->fetch();
        }

        // getSharedConnections will collect the connections that have been shared with the userID
        // @param $userid - the userID to collect the shared connections for
        // @return array - the shared connections
        public function getSharedConnections($userID) { 

            $userID = (int) filter_var($userID, FILTER_SANITIZE_NUMBER_INT);

            $query = 'select * from connections where sharedwith like concat("%", :userid, "%"';
            $query_params = array(
                ':userid' => $userID
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return false;
            }

            $rows = $stmt->fetchAll();

            $shared_conns = array();
            foreach ($rows as $row) { 
                $sharedIDs = explode(',', $row['sharedwith']);
                if (in_array($userID, $sharedIDs)) { 
                    push_array($shared_conns, $row);

                }
            }
            return $shared_conns;
        }

        // getOwnedConnections will collect the connections that have been assigned with the userID
        // @param $userid - the userID to collect the owned connections for
        // @return array - the connections owned by a user
        public function getOwnedConnections($userID) { 

            $query = 'select * from connections where owner = :userid';
            $query_params = array(
                ':userid' => $userID
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return false;
            }

            return $stmt->fetchAll();
        }

        // createConnection will create a new connection in the database, however a user can only have one connection with the same name
        public function createConnection($conn_data) { 

            // serach for an existing connection with the same name, and owner
            $query = 'select id from connections where name = :name and owner = :owner';
            $query_params = array(
                ':name' => $conn_data['name'],
                ':owner' => $conn_data['owner']
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            $row = $stmt->fetch();

            if (!empty($row)) { 
                return array(
                    'status' => 'error',
                    'message' => 'A connection with the name <strong>' . $conn_data['name'] . '</strong> already exists'
                );
            }

            // connection does not exist, so create it! but first, sanitize the data and encrypt the password!
            $conn_data['password'] = $this->request->encryptData($conn_data['password']);
            $conn_data['username'] = $this->request->encryptData($conn_data['username']);

            $query = 'insert into connections (name, hostname, port, username, password, owner, protocol, os, drive, node) values (:name, :host, :port, :username, :password, :owner, :protocol, :os, :drive, :node)';
            $query_params = array(
                ':name'     => $conn_data['name'],
                ':host'     => $conn_data['host'],
                ':port'     => $conn_data['port'],
                ':username' => $conn_data['username'],
                ':password' => $conn_data['password'],
                ':owner'    => $conn_data['owner'],
                ':protocol' => $conn_data['protocol'],
                ':os'       => $conn_data['os'],
                ':drive'    => $conn_data['drive'],
                'node'      => $conn_data['node']
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            $count = $stmt->rowCount(); // check the rows effected by the query, it should be 1. if not, something went wrong!
            if ($count == 0) { 
                return array(
                    'status' => 'error',
                    'message' => '<strong>Failed to create connection,</strong> something went wrong!'
                );
            }

            return array(
                'status' => 'success',
                'message' => 'Connection created successfully'
            );
        }

        // updateConnection will update a connection in the database, however a user can only have one connection with the same name
        // @param $conn_data - the connection data to update
        // @return array - the status of the update
        public function updateConnection($conn_data) { 

            var_dump($conn_data);

            // get the existing connection data, if the name, host, or port has changed; update the password encryption!
            $existing_conn = $this->getConnection($conn_data['id']);
            

            // check if the connection actually exists...
            if ($existing_conn == false || empty($existing_conn) || is_null($existing_conn)) { 
                return array(
                    'status' => 'error',
                    'message' => '<strong>Failed to update the connection,</strong> Connection does not exist'
                );
            }

            $conn_data['password'] = $this->request->encryptData($conn_data['password']);
            $conn_data['username'] = $this->request->encryptData($conn_data['username']);

            // check if the new connection name is already in use by the user!
            $query = 'select id from connections where name = :name and owner = :owner and id != :id';
            $query_params = array(
                ':name' => $conn_data['name'],
                ':owner' => $conn_data['owner'],
                ':id' => $conn_data['id']
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            $row = $stmt->fetch();

            if (!empty($row)) { 
                return array(
                    'status' => 'error',
                    'message' => 'A connection with the name <strong>' . $conn_data['name'] . '</strong> already exists'
                );
            }

            if ($existing_conn['name'] != $conn_data['name'] || $existing_conn['hostname'] != $conn_data['host'] || $existing_conn['port'] != $conn_data['port']) { 
                $conn_data['password'] = $this->request->encryptData($conn_data['password']);
            }

            $query = 'update connections set name = :name, hostname = :hostname, port = :port, username = :username, password = :password,  owner = :owner, protocol = :protocol, os = :os, drive = :drive, node = :node, modified = CURRENT_TIMESTAMP() where id = :id';
            $query_params = array(
                ':name'     => $conn_data['name'],
                ':hostname' => $conn_data['host'],
                ':port'     => $conn_data['port'],
                ':username' => $conn_data['username'],
                ':password' => $conn_data['password'],
                ':owner'    => $conn_data['owner'],
                ':protocol' => $conn_data['protocol'],
                ':os'       => $conn_data['os'],
                ':drive'    => $conn_data['drive'],
                ':node'     => $conn_data['node'], 
                ':id'       => $conn_data['id']
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            $count = $stmt->rowCount(); // check the rows effected by the query, it should be 1. if not, something went wrong!
            if ($count == 0) { 
                return array(
                    'status' => 'error',
                    'message' => '<strong>Failed to update connection,</strong> something went wrong!'
                );
            }

            return array(
                'status' => 'success',
                'message' => 'Connection updated successfully'
            );
        }

        // deleteConnection will delete a connection from the database
        // @param $conn_data - the connection data to delete
        // @return array - the status of the delete
        public function deleteConnection($conn_data) { 

            // check if connection actually exists!

            $query = 'delete from connections where id = :id and owner = :owner';
            $query_params = array(
                ':id' => $conn_data['id'],
                ':owner' => $conn_data['owner']
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            $count = $stmt->rowCount(); // check the rows effected by the query, it should be 1. if not, something went wrong!
            if ($count == 0) { 
                return array(
                    'status' => 'error',
                    'message' => '<strong>Failed to delete connection,</strong> something went wrong!'
                );
            }

            return array(
                'status' => 'success',
                'message' => 'Connection deleted successfully'
            );
        }

        // updateActive will update the lastactive field of a connection
        // @param $conn_data - the connection data to update
        // @return array - the status of the update
        public function updateActive($conn_data) { 

            // check if connection actually exists!
            $conn = $this->getConnection($conn_data['id']);

            if ($conn == false || empty($conn) || is_null($conn)) { 
                return array(
                    'status' => 'error',
                    'message' => '<strong>Failed to set connection as active,</strong> connection does not exist!'
                );
            }

            $query = 'update connections set lastactive = CURRENT_TIMESTAMP() where id = :id and (owner = :owner or sharedwith LIKE concat(\'%\', :owner, \'%\'))';
            $query_params = array(
                ':id' => $conn_data['id'],
                ':owner' => $conn_data['owner']
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                return array(
                    'status' => 'error',
                    'message' => 'Failed to run query: ' . $ex->getMessage()
                );
            }

            $count = $stmt->rowCount(); // check the rows effected by the query, it should be 1. if not, something went wrong!

            if ($count == 0) { 
                return array(
                    'status' => 'error',
                    'message' => '<strong>Failed to set connection as active,</strong> something went wrong!'
                );
            }

            return array(
                'status' => 'success',
                'message' => 'Connection set as active successfully'
            );
        }

        public function discoverConnections() { 

            $users = array();
            foreach ($this->user->getAllUsers() as $user) { 
                $users[] = $user['username'];
            }

            $vms = $this->proxmox->getAllVMs($users);

            foreach($vms as $vm) { 
                $conn_data = array(
                    'name' => $vm['name'],
                    'host' => $vm['host'],
                    'port' => $vm['port'],
                    'username' => $vm['username'],
                    'password' => $vm['password'],
                    'protocol' => $vm['protocol'],
                    'owner' => $vm['owner'],
                    'sharedwith' => $vm['sharedwith'],
                    'lastactive' => $vm['lastactive']
                );

                $this->createConnection($conn_data);
            }
        }
    }

    class GaucamoleHandler { 

        protected static $host;
        protected static $port;
        protected static $username;
        protected static $password;
        protected static $authToken;

        public static $avail_conns      = array();
        public static $avail_groups     = array();
        public static $avail_conngroups = array();

        function __construct() { 

        }

        /* generateConn will create a new Guacamole Connection with the VM name as the name in guacamole
        * and the IP address as the identifier. 
        * 
        * @param string $vm_name - The name of the VM to create a connection for
        * @param string $ip - The IP address of the VM to create a connection for
        * @param string $client_type - The type of client to use for the connection
        * @param string $auth - The authentication method to use for the connection
        * 
        * @return string - The ID of the newly created connection
        */
        function generateConn($vm) { 
            global $_SESSION;
            global $INFO;

            if (!empty($vm)) { 
                
                // check if the vm is online or if the the qemu guest agent is running
                if ($vm['conn'] !== NULL && stripos($vm['conn'], 'running') === false) { 
                    $data = array(
                        "connection" => array(
                            "type" => "",
                            "settings" => array(
                                'hostname' 	                 => $vm['conn'],
                                'port' 	 	                 => 3389,
                                "username"                   => isset($vm['user']) ? $vm['user'] : '',
                                "password"                   => isset($vm['pass']) ? $vm['pass'] : '',
                                "enable-desktop-composition" => "true",
                                "enable-menu-animations"     => "true",
                                "drive-path"                 =>  $INFO['guacd_drive'],
                                "drive-name"                 => "public",
                                "normalize-clipboard"        => "preserve",
                                "create-drive-path"          => "true",
                                "enable-font-smoothing"      => "true",
                                "enable-full-window-drag"    => "true",
                                "enable-drive"               => "true",
                                "enable-wallpaper"           => "true",
                                "enable-theming"             => "true",                               
                                "ignore-cert"                => "true",
                            )
                        ),
                        "hub" => array(
                            "user"      => $_SESSION['username'],
                            "user_addr" => $_SERVER['HTTP_X_FORWARDED_FOR'],
                            "name"       => ($INFO['proxy'] ? 'https://' : (empty($_SERVER['HTTPS']) ? 'http://' : 'https://')).$_SERVER['HTTP_HOST']
                        )
                    );

                    $data['connection']['type'] = 'rdp';
                    return self::buildToken($data) ;
                }
            }
        }

        function buildToken($value) { 
            global $INFO;
        
            $method = "AES-256-CBC";
            $key = hash('sha256', $INFO["guacd_secret"], true);
            $iv  = openssl_random_pseudo_bytes(16);
        
            $value = \openssl_encrypt(json_encode($value), $method, $key, 0, $iv);
            if ($value === false) {
                throw new \Exception('Could not encrypt the data.');
            }
        
            $data = [
                'iv'    => base64_encode($iv),
                'value' => $value,
            ];

            #var_dump(base64_encode($key));
            #var_dump(base64_encode($iv));

            # $json = json_encode($data);
            $json = base64_encode(json_encode($data));

            if (!is_string($json)) {
                throw new \Exception('Could not encrypt the data.');
            }
                                
            return $json;
        }
    }

    class ProxMox { 

        protected static $configure;
        protected static $request;
        protected static $access;
        protected static $nodes;
        protected static $cluster;
        protected static $storage;

        protected static $nodesList;
        protected static $guacamole;


        function __construct() {
            global $INFO;

            $configure = array(
                'hostname'      => $INFO['prox_host'],
                'port'          => '8006',
                'token_name'    => $INFO['prox_key_id'],
                'token_value'   => $INFO['prox_key'],
                'username'      => $INFO['prox_user'],
                'password'      => $INFO['prox_pass'],
                'realm'         => 'pam',
            );

            
            try { 
                self::$request = new Request($configure);
                self::$access  = new Access();
                self::$nodes   = new Nodes();
                self::$cluster = new Cluster();
                self::$storage = new Storage();
            } catch (Exception $ex) {
                
                $errorMSG = "Failed to connect to Proxmox API!";
                include('error.php');
                die();
            }

            self::$guacamole = new GaucamoleHandler();
        }

        /* getOnlineNodes will retreive all the online nodes with the following information: 
            name of the node
            status of the node (online, offline, unknown)
            id of the node node/<name>
        */
        function getOnlineNodes() { 

            $nodes        = self::getNodes();
            $online_nodes = array();

            if ($nodes) { 
                foreach ($nodes->data as $node) { 
                    if ($node->status == "online") { 
                      array_push($online_nodes, array(
                        'name' => $node->node,
                        'status' => $node->status,
                        'id' => $node->id
                      ));
                    }
                }
            }

            return $online_nodes;
        }

        function getNodes() {
            self::$nodesList = self::$nodes::listNodes();
            return self::$nodesList;
        }

        function getClusterResources() { 

            $cluster_data = self::$cluster::Resources('node')->data;

            $data = array(
                'cpu' => 0,
                'cpu_usage' => 0.0,
                'mem' => 0,
                'mem_usage' => 0.0,
                'disk' => 0,
                'disk_usage' => 0.0,
            );

            foreach($cluster_data as $cluster) { 
                $data['cpu'] += $cluster->maxcpu;
                $data['cpu_usage'] += $cluster->cpu;
                $data['mem'] += $cluster->maxmem;
                $data['mem_usage'] += $cluster->mem;
                $data['disk'] += $cluster->maxdisk;
                $data['disk_usage'] += $cluster->disk;
            }

            $data['cpu_usage'] = round(($data['cpu_usage'] * 100) / count($cluster_data), 2);
            $data['mem_usage'] = round((($data['mem_usage'] / $data['mem']) * 100) / count($cluster_data), 2);
            $data['disk_usage'] = round((($data['disk_usage'] / $data['disk']) * 100) / count($cluster_data), 2);

            return $data;
        }

        public function getClusterResc() { 
            return self::$cluster::Resources('node')->data;    
        }

        function getVMs($node) {
            return self::$nodes::Qemu($node);
        }

        /*
          findVM will search for a VM in the ProxMox cluster
          @param $vmid: The ID of the VM to search for
          @param $name: The name of the VM to search for
          @param $node: The node to search for the VM in
          @param $mult: If true, will return all the VMs that match the search criteria
          @return: The VM object if found, null otherwise       
        */
        function findVM(int $vmid = null, string $name = null,  $node = null, $mult=False) { 

            $discovered_vms = array();
            $matched_vms    = array();
            
            if ($vmid != null || $name != null) {

                if ($node != null) { 
                    $discovered_vms = self::getVMs($node);
                } else if ($name != null) { 
                    $discovered_vms = array();
                    $nodes = self::$nodesList->data;
                    
                    foreach ($nodes as $node) { 
                        foreach(self::getVMs($node->node)->data as $vm) {
                            if ($vm->vmid == $vmid) { 
                                if ($mult) { 
                                    array_push($matched_vms, $vm);
                                } else { 
                                    return $vm;
                                }
                            } else if (stripos($vm->name,$name) !== false) { 
                                if ($mult) { 
                                    array_push($matched_vms, $vm);
                                } else { 
                                    return $vm;
                                }
                            }
                        }
                        $discovered_vms = array_merge($discovered_vms, );
                    }
                }
            } else if ($node != null) { 
                return self::getVMs($node)->data;
            }

            return $matched_vms;
        }

        /*
            getVMNetworking will return the connection IP addresses of the VM
            @param $node: The node the VM is on
            @param $vmid: The ID of the VM
            @return: The IP address of the VM
        */
        function getVMNetworking($node, $vmid) { 
            $data = self::$request::Request("/nodes/$node/qemu/$vmid/agent/network-get-interfaces", null, 'GET');

            if ($data != NULL && $data->data != NULL) { 
                $data = $data->data->result;
                foreach ($data as $interface) { 
                    $interface = get_object_vars($interface);
                    if (stripos($interface['name'], 'lo') === false || stripos($interface['name'], 'lo') === false) { 
                        foreach ($interface['ip-addresses'] as $addr) { 
                            $addr = get_object_vars($addr);
                            if ($addr['ip-address-type'] == 'ipv4') {
                                return $addr['ip-address'];
                            }
                        }
                    }
                }
            }
            return '';
        }

        /* 
            getVMOsInfo will return the OS information of the VM
            @param $node: The node the VM is on
            @param $vmid: The ID of the VM
            @return: The OS information of the VM
        */
        function getVMOsInfo($node, $vmid) { 
            return self::$request::Request("/nodes/$node/qemu/$vmid/agent/get-osinfo", null, 'GET'); 
        }

        /* 
        getOwnedVMs will return all VM's owned by a user on all available nodes ($nodes is an array of nodes).
            - An 'OWNED' VM is determined by the username (guac) being present in the VM name
            - an empty username will return all VMs
        returns an array containing a VM's: 
            - disks
            - uptime
            - max memory
            - cpus
            - vmid
            - status
        */
        function getOwnedVMs(string $username, array $nodes) { 

            $vms = array();
            if (!empty($nodes)) { 

                foreach ($nodes as $node) { 
                    foreach (self::findVM(null, null, $node['name']) as $vm) {
                        if (stripos($vm->name, $username) != false || $username == '') {
                            // var_dump(self::$nodes::qemuAgentNetwork($node['name'], $vm->vmid)->data->result);
                            // var_dump(self::$nodes::qemuAgentNetwork($node['name'], $vm->vmid)->data);
                            $upSec   = str_pad($vm->uptime %60, 2, '0', STR_PAD_LEFT);
                            $upMins  = str_pad(floor(($vm->uptime % 3600)/60), 2, '0', STR_PAD_LEFT);
                            $upHours = str_pad(floor(($vm->uptime % 86400)/3600), 2, '0', STR_PAD_LEFT);
                            $upDays  = str_pad(floor(($vm->uptime % 2592000)/86400), 2, '0', STR_PAD_LEFT);

                            $status  = self::$nodes::qemuCurrent($node['name'], $vm->vmid)->data;
                            $os_info = self::getVMOsInfo($node['name'], $vm->vmid)->data;
                            $data = array(
                                'disk'   => ($vm->maxdisk > 1073741824) ? ($vm->maxdisk / 1073741824) . 'Gb' : ($vm->maxdisk / 1048576) . 'Mb',
                                'uptime'  => "{$upDays}D {$upHours}:{$upMins}:{$upSec}",
                                'maxmem' => ($vm->maxmem / 1073741824),
                                'cpus'   => $vm->cpus,
                                'vmid'   => $vm->vmid,
                                'status' => ($status != NULL) ? $status->status : 'unknown',
                                'os'     => ($os_info != NULL) ? $os_info->result->id : 'agent not running',
                                'name'   => str_ireplace("-{$_SESSION['username']}", '', $vm->name),
                                'node'   => $node['name'],
                                'conn'   => self::getVMNetworking($node['name'], $vm->vmid),
                                'snaps'  => self::listSnapshots($node['name'], $vm->vmid),
                                'guac'   => NULL,
                                'token'  => NULL
                            );

                            $config = self::getVMConfig($vm->vmid, $node['name']);

                            if ($config) { 
                                if (isset($config->description)) { 
                                    $config = explode('::',$config->description);
                                    $data['user']  = $config[0];
                                    $data['pass']  = isset($config[1]) ? explode('\n', $config[1])[0] : '';
                                } else { 
                                    $data['user']  = '';
                                    $data['pass']  = '';
                                }
                            }

                            $data['token'] = array(
                                'conn_start', $data['name'], self::$guacamole->generateConn($data), $data['vmid'], $data['node']
                            );

                            
                            
                            array_push($vms, $data);
                        }
                    }
                }
                return $vms;
            }
            return $vms;
        }

        /*
            getAllVMs will collect all of the Virtual Machines on all nodes for all users
            @param $node: The node the VM is on
            @param $vmid: The ID of the VM
            @return: The configuration of the VM
        */
        function getAllVMs( array $users) {

            $vms = array();

            foreach (self::getNodes()->data as $node) { 
                
                foreach (self::getVMs($node->node)->data as $vm) { 
                    
                    $os_info = self::getVMOsInfo($node->node, $vm->vmid)->data;
                    $status  = self::$nodes::qemuCurrent($node->node, $vm->vmid)->data;

                    foreach ($users as $user) { 
                        if (stripos($vm->name, $user['username']) !== false) {
                            $upSec   = str_pad($vm->uptime %60, 2, '0', STR_PAD_LEFT);
                            $upMins  = str_pad(floor(($vm->uptime % 3600)/60), 2, '0', STR_PAD_LEFT);
                            $upHours = str_pad(floor(($vm->uptime % 86400)/3600), 2, '0', STR_PAD_LEFT);
                            $upDays  = str_pad(floor(($vm->uptime % 2592000)/86400), 2, '0', STR_PAD_LEFT);

                            $os_info = (($os_info == NULL) ? 'missing agent' : (($os_info->result->id == 'windows') ? 'mswindows' : $os_info->result->id));
                                                        
                            $data = array(
                                'name'    => $vm->name,
                                'node'    => $node->node,
                                'vmid'    => $vm->vmid,
                                'os'      => $os_info,
                                'status'  => ($status != NULL) ? $status->status : 'unknown',
                                'maxmem'  => ($vm->maxmem > 1073741824) ? ($vm->maxmem / 1073741824) . 'Gb' : ($vm->maxmem / 1048576) . 'Mb',
                                'maxdisk' => ($vm->maxdisk > 1073741824) ? ($vm->maxdisk / 1073741824) . 'Gb' : ($vm->maxdisk / 1048576) . 'Mb',
                                'cpus'    => $vm->cpus,
                                'uptime'  => "{$upDays}D {$upHours}:{$upMins}:{$upSec}",
                                'conn'    => self::getVMNetworking($node->node, $vm->vmid),
                            );

                            $config = self::getVMConfig($vm->vmid, $node->node);
                            if ($config && isset($config->description)) { 
                                $config = explode('::',$config->description);
                                $data['user']  = $config[0];
                                $data['pass']  = isset($config[1]) ? explode('\n', $config[1])[0] : '';
                            }

                            $data['token'] = array(
                                'conn_start', $data['name'], self::$guacamole->generateConn($data), $data['vmid'], $data['node']
                            );
                            array_push($vms, $data);
                        }
                    }
                }
            }
            return $vms;
        }

        /* 
        getTemplates will return all templates available on the proxmox cluster
            - A 'Template' VM is determined by the string 'temp' being present in the VM name
            @param $filter: substring to filter the templates by
            @param $node: node to search for templates on
        */
        function getTemplates(string $filter = 'Temp', string $node = '') {
            
            $templates = array();
            $vms = self::$cluster::Resources('vm')->data;
            foreach ($vms as $vm) {      
                if ($vm->template == 1) { 
                    $temp = array(
                        'disk' => $vm->disk,
                        'uptime' => $vm->uptime, 
                        'maxmem' => ($vm->maxmem / 1073741824),
                        'vmid' => $vm->vmid,
                        'status' => $vm->status,
                        'name' => !empty($filter) ? str_ireplace("$filter-", '', $vm->name) : $vm->name,
                        'node' => $vm->node,
                    );
                    
                    if (!empty($filter) || !empty($node)) { 
                        if (stripos($vm->name, $filter) !== false && !empty($filter)) {
                            array_push($templates, $temp);
                        } else if ($node == $vm->node && !empty($node)) {
                            array_push($templates, $temp);
                        }
                    } else { 
                        array_push($templates, $temp);
                    }
                }
            }
            return $templates;

        }

        /*
            getNode will collect the node for a specific VM
            @param $vmid: The ID of the VM
            @return: The node containing the VM
        */
        function getNode($vmid) { 
            $nodes = self::getNodes()->data;
            foreach ($nodes as $node) { 
                foreach(self::getVMs($node->node)->data as $vm) {
                    if ($vm->vmid == $vmid) { 
                        return $node->node;
                    }
                }
            }
            return null;
        }

        /*
            getVMConfig will collect the VM configuration for a specific VM
            @param $vmid: The ID of the VM
            @param $node: The node containing the VM, if null it will use getNode to find the node
            @return: The VMs on the node
        */
        function getVMConfig($vmid, $node = null) { 
            if ($node == null) { 
                $node = self::getNode($vmid);
            }
            return self::$nodes::qemuConfig($node, $vmid)->data;
        }

        /*
            getVM will collect data for a specific VM
            @param $vmid: The ID of the VM
            @param $node: The node containing the VM, if null it will use getNode to find the node
            @return: The VMs on the node
        */
        function getVM($vmid, $node = null) { 
            if ($node == null) { 
                $node = self::getNode($vmid);
            }

            $vm   = self::$nodes::qemu($node, $vmid)->data;

            $status  = self::$nodes::qemuCurrent($node['name'], $vm->vmid)->data;
            $os_info = self::getVMOsInfo($node['name'], $vm->vmid)->data;
            $config  = explode('::', self::getVMConfig($vm->vmid, $node['name'])->description);
            $data = array(
                'disk'   => $vm->disk,
                'uptime' => $vm->uptime, 
                'maxmem' => ($vm->maxmem / 1073741824),
                'cpus'   => $vm->cpus,
                'vmid'   => $vm->vmid,
                'status' => ($status != NULL) ? $status->status : 'unknown',
                'os'     => ($os_info != NULL) ? $os_info->result->id : 'agent not running',
                'name'   => str_ireplace("-{$_SESSION['username']}", '', $vm->name),
                'node'   => $node['name'],
                'conn'   => self::getVMNetworking($node['name'], $vm->vmid),
                'snaps'  => self::$nodes::qemuSnapshot($node['name'], $vm->vmid)->data,
                'guac'   => NULL,
                'user'   => $config[0],
                'pass'   => $config[1],
            );
            $data['token'] = array(
                'conn_start', $data['name'], self::$guacamole->generateConn($data), $data['vmid'], $data['node']
            );

            return $data;
        }

        /* 
        getConnInfo will get the connection information for a VM
            - A 'Template' VM is determined by the string 'temp' being present in the VM name
            @param $vmid: the ID of the VM to get connection info for
            @param $node: the node the VM is on
        */
        function getConnInfo($vmid, $node) { 

            $agentStatus = self::$nodes::qemuAgent($vmid, $node);

            return self::$nodes::Qemu($node, $vmid)->data;
        }

        /* 
            listSnapshots will list all snapshots of a VM
            @param $vmid: The ID of the VM to list the snapshots of
            @param $name: The name of the VM to list the snapshots of
            @param $node: The node to list the snapshots of the VM in
        */
        function listSnapshots($node = null, $vmid) { 

            if ($node == null) { 
                $node = self::getNode($vmid);            
            }

            $snapshots = self::$nodes::qemuSnapshot($node, $vmid)->data;

            usort($snapshots, function($a, $b) {
                if ($a->name != 'current' && $b->name != 'current')
                    return $a->snaptime > $b->snaptime;
            });

            return $snapshots;
        }
        

        /* 
            getVMStatus will get the status of a VM
            @param $vmid: The ID of the VM to get the status of
            @param $name: The name of the VM to get the status of
            @param $node: The node containing the VM to get the status of
        */
        function getVMStatus($vmid, $node) { 
            $status = self::$nodes::qemuCurrent($node, $vmid);
            if ($status) { 
                return $status->data->qmpstatus;
            } else { 
                return 'stopped';
            }
        }

        /* 
            createSnap will create a snapshot of a VM
            @param $vmid: The ID of the VM to create the snapshot of
            @param $name: The name of the VM to create the snapshot of
            @param $node: The node containing the VM to create the snapshot of
        */
        function createSnap($vmid = null, $name = null, $node = null, $data = array()) { 

            if ($node == null) { 
                $node = self::getNode($vmid);            
            }

            if (empty($data)) { 
                $data = array(
                    "snapname" => $name,
                    "description" => '',
                    "vmstate" => 1,
                );
            }

            $resp = self::$nodes::createQemuSnapshot($node, $vmid, $data);
            return $resp;
        }

        /* 
            deleteSnap will delete a snapshot of a VM
            @param $vmid: The ID of the VM to create the snapshot of
            @param $name: The name of the VM to create the snapshot of
            @param $node: The node containing the VM to delete the snapshot of
        */
        function deleteSnap($vmid = null, $name = null, $node = null) { 

            if ($node == null) { 
                $node = self::getNode($vmid);            
            }

            return self::$nodes::deleteQemuSnapshot($node, $vmid, $name);
        }

        /* 
            revertSnap will revert a VM to the previous snapshot
            @param $vmid: The ID of the VM to revert
            @param $name: The name of the VM to revert
            @param $node: The node containing the VM to revert
        */
        function revertVM($vmid= null, $node = null, $name = null) { 

            if ($node == null) { 
                $node = self::getNode($vmid);            
            }

            if ($name == null) { // find the most recent snapshot by date
                $snaps = self::listSnapshots($node, $vmid);
                $snap_found  = NULL;

                // find the current state, and then get the parent of it to get the most recent snapshot
                foreach ($snaps as $snap) { 
                    if ($snap->name == "current") { 
                        $name = $snap->parent;
                        $snap_found = $snap;
                        break;
                    }
                }
            }

            return self::$nodes::QemuSnapshotRollback($node, $vmid, $name);
        }

        /*
            startVM will start a VM
            @param $vmid: The ID of the VM to start
            @param $name: The name of the VM to start
            @param $node: The node containing the VM to start
        */
        function startVM($vmid = null, $name = null, $node = null) { 

            if ($node == null) { 
                $node = self::getNode($vmid);            
            }

            return self::$nodes::QemuStart($node, $vmid);
        }

        /*
            stopVM will stop a VM
            @param $vmid: The ID of the VM to stop
            @param $name: The name of the VM to stop
            @param $node: The node containing the VM to stop
        */
        function stopVM($vmid = null, $name = null, $node = null) { 

            if ($node == null) { 
                $node = self::getNode($vmid);            
            }

            return self::$nodes::QemuStop($node, $vmid);
        }

        /*
            deleteVM will delete a VM
            @param $vmid: The ID of the VM to delete
            @param $name: The name of the VM to delete
            @param $node: The node containing the VM to delete
        */
        function deleteVM($vmid = null, $name = null, $node = null) { 

            if ($node == null) { 
                $node = self::getNode($vmid);            
            }

            return self::$request::Request("/nodes/$node/qemu/$vmid", array(), 'DELETE');
        }

        /*
            rebootVM will reboot a VM
            @param $vmid: The ID of the VM to reboot
            @param $name: The name of the VM to reboot
            @param $node: The node containing the VM to reboot
        */
        function rebootVM($vmid = null, $name = null, $node = null) { 

            if ($node == null) { 
                $node = self::getNode($vmid);
            }

            return self::$nodes::QemuReboot($node, $vmid);
        }

        /*
            resetVM will reset a VM
            @param $vmid: The ID of the VM to reset
            @param $name: The name of the VM to reset
            @param $node: The node containing the VM to reset
        */
        function resetVM($vmid = null, $name = null, $node = null) { 

            if ($node == null) { 
                $node = self::getNode($vmid);
            }

            return self::$nodes::QemuReset($node, $vmid);
        }   


        function suspendVM($vmid = null, $name = null, $node = null) { 

            if ($node == null) { 
                $node = self::getNode($vmid);
            }

            return self::$nodes::QemuSuspend($node, $vmid);
        }

        function resumeVM($vmid = null, $name = null, $node = null) { 

            if ($node == null) { 
                $node = self::getNode($vmid);
            }

            return self::$nodes::QemuResume($node, $vmid);
        }
        
        function getAvailableVMID(string $filter = '', string $exclude = 'GS-') { 

            $vms  = array();
            $vmid = 0;
            foreach (self::$cluster::Resources('vm')->data as $vm) { 
                if ($vm->template != 1) { // only check for active VMs!
                    if (stripos($vm->name, $exclude) === false) {
                        if (empty($filter) || stripos($vm->name, $filter) !== false) {
                            if ($vmid != NULL && $vmid < $vm->vmid) { 
                                $vmid = $vm->vmid;
                            } else if ($vmid == 0) { 
                                $vmid = $vm->vmid;
                            } 
                        }
                    }
                }
            };
            return ($vmid !== 0) ? $vmid + 1 : NULL;
        }

        function cloneVM($vmid = null, $node = null, $data = null) { 
                
            if ($node == null) { 
                $node = self::getNode($vmid);
            }
                
            return self::$nodes::qemuClone($node, $vmid, $data);
        }

        
        function buildToken($value) { 
            global $INFO;

            $iv = random_bytes(16);
            
            $value = \openssl_encrypt(json_encode($value), "AES-256-CBC", $INFO["enc_key"], 0, $iv);
            if ($value === false) {
                throw new \Exception('Could not encrypt the data.');
            }
                        
            $data = [
                'iv'    => base64_encode($iv),
                'value' => $value,
            ];
        
            $json = json_encode($data);
        
            if (!is_string($json)) {
                throw new \Exception('Could not encrypt the data.');
            }
        
            return base64_encode($json);
        } 

        function getStorage() { 
            $storage =  self::$request::Request("/storage", null, 'GET')->data;
            
            $disks = array();
            foreach ($storage as $store) { 
                $disks[] = $store->storage;
            }
            return $disks;
        }
    }

    class DuoHandler { 

        private static $ikey   = '';
        private static $skey   = '';
        private static $api    = '';
        private static $fail   = '';
        private static $redir  = '';


        private static $Client;

        function __construct($configure) { 

            if ($configure['enable_duo'] == true) {
                self::$ikey  = $configure['duo_ikey'];
                self::$skey  = $configure['duo_skey'];
                self::$redir = $configure['duo_redir'];
                self::$api   = $configure['duo_api'];
                self::$fail  = $configure['duo_fail'];

                self::$Client = new Client(self::$ikey, self::$skey, self::$api, self::$host);
                
                try { 
                    self::$Client->healthCheck();
                } catch (DuoException $e) {
                    $errorMSG = $e->getMessage();
                    include ('error.php');
                    die();
                }
            }            
        }
    }

    class RequestHandler { 
        
        public function protect($data) { 
            $msg = implode('::', $data);
            return urlencode(str_rot13(base64_encode(str_rot13($_SESSION['state'] . '::' . $msg))));
        }

        public function unprotect($data = '') { 
            $msg = urldecode($data);
            $msg = str_rot13(base64_decode(str_rot13($msg)));
            $msg = explode('::', $msg);
            return $msg;
        }

        public function encryptData($data) {         
            global $INFO;
    
            $iv         = random_bytes(16); // generate a random IV
            $value      = \openssl_encrypt($data, "AES-256-CBC", $INFO["enc_key"], 0, $iv);
            $encrypted  = base64_encode($iv . $value);

            return $encrypted;
        }

        public function decryptData($data) { 
            global $INFO;

            $data       = base64_decode($data);
            $iv         = substr($data, 0, 16);
            $value      = substr($data, 16);
            $decrypted  = \openssl_decrypt($value, "AES-256-CBC", $INFO["enc_key"], 0, $iv);

            return $decrypted;
        }

    }


?>