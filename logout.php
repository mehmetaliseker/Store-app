<?php
session_start();

// Destroy all session data
session_destroy();

// Redirect to login page (index.php)
header('Location: index.php');
exit();
?> 