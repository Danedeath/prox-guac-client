<?php

    include "../integrations/dbConfig.php";

    class LoginHandler {

        // getUserSalt will get the password salt belonging to the specified user from the Guacamole database
        // 
        //  return:
        //      - MYSQL object
        function getUserSalt($username) { 
            global $db;

            $query = "SELECT password_salt FROM guacamole_user WHERE entity_id IN (SELECT entity_id FROM guacamole_entity WHERE name = :name)";
            $query_params = array(
                "name" => $username
            );

            try { 
                $stmt = $db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch (PDOException $ex) {
                die("Failed to fetch user information, using LoginHandler.getUserInfo()!\n" . $ex->getMessage());
            }
            return $result;
        }

        // getEncodedMsg utilizes the MySQL UNHEX function to retrieve the digest for a SHA2 hash from $content with a provided salt
        // 
        // return: 
        //      - 
        function getEncodedMsg($content, $salt) { 

            global $db;

            $query = "SELECT UNHEX(SHA2(CONCAT(:content, HEX(:salt)), 256))"; // Encode the content
            $query_params = array( 
                "content" => $conent,
                "salt"    => $salt
            )

            try { 
                $stmt = $db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch (PDOException $ex) {
                die("Failed to encode message, using LoginHandler.getEncodedMsg()!\n" . $ex->getMessage());
            }
            return $result;
        }

        function getUserID($username, $password) { 
            global $db;

            $query = "SELECT user_id FROM guacamole_user WHERE password_hash = :password AND entity_id IN (SELECT entity_id FROM guacamole_entity WHERE name = :name)";
            $query_params = array( 
                "username" => $username, 
                "password" => $password
            );

            try { 
                $stmt = $db->prepare($query);
                $result = $stmt->execute($query_params);
            } catch (PDOException $ex) {
                die("Failed to fetch user_id, using LoginHandler.getUserID()!\n" . $ex->getMessage());
            }
            return $result;
        }


    }

    class GaucamoleHandler { 

        // TODO
        //  - Add function to validate macaddress for SearchIP, ensures that no bad commands are executed on system

        function searchIP($mac) {
            $out = shell_exec("sudo ./searchIP.sh | fgrep '$mac'");
            $ip = substr($out, strpos($out, "192.168.1."), 13); // Filtra solo la IP de la salida
            return trim($ip);
        }

        function createConnectionw10($username, $ip) {
            global $db;

            $sql = "SELECT connection_id FROM guacamole_connection WHERE connection_name LIKE '%- $username'";
            $query = $db->query($sql);
            if ($query === FALSE) { //Error
                echo "Could not successfully run query ($sql) from DB: " . $db->error;
                exit;
            }
            $connectionid = $query->fetch_row()[0];
        
            $sql = "UPDATE guacamole_connection_parameter SET parameter_value='$ip' WHERE connection_id = '$connectionid' AND parameter_name = 'hostname'";
            $query = $db->query($sql); // Update the IP of the machine in Guacamole
            if ($query === FALSE) { // Error
                echo "Could not successfully run query ($sql) from DB: " . $db->error;
                exit;
            }		
        }
    }

?>