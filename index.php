<?php
require_once __DIR__ . '/config/db.php';
if (isLoggedIn()) {
    redirect(APP_URL . '/' . $_SESSION['role'] . '/dashboard.php');
} else {
    redirect(APP_URL . '/login.php');
}
