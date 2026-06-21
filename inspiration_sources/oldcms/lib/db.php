<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/CrudApp.class.php';
require_once __DIR__ . '/../app/PanelAccess.class.php';

function db(): \core\app\CrudApp
{
    static $app = null;

    if ($app instanceof \core\app\CrudApp) {
        return $app;
    }

    $app = \core\app\CrudApp::getInstance([
        'database_type' => defined('DB_DRIVER') ? DB_DRIVER : (defined('DBDRIVER') ? DBDRIVER : 'mysql'),
        'server' => defined('DB_HOST') ? DB_HOST : (defined('DBHOST') ? DBHOST : '127.0.0.1'),
        'database_name' => defined('DB_NAME') ? DB_NAME : (defined('DBNAME') ? DBNAME : ''),
        'username' => defined('DB_USER') ? DB_USER : (defined('DBUSER') ? DBUSER : ''),
        'password' => defined('DB_PASS') ? DB_PASS : (defined('DBPASS') ? DBPASS : ''),
        'charset' => defined('DB_CHARSET') ? DB_CHARSET : (defined('DBCHARSET') ? DBCHARSET : 'utf8mb4'),
        'collation' => defined('DB_COLLATION') ? DB_COLLATION : 'utf8mb4_unicode_ci',
        'port' => defined('DB_PORT') ? DB_PORT : (defined('DBPORT') ? DBPORT : 3306),
        'error' => \PDO::ERRMODE_EXCEPTION,
        'option' => [
            \PDO::ATTR_CASE => \PDO::CASE_NATURAL,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ]);
    return $app;
}

function panel_access(): \core\app\PanelAccess
{
    static $panelAccess = null;

    if ($panelAccess instanceof \core\app\PanelAccess) {
        return $panelAccess;
    }

    $panelAccess = new \core\app\PanelAccess(
        db(),
        defined('DISCORD_ALLOWED_USER_IDS') ? DISCORD_ALLOWED_USER_IDS : []
    );

    return $panelAccess;
}
