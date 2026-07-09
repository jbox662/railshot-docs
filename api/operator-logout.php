<?php
require_once __DIR__ . '/bootstrap.php';

railshot_operator_logout();
railshot_json_response(['ok' => true]);
