<?php 

include "../integrations/dbConfig.php";
include "../header.php";

session_start();
 
// Check if the user is already logged in, if yes then redirect him to servers
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: ../servers.php");
    exit;
}

$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST["login"])) {

	if (!isset($_POST["name"], $_POST["password"])) { 

		echo "Malformed query! Missing username & password";
		header("location: ./login.php");
		exit;
	}
	
	// sanitize input from forms...
	$username = filter_var($_POST['username'] , FILTER_SANITIZE_FULL_SPECIAL_CHARS);
	$password = filter_var($_POST['password'] , FILTER_SANITIZE_FULL_SPECIAL_CHARS);
	$query    = $loginHanlder->getUserSalt($username);

	if ($query->num_rows == 0) {   // user does not exist
		$msg = "Username or password do not match";

	} else {
		$salt = $query->fetch_row()[0];
		$salt = $db->real_escape_string($salt); // Remove string escape

		$query    = $loginHanlder->getEncodedMsg($password, $salt);
		$password = $query->fetch_row()[0];

		if ($username == '' || $password == '') {
			$msg = "You must enter all fields";
		} else {

			$query = $loginHanlder->getUserID($password, $username);

			if ($query->num_rows > 0) {
				session_start();  

                // Store data in session variables
                $_SESSION["loggedin"] = true;
                //$_SESSION["id"] = $id;
				$_SESSION["username"] = $username;
				$_SESSION["slots"] = 3;
				$_SESSION["ons"] = 0;
				unset($query);

				header('Location: ../servers.php');
				exit;
			}
			$msg = "Username and password do not match";
		}
	}
	unset($query);
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
	<link href="style.css" rel="stylesheet" type="text/css">
</head>
<body>
	<h1>Login</h1>
	<form name="frmregister"action="<?= $_SERVER['PHP_SELF'] ?>" method="post" >
		<table class="form" border="0">
			<tr>
			<td></td>
				<td style="color:red;">
				<?php echo $msg; ?></td>
			</tr> 
			<tr>
				<th><label for="name"><strong>Username:</strong></label></th>
				<td><input class="inp-text" name="name" id="name" type="text" size="30" /></td>
			</tr>
			<tr>
				<th><label for="name"><strong>Password:</strong></label></th>
				<td><input class="inp-text" name="password" id="password" type="password" size="30" /></td>
			</tr>
			<tr>
			<td></td>
				<td class="submit-button-right">
				<input class="send_btn" type="submit" value="Submit" alt="Submit" title="Submit" />
				
				<input class="send_btn" type="reset" value="Reset" alt="Reset" title="Reset" /></td>
			</tr>
		</table>
	</form>
</body>
</html>
