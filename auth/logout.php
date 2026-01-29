<?php
session_start();
session_destroy();
session_start();
$_SESSION['logout_success'] = true;

header("Location: /E-Shkolla/login");
exit;
