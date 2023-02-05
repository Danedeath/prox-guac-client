<?php 

include "../header.php";

require $_SERVER['DOCUMENT_ROOT'].'/../composer/vendor/autoload.php';

use Duo\DuoUniversal\Client;
use Duo\DuoUniversal\DuoException;

// Check if the user is already logged in, if yes then redirect him to servers
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    if(isset($_SESSION["admin_loggedin"]) && $_SESSION["admin_loggedin"] === true) {
		if (!isset($_SESSION['redirect_url'])) {
			header("location: $serverBase/index.php");
		} else {
			header("location: $serverBase/{$_SESSION['redirect_url']}");
		}
	
		exit;
	}
    exit;
}

$duo_client = new Client(
	$INFO['duo_ikey'],
	$INFO['duo_skey'],
	$INFO['duo_api'],
	"{$serverBase}/{$INFO['duo_redir']}"
);

$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if (isset($_POST["username"]) && isset($_POST["password"])) {

		$username = filter_var($_POST["username"], FILTER_SANITIZE_STRING);
		$password = $_POST["password"];
	
		// check if the user exists first, otherwise we cannot continue the login process!
		$user = $dbLogin->getUser($username);

		if (empty($user)) {
			$msg = "Invalid username or password";
			$_SESSION = array();
			session_destroy();
			session_start();

		}  else { 

			if ($password === False) { // if the password is empty, we cannot continue the login process!
				include ('error.php');
				die();
			} else { 

				// compare the password provided!
				$login_status = $dbLogin->login($username, $password);

				if (!is_array($login_status) && $login_status) { 

					try {
						$duo_client->healthCheck();
					} catch (DuoException $e) {
						$errorMSG = $e->getMessage();
						include("$serverBase/extra/error.php");
						die();
					}

					// generate duo auth request
					$_SESSION['duo_state']      = $duo_client->generateState();
					$_SESSION['username']       = $user['username'];
					$_SESSION['loggedin']       = false;
                    $_SESSION['admin_loggedin'] = false;
					$_SESSION['redirect_url']   = isset($_SESSION['redirect_url']) ? filter_var($_POST['redirect_url'], FILTER_SANITIZE_STRING) : 'index';
					$_SESSION['ip_addr']        = $_SERVER['REMOTE_ADDR'];

					$prompt_uri = $duo_client->createAuthUrl($user['username'], $_SESSION['duo_state']);
					header("Location: $prompt_uri");			

				} else if (isset($login_status['status'])) { 
					$msg = $login_status['message'];
				} else { 
					$msg = "Something went wrong!";
				}
			} 
		} 	
	}
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {

	if (isset($_GET['error'])) { 
		$errorMSG = "{$_GET['error']}: {$_GET['error_description']}";
		include ($_SERVER['DOCUMENT_ROOT']."/extra/error.php");
		die();
	}

	if (isset($_GET['duo_code']) && isset($_GET['state'])) {

		$duo_code  		= $_GET['duo_code'];
		$duo_state 		= $_GET['state'];
		$username  		= $_SESSION['username'];
		$saved_state 	= $_SESSION['duo_state'];
		unset($_SESSION);
		
		if (empty($saved_state) || empty($username)) { 
			$msg = "No saved state, please login again";
			$_SESSION = array();
			session_destroy();
			session_start();
		}

		else if ($saved_state != $duo_state) {
			$errorMSG = "Invalid state parameter";
			$msg = "Invalid response from DUO, hacker!";
			$_SESSION = array();
			session_destroy();
			session_start();

		} else if ($saved_state == $duo_state) {

			try {
				$decoded_token = $duo_client->exchangeAuthorizationCodeFor2FAResult($duo_code, $username);
			} catch (DuoException $e) {
				$errorMSG = "Error decoding Duo result. Confirm device clock is correct.";
				$returnPage = $serverBase."/login_form/login.php";
				include ($_SERVER['DOCUMENT_ROOT']."/extra/error.php");
				die();
			}

			$_SESSION = array();
			session_destroy();
			session_start();
			$_SESSION['username'] = $username;
			$_SESSION['loggedin'] = true;
			$_SESSION['state']    		= bin2hex(random_bytes(32));
			$_SESSION['redirect_url'] 	= (isset($_SESSION['redirect_url'])) ? $_SESSION['redirect_url'] : 'servers';
			$_SESSION['ip_addr']        = $_SERVER['REMOTE_ADDR'];

			header("Location: $serverBase/".$_SESSION['redirect_url'].'.php'); // logged in successfully, send to main page
			exit();

		} else { 
			$errorMSG = "Something mysterious happened!";
			$returnPage = $serverBase."/login_form/login.php";
			include ($_SERVER['DOCUMENT_ROOT']."/extra/error.php");
			die();
		}
	}
}

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html" />
		<title>Login</title>
		<meta name="description" content="Login page"/>
		<meta name="keywords" content="login"/>
		<meta charset="UTF-8">
		<link href="<?php echo $serverBase; ?>/extra/style.css" rel="stylesheet" type="text/css">
		<link href="<?php echo $serverBase; ?>/extra/bootstrap.css" rel="stylesheet" type="text/css">
		<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<style> 
			body {
				display: -ms-flexbox;
				display: -webkit-box;
				display: flex;
				-ms-flex-align: center;
				-ms-flex-pack: center;
				-webkit-box-align: center;
				align-items: center;
				-webkit-box-pack: center;
				justify-content: center;
				padding-top: 40px;
				padding-bottom: 40px;
				background-color: #2d3035 !important;
			}
		</style>
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-iYQeCzEYFbKjA/T2uDLTpkwGzCiq6soy8tYaI1GyVh/UjpbCx/TYkiZhlZB6+fzT" crossorigin="anonymous">
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-u1OknCvxWvY5kfmNBILK2hRnQC3Pr17a+RTT6rIHI7NnikvbZlHgTPOOmMi466C8" crossorigin="anonymous"></script>
	</head>
	<body class="text-center bg-dark">
		<div style="max-width: 300px;width: 25%;">
			<form class="form-signin mx-auto" name="frmlogin" id="frmlogin" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" >
				<input type="hidden" name="redirect_url" value="<?php echo isset($_GET['next']) ? $_GET['next'] : $serverBase.'/index.php' ; ?>">
				<h1 class="h3 mb-3 font-weight-normal">Login</h1>
				<?php if (!empty($msg)) { ?>
					<div class='alert alert-danger alert-dismissible fade show' role='alert'>
						<?php echo $msg; ?>
						<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
					</div>
				<?php } ?>
				<label for="name" class="sr-only">Username</label>
				<input type="username" name="username" id="username" class="form-control" placeholder="Username" required autofocus>
				<label for="password" class="sr-only">Password</label>
				<input type="password" id="password" name="password" class="form-control" placeholder="Password" required>
				<div class="checkbox mb-3">
					<label>
					<input type="checkbox" value="remember-me"> Remember me
					</label>
				</div>
			</form>
			<div class="text-center bg-dark">
				<input class="btn btn-lg btn-primary btn-block" type="submit" form="frmlogin" value="Sign in">
			</div>
		</div>
	</body>
</html>

