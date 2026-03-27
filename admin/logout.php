<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';
Csrf::verify();
Auth::logout();
header('Location: login.php');
exit;
