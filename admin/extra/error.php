<?php
if(!isset($include)) { die('Direct access not permitted'); }
?>

<div class="d-flex align-items-center justify-content-center vh-100">
    <div class="text-center">
        <p class="fs-3"> <span class="text-danger">Opps!</span> An error happened.</p>
        <p class="lead">
            <?php if (isset($errorMSG)) { echo $errorMSG; } else { echo 'A technical error occurred, which means we cannot display the site right now.'; } ?>
        </p>
        <?php if (isset($returnPage)) { ?>
            <a href="<?php echo $returnPage; ?>" class="btn btn-primary">Go Back</a>
        <?php } else { ?>
            <a onclick="window.location.reload();" id="reload_button" class="btn btn-primary">Refresh</a>
        <?php } ?>
    </div>
</div>
    