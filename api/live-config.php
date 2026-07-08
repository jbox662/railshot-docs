<?php
require_once __DIR__ . '/bootstrap.php';

header('Access-Control-Allow-Origin: *');
railshot_json_response(railshot_public_live_config());
