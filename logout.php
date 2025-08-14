<?php
require_once 'config/config.php';

if (isset($_SESSION['access_token'])) {
    logoutUser($_SESSION['access_token']);
}

session_unset();
session_destroy();
redirect('login.php');
?>