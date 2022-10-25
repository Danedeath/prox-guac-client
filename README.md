# prox-guac-client

Originally inspired by [Oscar Boronat](https://github.com/osc3b/proxmox-guacamole-client).

Custom integration of Proxmox and Guacamole (Guacamole or Guacamole-lite). If using a Guacamole-Lite implementation a custom console is provided, still a WIP but mostly works.

Features: 

- Clone templates (currently a template must contain 'Temp' in the name)
- VM Management
  - Snapshotting (delete/create/revert)
  - Start/Stop/Pause/Resume
  - Template cloning 
  - VM Deletion

- Guacamole-Lite implementation!
  - Custom console (RDP only, WIP)
  - Copy/Paste
  - File upload/download
  - Guacamole-common usage
  - Shared-drives

- Hopefully easy configuration
- Composer for package management! (defualt location is ```$_SERVER['DOCUMENT_ROOT'].'/../composer/'```)
- Bootstrap 5.0

Open Source software used in the project:
- Proxmox VE 7.2: https://www.proxmox.com/en/proxmox-ve
- MariaDB/MYSQL
- PHP 7.4 or 8.0: https://www.php.net/

# Assumptions
- All VMs being used by this system are required to have the QEMU guest agent running
- Proxmox API token account must be able to create VM's and all the required sub perms
- QEMU access
- Cluster resource access


# Setup

1. Install composer packages with composer.json!
   - be sure to install it in a non public directory!

2. Setup guacamole using the MYSQL authentication (currently required w/o 2 factor)
   - Guacamole web API sucks, currently working to switch to a standalone auth

3. Configure the prox-guac-client by modifying /extra/config.php with the required information
   - Currently required: 
     - guacamole database connection
     - guacamole connection 
     - proxmox configuration settings
     - duo configuration
     
# Todo

- Transition from Guacamole Auth to custom Auth
- Implement more statistics for VMs
- Possibly replace display of VMs from a table to cards
- Add password reset (for VM) and automatically poll user accounts 
- Add an administration backend
- Add profile page
- Add settings page
- Add notifications on download/upload in guacd console
- Add ability to disable DUO
- Add TOTP option
- SQL Items: 
  - Securely store VM connection information
  - Add permission system 
  - Add session storage
  - Add account reset systems
- Add account throttling

# Images

![Login Page](/images/login.png)

![Dashboard](/images/dashboard.PNG)

![VM Management](/images/vm_man.png)

![Snapshot Management](/images/snapman.png)