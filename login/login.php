<?php 

include "../header.php";

require $_SERVER['DOCUMENT_ROOT'].'/../composer/vendor/autoload.php';

use Duo\DuoUniversal\Client;
use Duo\DuoUniversal\DuoException;

// Check if the user is already logged in, if yes then redirect him to servers
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: ../servers.php");
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

	if (isset($_POST["name"]) && isset($_POST["password"])) {

		$username = filter_var($_POST["name"], FILTER_SANITIZE_STRING);
		$password = filter_var($_POST["password"], FILTER_SANITIZE_STRING);

		$salt = $loginHanlder->getUserSalt($username);
			
		if (!empty($salt)) {
					
			if ($password === FALSE) { //Error al codificar la cadena
				include ('error.php');
				die();
			}

			if ($username == '' || $password == '') {
				$msg = "You must enter all fields";

			} else {

				$login_status = $loginHanlder->login($username, $password);
				
				if ($login_status) { 
					
					try {
						$duo_client->healthCheck();
					} catch (DuoException $e) {
						$errorMSG = $e->getMessage();
						include("$serverBase/extra/error.php");
						die();
					}

					// generate duo auth request
					$_SESSION['duo_state'] = $duo_client->generateState();
					$_SESSION['username']  = $username;
					$_SESSION['loggedin']  = false;

					$prompt_uri = $duo_client->createAuthUrl($username, $_SESSION['duo_state']);
					header("Location: $prompt_uri");			
			
					# header('Location: ../servers.php'); //Envia a la siguiente web
				}
				$msg = "Invalid username or password";
			}
		} else { 
			$msg = "Invalid username or password";
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
			$_SESSION['state']    = substr($saved_state, 0, 10); 

			header("Location: $serverBase/servers.php"); // logged in successfully, send to main page
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
		<form class="form-signin mx-auto" name="frmregister" action="<?= $_SERVER['PHP_SELF'] ?>" method="post" >
			<h1 class="h3 mb-3 font-weight-normal">Login</h1>
			<?php if (!empty($msg)) { ?>
				<div class='alert alert-danger alert-dismissible fade show' role='alert'>
					<?php echo $msg; ?>
					<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
				</div>
			<?php } ?>
			<label for="name" class="sr-only">Username</label>
			<input type="username" name="name" id="name" class="form-control" placeholder="Username" required autofocus>
			<label for="password" class="sr-only">Password</label>
			<input type="password" id="password" name="password" class="form-control" placeholder="Password" required>
			<div class="checkbox mb-3">
				<label>
				<input type="checkbox" value="remember-me"> Remember me
				</label>
			</div>
			<button class="btn btn-lg btn-primary btn-block" type="submit">Sign in</button>
		</form>
	</body>
</html>

