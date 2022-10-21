<?php
if(!isset($include)) { die('Direct access not permitted'); }
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Oops! Error</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-dark text-light">
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
    </body>
</html>
