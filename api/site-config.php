<?php
require_once __DIR__ . '/bootstrap.php';

header('Access-Control-Allow-Origin: *');
$config = railshot_load_config();
railshot_json_response($config['site'] ?? []);
