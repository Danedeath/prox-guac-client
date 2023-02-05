<?php 

$include = "1";

include $_SERVER['DOCUMENT_ROOT']."/install/header.php";
include $_SERVER['DOCUMENT_ROOT']."/install/functions.php";

if (isset($_GET['data'])) { // data being sent with a post, read it to determine the action requested!

    $data = $_GET['data'];
    $data = $requestHandler->unprotect($data);
    
    if ($data['action'] == 'continue') {
        if ($data['continue'] == 1) {
            $requestHandler->redirect('/install/step2.php');
        }
    }
}

?>

<div class="row pt-5 ps-5 pe5">

    <form class="form-signin" name="frmregister" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" >
        <input type="hidden" name="data" value="<?php echo $requestHandler->protect(array('action', 'continue', 1)); ?>" />
        <h1 class="h3 mb-3 font-weight-normal">Installation Page</h1>


    </form>
</div>