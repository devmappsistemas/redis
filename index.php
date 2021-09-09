<?php

require_once __DIR__ . "/vendor/autoload.php";

use App\Redis;

Redis::accessControl(2, 1);
