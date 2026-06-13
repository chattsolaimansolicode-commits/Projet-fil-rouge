<?php
require_once __DIR__ . '/config/db.php';
$_SESSION = [];
session_destroy();
redirect(APP_URL . '/login.php');
