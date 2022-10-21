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

        // store the database connector and the pepper as variables in the class!
        function __construct() {
            global $DB;
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

            $query = 'select * from users where username = :user';
            $query_params = array(
                ':user' => $user
            );

            try { 
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch(PDOException $ex) { 
                die("Failed to run query: " . $ex->getMessage());
            }

            $row = $stmt->fetch();
        }

        /* hash the password with the pepper and the salt from the database! 
         * @param $pass - the password to hash
         * return string - the hashed password
        */
        public function hashPass($pass) { 

            $pass = filter_var($pass, FILTER_SANITIZE_STRING);

            // encrypt the combined password!
            $pass = password_hash($pass . $this->$pepper, PASSWORD_BCRYPT);

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
        public function checkLogin($user, $pass) { 

            $user = filter_var($user, FILTER_SANITIZE_STRING);
            $pass = filter_var($pass, FILTER_SANITIZE_STRING);

            $user_info = $this->getUser($user);

            // verify the password is correct!
            if (password_verify($pass, $user_info['password'])) { 
                $this->updateLastLogin($user);
                return true;
            } else { 
                return false;
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
            $data['email']    = filter_var($data['email'], FILTER_VALIDATE_EMAIL);           
            
            if ($data['email'] === false) { 
                return array(
                    'status' => 'error',
                    'message' => 'Invalid email address!'
                );
            }

            // check if the email is already in use!
            $existing = $this->getUser($data['email']);

            if ($existing !== false) { 
                return array(
                    'status' => 'error',
                    'message' => 'Email address already in use!'
                );
            } else { 
                if (is_array($data['password'])) { 
                    return $data['password'];
                } else { 
    
                    $query = "insert into users (username, password, emial) values (:user, :pass, :email)";
                    $query_params = array(
                        ':user' => $data['username'],
                        ':pass' => $this->hashPass($data['password']),
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
                        'message' => 'User registered successfully!'
                    );
                }
            }

           
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

        function getVMNetworking($node, $vmid) { 
            $data = self::$nodes::qemuAgentNetwork($node, $vmid);

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
                            $os_info = self::$nodes::qemuOsInfo($node['name'], $vm->vmid)->data;
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

        function getVMConfig($vmid, $node = null) { 
            if ($node == null) { 
                $node = self::getNode($vmid);
            }
            return self::$nodes::qemuConfig($node, $vmid)->data;
        }

        function getVM($vmid, $node = null) { 
            if ($node == null) { 
                $node = self::getNode($vmid);
            }

            $vm   = self::$nodes::qemu($node, $vmid)->data;

            $status  = self::$nodes::qemuCurrent($node['name'], $vm->vmid)->data;
            $os_info = self::$nodes::qemuOsInfo($node['name'], $vm->vmid)->data;
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
            @param $node: The node to create the snapshot of the VM in
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
            @param $node: The node to create the snapshot of the VM in
        */
        function deleteSnap($vmid = null, $name = null, $node = null) { 

            if ($node == null) { 
                $node = self::getNode($vmid);            
            }

            return self::$nodes::deleteQemuSnapshot($node, $vmid, $name);
        }

        /* 
            revertSnap will revert a VM to a snapshot
            @param $vmid: The ID of the VM to revert
            @param $name: The name of the VM to revert
            @param $node: The node to revert the VM in
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
            @param $node: The node to start the VM in
        */
        function startVM($vmid = null, $name = null, $node = null) { 

            if ($node == null) { 
                $node = self::getNode($vmid);            
            }

            return self::$nodes::QemuStart($node, $vmid);
        }

        function stopVM($vmid = null, $name = null, $node = null) { 

            if ($node == null) { 
                $node = self::getNode($vmid);            
            }

            return self::$nodes::QemuStop($node, $vmid);
        }

        function deleteVM($vmid = null, $name = null, $node = null) { 

            if ($node == null) { 
                $node = self::getNode($vmid);            
            }
            return self::$nodes::qemuDelete($node, $vmid);
        }

        function rebootVM($vmid = null, $name = null, $node = null) { 

            if ($node == null) { 
                $node = self::getNode($vmid);
            }

            return self::$nodes::QemuReboot($node, $vmid);
        }

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