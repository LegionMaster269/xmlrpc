<?php
require_once 'header.php';

if (!$x) {
    header("Location: login.php");
    exit();
}


$result2  = MysqlQuery("SELECT * from tblusers");



while ($row= $result2->fetch_assoc()) {
    echo $row['user'];
}


?>

<h1>Welcome <?php echo htmlspecialchars($_SESSION['user']); ?></h1>



<a href="logout.php">logout</a>