<?php

    if(!isset($include)) { die('Direct access not permitted'); }

    include "/var/www/html/extra/dbConfig.php";
    include "/var/www/html/extra/config.php";

    require $_SERVER['DOCUMENT_ROOT'].'/../composer/vendor/autoload.php';

    use Proxmox\Request;
    use Proxmox\Access;
    use Proxmox\Nodes;
    use Proxmox\Cluster;
    use \Curl\Curl;

    use ridvanaltun\Guacamole\Guacamole;
    use ridvanaltun\Guacamole\User;
    use ridvanaltun\Guacamole\Connection;
    use ridvanaltun\Guacamole\ConnectionGroup;

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


        /* 
        * getUser will get the user from the database and return it as an array
        * @param $user - the username of the user
        * @return array - the user as an array
        */
        public function getUser($user) { 
            $user = filter_var($user, FILTER_SANITIZE_STRING);

            $query = 'select * from users where email = :user';
            $query_params = array(
                ':user' => $user
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                die("Failed to run query: " . $ex->getMessage());
            }

            return $stmt->fetch();
        }

        /* hash the password with the pepper and the salt from the database! 
         * @param $pass - the password to hash
         * return string - the hashed password
        */
        public function hashPass($pass) { 

            $pass = filter_var($pass, FILTER_SANITIZE_STRING);

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
            $query = 'update users set last_login = :time where username = :user';
            $query_params = array(
                ':time' => time(),
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
        * @param $user - the username of the user
        * @param $pass - the password of the user
        * @return boolean - true if the login is successful, false if not
        */
        public function login($user, $pass) { 

            $user = filter_var($user, FILTER_SANITIZE_STRING);
            $pass = filter_var($pass, FILTER_SANITIZE_STRING);

            $user_info = $this->getUser($user);

            // verify the password is correct!
            if (password_verify($pass, $user_info['password'])) { 

                // check if the password needs to be reshashed!
                if (password_needs_rehash($user_info['password'], PASSWORD_DEFAULT)) {

                    $new_hash = $this->hashPass($pass);

                    $this->updatePassword($user, $new_hash);
                }

                $this->updateLastLogin($user);
                return true;

            } else { return false; }
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
            $query = 'select * from reset_tokens where username = :user and reset_token = :token';
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
                    'message' => 'Invalid token!'
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
        public function deleteToken($data) { 

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

            return true;
        }

    }

    class UserHandler { 

        protected $db; 

        public function __construct($db) { 
            global $DB;

            if ($db instanceof PDO) { 
                $this->db = $db;
            } else { 
                $this->db = $DB;
            }
        }

        /* 
        * getPerms will collect the permissions of the user
        * @param $userid - the userid of the user
        * @return array - the permissions of the user
        */
        public function getPerms($userid) { 

            $userid = (int) filter_var($userid, FILTER_SANITIZE_NUMBER_INT);

            $query = 'select * from permissions where userid = :userid';
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

        /*
        * getPerm will collect the provided permission of the user
        * @param $userid - the userid of the user
        * @param $perm - the permission to collect
        * @return boolean - the permission of the user
        */
        public function getPerm($userid, $perm) { 

            $userid = (int) filter_var($userid, FILTER_SANITIZE_NUMBER_INT);
            $perm = filter_var($perm, FILTER_SANITIZE_STRING);
            
            $query = 'select * from permissions where userid = :userid';
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
                
            $query = 'update permissions set ' . $perm . ' = :value where userid = :userid';
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

        public function getUserSettings($userid) { 

            $userid = (int) filter_var($userid, FILTER_SANITIZE_NUMBER_INT);
                
            $query = 'select * from user_settings where userid = :userid';
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

            $query = 'select * from user_settings where userid = :userid';
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

            $query = 'update user_settings set ' . $setting . ' = :value where userid = :userid';
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
            $query = 'select id, username, email, reg_date, login_date from users';

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
            $query = 'select id, username, email, reg_date, login_date  from users where id = :userid';
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
            $query = 'select id from users where is_admin = 1';
            
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
                    0 => array( 0 => 'delete from permissions where userid = :id', 1 => array(':id' => $userData['id'] )),
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
    }

    class ConnectionManager { 

        protected $db;

        public function __construct($db) { 
            global $DB;

            if ($db instanceof PDO) { 
                $this->db = $db;
            } else { 
                $this->db = $DB;
            }
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

            $row = $stmt->fetch();

            return $row;
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
            $conn_data['password'] = $this->protectPassword($conn_data, $conn_data['password']);

            $query = 'insert into connections (name, hostname, port, username, password, owner, protocol, os) values (:name, :host, :port, :username, :password, :owner, :protocol, :os)';
            $query_params = array(
                ':name' => $conn_data['name'],
                ':host' => $conn_data['host'],
                ':port' => $conn_data['port'],
                ':username' => $conn_data['username'],
                ':password' => $conn_data['password'],
                ':owner' => $conn_data['owner'],
                ':protocol' => $conn_data['protocol'],
                ':os' => $conn_data['os']
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

            // get the existing connection data, if the name, host, or port has changed; update the password encryption!
            $existing_conn = filter_var($this->getConnection($conn_data['id']));

            // check if the connection actually exists...
            if ($existing_conn == false || empty($existing_conn) || is_null($existing_conn)) { 
                return array(
                    'status' => 'error',
                    'message' => '<strong>Failed to update the connection,</strong>Connection does not exist'
                );
            }

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

            if ($existing_conn['name'] != $conn_data['name'] || $existing_conn['host'] != $conn_data['host'] || $existing_conn['port'] != $conn_data['port']) { 
                $conn_data['password'] = $this->unprotectPassword($existing_conn, $existing_conn['password']);
                $conn_data['password'] = $this->protectPassword($conn_data, $conn_data['password']);
            }

            $query = 'update connections set name = :name, host = :host, port = :port, username = :username, password = :password, modified = CURRENT_TIMESTAMP() where id = :id';
            $query_params = array(
                ':name' => $conn_data['name'],
                ':host' => $conn_data['host'],
                ':port' => $conn_data['port'],
                ':username' => $conn_data['username'],
                ':password' => $conn_data['password'],
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

        // protectPassword will encrypt the password using the connection data, this will allow for a secure method of storing passwords in the DB.
        // @param $conn_data - the connection data to use for encryption
        // @param $password - the password to encrypt
        // @return string - the encrypted password
        protected function protectPassword($conn_data, $password) { 
            
            // generate the key using portions of the connection data!

            $key = $_SERVER['SERVER_NAME'] . $conn_data['host'] . $conn_data['port'] . $conn_data['username'];
            $key = hash('sha256', $key);

            $iv = random_bytes(16); // generate a random IV
            $value = \openssl_encrypt($password, "AES-256-CBC", $INFO["enc_key"], 0, $iv);

            $encrypted = base64_encode($iv . $value);

            return $encrypted;
        }

        // unprotectPassword will decrypt the password using the connection data, this will be used for updating the password & connecting to the machine.
        // @param $conn_data - the connection data to use for decryption
        // @param $password - the password to decrypt
        protected function unprotectPassword($conn_data) { 

            $key = $_SERVER['SERVER_NAME'] . $conn_data['host'] . $conn_data['port'] . $conn_data['username'];
            $key = hash('sha256', $key);

            $data = base64_decode($conn_data['password']);
            $decrypted = \openssl_decrypt(substr($data, 16), "AES-256-CBC", $INFO["enc_key"], 0, substr($data, 0, 16));

            return $decrypted;
        }
    }

    class GuacLoginHandler {

        // getUserSalt will get the password salt belonging to the specified user from the Guacamole database
        // 
        //  return:
        //      - MYSQL object
        function getUserSalt($username) { 
            global $DB;

            $query = "SELECT password_salt FROM guacamole_user WHERE entity_id IN (SELECT entity_id FROM guacamole_entity WHERE name = :name)";
            $query_params = array(
                ":name" => $username
            );

            try { 
                $stmt = $DB->prepare($query);
                $stmt->execute($query_params);
            } catch (PDOException $ex) {
                die("Failed to fetch user information, using LoginHandler.getUserInfo()!\n" . $ex->getMessage());
            } catch (Exception $ex) {
                die("Failed to fetch user information, using LoginHandler.getUserInfo()!\n" . $ex->getMessage());
            }

            return $stmt->fetch()['password_salt'];
        }

        function registrationStatus() { 
            global $INFO;

            return boolval($INFO['enable_registration']);
        }

        // getEncodedMsg utilizes the MySQL UNHEX function to retrieve the digest for a SHA2 hash from $content with a provided salt
        // 
        // return: 
        //      - 
        function getEncodedMsg($content, $salt) { 

            global $DB;

            $query = "SELECT UNHEX(SHA2(CONCAT($content, HEX($salt)), 256))"; // Encode the content

            try { 
                $stmt = $DB->prepare($query);
                $stmt->execute();
            } catch (PDOException $ex) {
                die("Failed to encode message, using LoginHandler.getEncodedMsg()!\n" . $ex->getMessage());
            }
            return $stmt->fetch();
        }

        function getUserID($username, $password) { 
            global $DB;

            $query = "SELECT user_id FROM guacamole_user WHERE password_hash = ':password' AND entity_id IN (SELECT entity_id FROM guacamole_entity WHERE name = ':name')";
            $query_params = array( 
                ":name" => $username, 
                ":password" => $password
            );

            try { 
                $stmt = $DB->prepare($query);
                $stmt->execute($query_params);
            } catch (PDOException $ex) {
                die("Failed to fetch user_id, using LoginHandler.getUserID()!\n" . $ex->getMessage());
            }
            return $stmt->fetch()['user_id'];
        }

        function escapeString($msg) { 
            global $DB;

            return $DB->real_escape_string($msg);
        }

        function login($user, $pass) { 
            global $DB;
            global $INFO;

            # "select * from guacamole_user u inner join guacamole_entity e on u.entity_id = e.entity_id where u.name = :username and u.password_hash = UNHEX(SHA2(CONCAT(:password, HEX(u.password_salt)), 256))";
			# $query = "SELECT * FROM guacamole_user WHERE password_hash = UNHEX(SHA2(CONCAT(:password, HEX(SELECT password_hash from )), 256))  AND entity_id IN (SELECT entity_id FROM guacamole_entity WHERE name = ':username')";
            $query = "select * from guacamole_user u inner join guacamole_entity e on u.entity_id = e.entity_id where e.name = :username and u.password_hash = UNHEX(SHA2(CONCAT(:password, HEX(u.password_salt)), 256))";
            $query_params = array( 
                ":username" => $user, 
                ":password" => $pass
            );

            try { 
                $stmt = $DB->prepare($query);
                $stmt->execute($query_params);
            } catch (PDOException $ex) {
                die("Failed to fetch user information, using LoginHandler.getUserInfo()!\n" . $ex->getMessage());
            }

            $result = $stmt->fetchAll();

            if(!empty($result)) { // successful login, create the new session

                if (session_status() === PHP_SESSION_NONE) { // ensure a session is started
                    session_start();
                }

                $_SESSION["loggedin"] = true;
                $_SESSION["username"] = $user;

                return true;

            } else { 
                return false;
            }           
        }

        function logout() { 
            session_start();
            // Unset all of the session variables
            $_SESSION = array();
            // Destroy the session.
            session_destroy();
        }

        function getAllUsers() { 
            global $DB;
            global $INFO;

            # "select * from guacamole_user u inner join guacamole_entity e on u.entity_id = e.entity_id where u.name = :username and u.password_hash = UNHEX(SHA2(CONCAT(:password, HEX(u.password_salt)), 256))";
			# $query = "SELECT * FROM guacamole_user WHERE password_hash = UNHEX(SHA2(CONCAT(:password, HEX(SELECT password_hash from )), 256))  AND entity_id IN (SELECT entity_id FROM guacamole_entity WHERE name = ':username')";
            $query = "select * from guacamole_user u inner join guacamole_entity e on u.entity_id = e.entity_id";
            try { 
                $stmt = $DB->prepare($query);
                $stmt->execute();
            } catch (PDOException $ex) {
                die("Failed to fetch user information, using LoginHandler.getAllUsers()!\n" . $ex->getMessage());
            }

            $users = array();
            foreach($stmt->fetchAll() as $row) { 
                array_push($users, $row);
            }
            return $users;
        }
    }

    class GaucamoleHandler { 

        protected static $host;
        protected static $port;
        protected static $username;
        protected static $password;
        protected static $authToken;

        protected static $Connection;
        protected static $Client;
        protected static $User;
        protected static $ConnectionGroup;

        public static $avail_conns      = array();
        public static $avail_groups     = array();
        public static $avail_conngroups = array();

        function __construct(array $configure) { 
            if (!empty($configure)) { 
                self::$host = ($configure['proxy'] ? 'https://' : (empty($_SERVER['HTTPS']) ? 'http://' : 'https://')).$configure['guac_host'].(!empty($configure['port']) ? $configure['guac_port'] : '');
                
                self::$Client = new Guacamole('https://gerbil.zhoslarkoat.com/', $configure['guac_user'], $configure['guac_pass'], ['verify' => false]);
                self::$Connection = new Connection(self::$Client);
                self::$User = new User(self::$Client);
                self::$ConnectionGroup = new ConnectionGroup(self::$Client);
                
            } else { 
                $errorMSG = "Failed to create GuacamoleHandler object, no configuration provided!";
                include 'error.php';
                die();
            }

            self::$avail_conns = self::$Connection->list();
        }

        // TODO
        //  - Add function to validate macaddress for SearchIP, ensures that no bad commands are executed on system
        function searchIP($mac) {
            $out = shell_exec("sudo ./searchIP.sh | fgrep '$mac'");
            $ip = substr($out, strpos($out, "192.168.1."), 13); // Filtra solo la IP de la salida
            return trim($ip);
        }

        function createConnectionw10($username, $ip) {
            global $DB;

            if (self::$avail_conns) { }
        }

        function getConns() { 
            return self::$Connection->list();
        }

        function getConn($conn_id = null, $conn_name = null) { 
            if (!empty($conn_id)) { 
                return self::$Connection->details($conn_id);
            } else if (!empty($conn_name)) { 
                foreach (self::$Connection->list() as $conn) { 
                    if (stripost($conn['name'], $conn_name) !== false) { 
                        return $conn;
                    }
                }
            } 
            return NULL;
        }

        function buildConnection($connection) { 
            global $INFO;

            $conn_str = $connection['identifier'].chr(0).'c'.chr(0).'mysql';
            $conn_str = str_replace('=', '', base64_encode($conn_str));
            return self::$host."/#/client/".$conn_str;
            ;
        }

        function findConnection($conn_name) { 
            foreach (self::$Connection->list() as $conn) { 
                if (stripos($conn['name'], $conn_name) !== false) { 
                    return $conn;
                }
            }
            return NULL;
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
        function generateConn_new($vm) { 
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
                                "username"                   => $vm['user'],
                                "password"                   => $vm['pass'],
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

        function generateConn($vm, $token = False) { 
            global $_SESSION;
            global $INFO;

            if (!empty($vm)) { 
                
                // check if the vm is online or if the the qemu guest agent is running
                if ($vm['conn'] !== NULL && stripos($vm['conn'], 'running') === false) { 
                    $data = array(
                        'hostname' 	                 => $vm['conn'],
                        'port' 	 	                 => 3389,
                        "username"                   => $vm['user'],
                        "password"                   => $vm['pass'],
                        "enable-desktop-composition" => "true",
                        "enable-menu-animations"     => "true",
                        "drive-path"                 => $INFO['guac_drive'],
                        "drive-name"                 => "guac",
                        "normalize-clipboard"        => "preserve",
                        "create-drive-path"          => "true",
                        "enable-font-smoothing"      => "true",
                        "enable-full-window-drag"    => "true",
                        "enable-drive"               => "true",
                        "enable-wallpaper"           => "true",
                        "enable-theming"             => "true",                               
                        "ignore-cert"                => "true",
                    );

                    // search for an existing connection in guacamole, if one does not exist, create one
                    // if one does exist, update the connection with the new IP address
                    $conn = self::findConnection($vm['name']."-".$_SESSION['username']);
                    if ($conn === NULL) { 
                        
                        if (stripos($vm['os'], 'windows') !== false) { 
                            if (!empty($data)) { 
                                # $data['connection']['type'] = 'rdp';
                                $conn = self::$Connection->createRdp($vm['name']."-".$_SESSION['username'],$vm['conn'], 3389, $data);
                                return self::buildConnection($conn);
                            }
                        } else { 
                            if (!empty($data)) { 
                                # $data['connection']['type'] = 'rdp';
                                $data['username'] = 'user';
                                $conn = self::$Connection->createRdp($vm['name']."-".$_SESSION['username'],$vm['conn'], 3389, $data);
                                return self::buildConnection($conn);
                            }
                        }

                    } else { 
                        return self::buildConnection($conn);
                    }
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
            } catch (Exception $ex) {
                
                $errorMSG = "Failed to connect to Proxmox API!";
                include('error.php');
                die();
            }

            self::$guacamole = new GaucamoleHandler($INFO);
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
                            $status  = self::$nodes::qemuCurrent($node['name'], $vm->vmid)->data;
                            $os_info = self::getVMOsInfo($node['name'], $vm->vmid)->data;
                            $config  = explode('::', self::getVMConfig($vm->vmid, $node['name'])->description);
                            $data = array(
                                'disk'   => ($vm->maxdisk > 1073741824) ? ($vm->maxdisk / 1073741824) . 'Gb' : ($vm->maxdisk / 1048576) . 'Mb',
                                'uptime' => $vm->uptime, 
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
                                'token'  => NULL,
                                'user'   => $config[0],
                                'pass'   => $config[1],
                            );
                            $data['guac']  = self::$guacamole->generateConn($data);
                            $data['token'] = array(
                                'conn_start', $data['name'], self::$guacamole->generateConn_new($data)
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
                        if (stripos($vm->name, $user['name']) !== false) {
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
                            $data['token'] = array(
                                'conn_start', $data['name'], self::$guacamole->generateConn_new($data)
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
            $data['guac'] = self::$guacamole->generateConn($data);

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

        function consoleVM($vmid = null, $name = null, $node = null, $handler = null) { 
            global $INFO;

            if ($handler == null) { 
                $handler = new GuacamoleHandler($INFO);
            }

            if ($node == null) { 
                $node = self::getNode($vmid);
            }

            if ($vmid != null) { 
                $vmid = self::getVMID($vmid, $node);
            }

            $hanlder->createConnection($vmid, $node);
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
                    self:;$Client->healthCheck();
                } catch (DuoException $e) {
                    $errorMSG = $e->getMessage();
                    include ('error.php');
                    die();
                }
            }            
        }
    }

    class RequestHandler { 
        
        function protect($data = array()) { 
            $msg = implode('::', $data);
            return urlencode(str_rot13(base64_encode(str_rot13($_SESSION['state'] . '::' . $msg))));
        }

        function unprotect($data = '') { 
            $msg = urldecode($data);
            $msg = str_rot13(base64_decode(str_rot13($msg)));
            $msg = explode('::', $msg);
            return $msg;
        }
    }


?>