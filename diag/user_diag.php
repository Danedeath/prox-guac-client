<?php 

// disable direct access...
if(!isset($include)) { die('Direct access not permitted'); }

?>
    <link rel=stylesheet href="<?php echo $serverBase."/admin/extra/style.css?".time(); ?>" type="text/css">
    <div class="row pt-5 pe-5">
        <div class="col-md-12">
            <div class="box row" style="height:180px">
                <div class="col-md-10 box-widget widget-user-2">
                    <div class="widget-user-header">
                        <div class="widget-user-image" style="width:100px;">
                            <img width="100" height="100" style="border: 3px solid #d2d6de; width:100; border-radius: 5%; margin-top: 20px;" avatar="<?php echo $user['username']; ?>">
                        </div>
                        <h1 class="widget-user-username">
                            <strong><?php echo $user['username']; ?></strong>
                        </h1>
                        <p class="widget-user-desc">
                            <?php echo $user['email']; ?>
                        </p>
                        <p class="widget-user-desc text-muted" style="margin-top: 15px;">
                            Joined <?php echo explode(' ', $user['reg_date'])[0]; ?>
                        </p>
                        <p class="widget-user-desc text-muted" style="margin-top: -15px;">
                            Last Active <?php echo explode(' ', $user['last_login'])[0]; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="nav-tabs-custom">
        <ul class="nav nav-tabs" role="tablist" id="memberTabs">
            <li class="nav-item Tabs_item">
                <a id="info-tab" data-bs-target="#acc_info" data-bs-toggle="tab" aria-expanded="true" type="button" class="active">User Information</a>
            </li>
            <li class="nav-item Tabs_item">
                <a data-bs-target="#connections" data-bs-toggle="tab" aria-expanded="true" type="button">Connections</a>
            </li>
        </ul>
    </div>

    <div class="row pe-5">
        <div class="col-md-12">
            <div class="tab-content" style="margin-left: -10px !important;">
                <div class="tab-pane fade show active" id="acc_info" role="tabpanel">
                    <div class="box box-body member-form">
                    <?php  if (filter_var($user_perms['userman_edit'],FILTER_VALIDATE_BOOLEAN)) { ?>
                        <form id="accInfoForm" name="accInfoForm" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
                            <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'edituser', implode(':!:', $user)));?>">
                    <?php } ?>
                            <div class="row mb-3 form-group required">
                                <label class="col-sm-2 control-label member-form-labels">Display Name</label>
                                <div class="col-sm-2">  
                                    <?php  if (filter_var($user_perms['userman_edit'],FILTER_VALIDATE_BOOLEAN)) { ?>                              
                                        <input type="text" class="form-control member-form-input" id="username" name="username" value="<?php echo $user['username']; ?>">
                                    <?php } else { ?>
                                        <input type="text" class="form-control member-form-input" id="username" name="username" value="<?php echo $user['username']; ?>" disabled>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php  if (filter_var($user_perms['userman_reset'],FILTER_VALIDATE_BOOLEAN) && filter_var($user_perms['userman_edit'],FILTER_VALIDATE_BOOLEAN)) { ?>
                                <div class="row mb-3 form-group">
                                    <label class="col-sm-2 control-label member-form-labels">Password</label>
                                    <div class="col-sm-2">
                                        <input type="password" class="form-control member-form-input" id="password" name="password" value="">
                                    </div>                                
                                </div>
                                <div class="row mb-3 form-group required">
                                    <label class="col-sm-2 control-label member-form-labels">Confirm Password</label>
                                    <div class="col-sm-2">
                                        <input type="password" class="form-control member-form-input" id="password_conf" name="password_conf" value="">
                                    </div>
                                </div>
                            <?php } ?>
                    <?php  if (filter_var($user_perms['userman_edit'],FILTER_VALIDATE_BOOLEAN)) { ?>
                        </form>
                        <div align="center" class="box-footer">
                            <button type="submit" form="accInfoForm" class="btn btn-primary member-form-submit">Save</button>
                        </div>
                    <?php } ?>
                    </div>
                </div>
                <?php if (filter_var($user_perms['userman_conn'],FILTER_VALIDATE_BOOLEAN)) { ?>
                <div class="tab-pane fade" id="connections" role="tabpanel">
                    <div class="box bg-dark member-connections">
                        <?php include $root.'/admin/templates/connections_table.php'; ?>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
