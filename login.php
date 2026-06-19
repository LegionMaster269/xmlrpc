<?php 
require_once 'header.php';
$user = '';
$pass = '';

  
 

 if ($_SERVER['REQUEST_METHOD'] == "POST") {


	$user = $_POST['user'];
	$pass = $_POST['pass'];

	$sql = "SELECT * FROM tblusers WHERE user='$user' AND pass='$pass'";

$result = MysqlQuery($sql);

	if ($result->num_rows == 0) {
		$error = "Invalid Username and Password!";
	} else {
		$_SESSION['user'] = $user;
		$_SESSION['pass'] = $pass;
		header("Location: admin.php");
	}
	
	}
	 
?>



<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title></title>
</head>
<body>
	<?php $error; ?>
	<form action="login.php" method="POST">
		
<label>Username:</label>
<input type="text" name="user" value="<?php echo $user;?>">
<br>
<label>Password:</label>
<input type="password" name="pass" value="<?php echo $pass; ?>" >
<br>
<input type="submit" name="login" value="login">
	</form>
</body>
</html>