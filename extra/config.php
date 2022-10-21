<?php
    
    if(!isset($include)) { die('Direct access not permitted'); }

    $INFO = array( 

        // database for dashboard, switching from the usage of the guacamole database! (not working yet)
        'dash_login'    => false,
        'sql_host'     => 'localhost',
        'sql_port'     => '3306',
        'sql_user'     => '',
        'sql_pass'     => '',
        'sql_db'       => '',
        'sql_prefix'   => '',

        // guacamole database connection information
        'guac_login'         => true,                                   // enable guacamole database authentication service
        'guac_sql_host'      => '',                                     // IP address of the database server hosting the guacamole database
        'guac_sql_port'      => '3306',                                 // port of the database server hosting the guacamole database
        'guac_sql_user'      => '',                                     // username of the database user with access to the guacamole database
        'guac_sql_pass'      => '',                                     // password of the database user with access to the guacamole database
        'guac_sql_database'  => 'guacamole_db',                         // name of the guacamole database
        
        /* guacamole connection information, used for building the connection strings */
        'guac_host'     => '',                                           // guacamole server hostname
        'guac_port'     => '',                                           // guacamole server port          
        'guac_user'     => '',                                           // guacamole API username 
        'guac_pass'     => '',                                           // guacamole API password
        'guac_auth'     => 'mysql',                                      // guacamole host authentication method
        'guac_drive'    => '',                                           // guacamole shared drive path
        
        /* guacamole-lite connection settings */
        'guacd_secret'  => '',                                           // guacamole host authentication secret (not used yet)
        'guacd_host'    => '',                                           // guacd server
        'guacd_port'    => '',                                           // guacd server port
        'guacd_token'   => '',                                           // guacd api server token, must match token guacd uses! (not used yet)
        'guacd_enc'     => '',                                           // guacd AES encryption key
        'guacd_drive'   => '',                                           // guacd drive path

        // proxmox configuration settings
        'max_instances' => 5,                                            // maximum number of VMs to allow per user
        'prox_host'     => '',                                           // proxmox host
        'prox_port'     => '8006',                                       // proxmox port
        'prox_key'      => '',                                           // proxmox API key
        'prox_key_id'   => '',                                           // proxmox API key ID
        'prox_user'     => '',                                           // proxmox user
        'prox_pass'     => '',                                           // proxmox password, not required if using an API key
        'default_store' => '',                                           // default storage location for new VMs created using the dashboard

        // duo authencation settings
        'enable_duo'    => true,                                         // enable duo authentication    
        'duo_ikey'      => '',                                           // duo integration key
        'duo_skey'      => '',                                           // duo secret key
        'duo_redir'     => '/login/login.php',                           // duo callback url
        'duo_api'       => '',                                           // duo api url
        'duo_fail'      => 'closed',                                     // duo failure mode                    

        'proxy'         => true,                                         // enable proxy support, assumes protocol is https
        'pepper'        => '',                                           // pepper for passwords, only used if using the native DB!
    );

?>