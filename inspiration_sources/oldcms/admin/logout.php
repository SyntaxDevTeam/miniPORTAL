<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';

start_session();
session_destroy();

redirect('/admin/login.php');

