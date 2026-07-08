<?php
require_once dirname(__DIR__) . '/api/bootstrap.php';
railshot_logout();
header('Location: /admin/login.php');
exit;
