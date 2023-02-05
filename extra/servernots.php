<?php 

// ensure no one can access this file directly
# if(!isset($include)) { die('Direct access not permitted'); }
# 
// build the alerts options!

if ($_SERVER['REQUEST_METHOD'] == 'GET' || $_SERVER['REQUEST_METHOD'] == 'POST') { // use the post formatting for handling alerts!
    if (!empty($alert_msg) && isset($alert_msg['head'])) { ?>
        <div class="row pb-5">
            <div class="alert alert-<?php echo $alert_msg['color']; ?> alert-dismissible fade show" role="alert">
                <h4 class="alert-heading"><?php echo $alert_msg['head']; ?></h4>
                <p><?php echo $alert_msg['msg']; ?></p>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" id="alertmsg"></button>
            </div>
        </div>
    <?php }
    if ($online_nodes != count($nodes)) { ?>
        <div class="row pb-5">
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <h4 class="alert-heading">Some nodes are offline!</h4>
                <p>Some nodes are offline, please contact an administrator.</p>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" id="alertmsg"></button>
            </div>
        </div>
    <?php } 
    if (isset($_GET['msg'])) { // dynamicaly display alert!
        $msg = $requestHandler->unprotect(filter_var($_GET['msg'], FILTER_SANITIZE_STRING));
    ?>
        <div class="row pb-5">
            <div class="alert alert-<?php echo $msg[1]; ?> alert-dismissible fade show" role="alert">    
                <p><strong><?php echo $msg[2]; ?></strong><?php echo $msg[3]; ?></p>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" id="alertmsg"></button>
            </div>
        </div>
    <?php unset($msg);
    }

    if (isset($_GET['msg']) || $online_nodes != count($nodes) || !empty($alert_msg)) { ?>
        <script>
            var alertmsg = document.getElementById('alertmsg');
            alertmsg.addEventListener('click', function() {
                window.location.href = window.location.href.split('?')[0];
            });
        </script>
    <?php }
} ?>
