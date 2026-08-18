<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';
http_response_code(429);
require dirname(__DIR__) . '/views/errors/429.php';
