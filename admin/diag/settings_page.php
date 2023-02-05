<?php 
// disable direct access...
if(!isset($include)) { die('Direct access not permitted'); }

$action_get = (isset($_GET['action'])) ? filter_var($_GET['action'], FILTER_SANITIZE_STRING) : '';
$settingID  = (isset($_GET['id'])) ? filter_var($_GET['id'], FILTER_SANITIZE_STRING) : '';

$setting_cats = $settHandler->getSettingTypes();

$breadcrumbs = array(
    'Settings Management' => 'settings.php'
);

$guacd_settings   = array();
$proxmox_settings = array();
$general_settings = array();
$guac_settings    = array();

?>

<link href="https://gitcdn.github.io/bootstrap-toggle/2.2.2/css/bootstrap-toggle.min.css" rel="stylesheet">
    <script src="https://gitcdn.github.io/bootstrap-toggle/2.2.2/js/bootstrap-toggle.min.js"></script>
<div class="row position-absolute main-body">
    
    <section class="content-header pull-right">
        <h2>
            Settings Management
            <small>
                Proxmox Admin Panel v0.0.1
            </small>
        </h2>
        <ol class="breadcrumb pe-5">
            <li>
                <a href="index.php">
                    <i class="fa fa-dashboard"></i> 
                    Home 
                </a>
            </li>
            
            <?php foreach ($breadcrumbs as $key => $value) {
                if (array_key_last($breadcrumbs) == $key) {
                    echo '<li class="active">'.$key.'</li>';
                } else {
                    echo '<li><a href="'.$value.'">'.$key.'</a></li>';
                }
            }
            ?>
        </ol>
    </section>
    <div class="row pt-5 pe-5">
        <?php if (isset($alert_msg)) { ?>
            <div class="alert alert-<?php echo ($alert_msg['status'] == 'success') ? 'success' : 'danger'; ?> alert-dismissible" role="alert"> 
                <?php echo $alert_msg['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" id="alertmsg"></button>
            </div>
        <?php } ?>
        <form class="row row-cols-1 row-cols-md-2 g-3" id="settingsForm" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
            <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'edit')); ?>">
            <?php foreach($setting_cats as $cat) {  ?>
                <div class="col">
                    <div class="card bg-dark setting-card  h-100">
                        <div class="card-body">
                            <?php
                                switch($cat) { 
                                    case 'guacd': echo '<h5 class="card-title">Guac Daemon Server</h5>'; break;
                                    case 'prox':  echo '<h5 class="card-title">Promox Configuration</h5>'; break;
                                    case 'gen':  echo '<h5 class="card-title">General Settings</h5>'; break;
                                    case 'guac': echo '<h5 class="card-title">Guacamole Configuration</h5>'; break;
                                    case 'tog':  echo '<h5 class="card-title">Toggle Settings</h5>'; break;
                                    default: echo '<h5 class="card-title">Unknown</h5>'; break;
                                }
                            
                                foreach($settHandler->getSettingsByType($cat) as $setting) { ?>
                                <div class="row mb-3 form-group required">
                                    
                                    <?php 
                                        $input_type = 'text';
                                        $sett_value = $setting['value'];
                                        $temp       = explode('_', $setting['name']);
                                        if (in_array(end($temp), array('key', 'secret', 'pass', 'token'))) { 
                                            $input_type = 'password'; 
                                            $sett_value = $requestHandler->decryptData($sett_value);
                                        } else if (in_array(end($temp), array('port', 'inst'))) { 
                                            $input_type = 'number';
                                        }

                                    if ($cat == 'tog') { ?> 
                                    <div class="from-group form-check form-switch ms-3">
                                        <?php if (filter_var($user_perms['sett_disable'],FILTER_VALIDATE_BOOLEAN) || filter_var($user_perms['sett_enable'],FILTER_VALIDATE_BOOLEAN)) { ?>
                                            <input class="form-check-input" type="checkbox" role="switch" id="<?php echo $setting['name']; ?>" name="<?php echo $setting['name']; ?>" <?php echo filter_var($sett_value, FILTER_VALIDATE_BOOLEAN) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="flexSwitchCheckDefault"><?php echo $setting['display']; ?></label>
                                        <?php } else {?>
                                                <span class="form-check-label" for="flexSwitchCheckDefault"><?php echo $setting['display']; ?></span>
                                                <div class="form-check-input <?php echo ($sett_value == 1) ? 'checked' : ''; ?>" type="checkbox" role="switch" id="<?php echo $setting['name']; ?>" name="<?php echo $setting['name']; ?>"></div>
                                        <?php } ?>
                                    </div>
                                    <?php } else { ?>
                                        <label class="col-4 setting-form-labels"><?php echo $setting['display']; ?></label>
                                        <?php 
                                            if ($setting['name'] == 'default_store') { 
                                                echo '<div class="col-6">';

                                                if (filter_var($user_perms['sett_disable'],FILTER_VALIDATE_BOOLEAN) || filter_var($user_perms['sett_enable'],FILTER_VALIDATE_BOOLEAN)) { ?>
                                                    <select class="form-control setting-form-input" id="<?php echo $setting['name']; ?>" name="<?php echo $setting['name']; ?>">
                                                        <?php foreach($proxmox->getStorage() as $storage) { ?>
                                                            <option value="<?php echo $storage; ?>" <?php echo ($sett_value == $storage) ? 'selected' : ''; ?>><?php echo $storage; ?></option>
                                                        <?php } ?>
                                                    </select>
                                                <?php } else { ?>
                                                    <input type="<?php echo $input_type; ?>" class="form-control setting-form-input" id="<?php echo $setting['name']; ?>" name="<?php echo $setting['name']; ?>" value="<?php echo $sett_value; ?>" disabled>
                                                <?php }
                                                echo '</div>';
                                            } else {
                                        ?>
                                        <div class="col-6">
                                            <?php if (filter_var($user_perms['sett_disable'],FILTER_VALIDATE_BOOLEAN) || filter_var($user_perms['sett_enable'],FILTER_VALIDATE_BOOLEAN)) { ?>
                                                <input type="<?php echo $input_type; ?>" class="form-control setting-form-input" id="<?php echo $setting['name']; ?>" name="<?php echo $setting['name']; ?>" value="<?php echo $sett_value; ?>">
                                            <?php } else { ?>
                                                <input type="<?php echo $input_type; ?>" class="form-control setting-form-input" id="<?php echo $setting['name']; ?>" name="<?php echo $setting['name']; ?>" value="<?php echo $sett_value; ?>" disabled>
                                            <?php } ?>
                                        </div>
                                    <?php    }
                                        } ?>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </form>
        <?php if (filter_var($user_perms['sett_edit'],FILTER_VALIDATE_BOOLEAN)) { ?>
            <div class="mt-5" align="center">
                <button type="submit" form="settingsForm" class="btn btn-primary member-form-submit">Save</button>
            </div>
        <?php } ?>


        