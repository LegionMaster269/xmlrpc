<?php 
session_start();
require_once 'functions.php';

if (isset($_SESSION['user'])) {
	$user = $_SESSION['user'];
	$x = true;
} else {
	$x = false;
}



?>