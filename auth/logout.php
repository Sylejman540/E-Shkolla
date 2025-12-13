<?php

session_start();
session_destroy();

header("Location: /E-Shkolla/login");
exit;