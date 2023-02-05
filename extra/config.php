<?php
    
    if(!isset($include)) { die('Direct access not permitted'); }

    $INFO = array( 

        // database for dashboard, switching from the usage of the guacamole database!
        'sql_host'     => '',
        'sql_port'     => '3306',
        'sql_user'     => '',
        'sql_pass'     => '',
        'sql_db'       => 'dashboard',
        'sql_prefix'   => '',
        
        /* guacamole-lite connection settings */
        'guacd_secret'  => '', // guacamole host authentication secret
        'guacd_host'    => '', // guacd server hostname
        'guacd_port'    => '', // guacd server port
        'guacd_token'   => '', // guacd api server token, must match token guacd uses!
        'guacd_enc'     => '', // guacd AES encryption key
        'guacd_drive'   => '', // guacd drive path

        // proxmox configuration settings
        'max_instances' => 5,  // maximum number of VMs to allow per user
        'prox_host'     => '', // proxmox host
        'prox_port'     => '', // proxmox port
        'prox_key'      => '', // proxmox API key
        'prox_key_id'   => '', // proxmox API key ID
        'prox_user'     => '', // proxmox user
        'prox_pass'     => '', // proxmox password, not required if using an API key
        'default_store' => '', // default storage location for new VMs created using the dashboard

        // duo authencation settings
        'enable_duo'    => true, // enable duo authentication    
        'duo_ikey'      => '', // duo integration key
        'duo_skey'      => '', // duo secret key
        'duo_redir'     => '/login/login.php', // duo callback url
        'duo_api'       => '', // duo api url
        'duo_fail'      => '', // duo failure mode                    

        // general settings
        'proxy'         => true, // enable proxy support, assumes protocol is https
        'pepper'        => '', // pepper for passwords, only used if using the native DB!
        'enable_registration' => false, // enable user registration
        'enc_key'       => '', // encryption key for some data stored at rest, AES-256

        'cape_sandbox'  => '', // link to cape sandbox
    );

?>