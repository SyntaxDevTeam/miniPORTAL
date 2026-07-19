<?php

namespace SyntaxDevTeam\Cms\Database;

require_once __DIR__ . '/../libs/Medoo.php';

use core\libs\Medoo\Medoo;
use PDO;
use Exception;

/**
 * [Description CrudApp]
 */
class CrudApp
{
    private static ?CrudApp $instance = null;
    /** @var array<string, CrudApp> */
    private static array $instances = [];

    private Medoo $database;

    private function __construct(array $config = [])
    {
        $databaseInfo = $this->buildDatabaseConfig($config);

        $this->database = new Medoo($databaseInfo);
        if ($this->database->pdo === null) {
            throw new Exception("Nie można połączyć się z bazą danych.");
        }
    }

    public static function getInstance(array $config = [])
    {
        $key = self::configKey($config);

        if (isset(self::$instances[$key])) {
            return self::$instances[$key];
        }

        $instance = new CrudApp($config);
        self::$instances[$key] = $instance;

        if ($key === 'default') {
            self::$instance = $instance;
        }

        return $instance;
    }

    public function create($table, $data)
    {
        $this->database->insert($table, $data);
        return $this->database->id();
    }
    public function read($table, $columns = '*', $where = null)
    {
        $result = $this->database->select($table, $columns, $where);
        return $result !== false ? $result : null;
    }

    public function update($table, $data, $where = null)
    {
        return $this->database->update($table, $data, $where);
    }
    public function delete($table, $where)
    {
        return $this->database->delete($table, $where);
    }
    public function count($table, $where = null)
    {
        $data = $this->database->count($table, $where);
        return $data;
    }

    /**
     * Umożliwia tworzenie niezależnych instancji na potrzeby osobnych baz.
     */
    public static function make(array $config = []): CrudApp
    {
        return new CrudApp($config);
    }

    public function connection(): Medoo
    {
        return $this->database;
    }

    /**
     * Proxy do metod Medoo – pozwala używać CrudApp jako fasady.
     */
    public function __call(string $name, array $arguments)
    {
        if (method_exists($this->database, $name)) {
            return $this->database->$name(...$arguments);
        }

        throw new \BadMethodCallException("Method {$name} does not exist on CrudApp or Medoo.");
    }

    public function db_info()
    {
        return $this->database->info();
    }
    public function id()
    {
        return $this->database->id();
    }
    public function backupDB()
    {
        throw new \RuntimeException(
            'Metoda backupDB() została wyłączona. Kopie bazy wykonuj przez kontrolowane narzędzie CLI albo panel z ACL.'
        );
    }

    private static function configKey(array $config): string
    {
        if ($config === []) {
            return 'default';
        }

        $normalized = self::normalizeConfig($config);
        return md5(json_encode($normalized));
    }

    private static function normalizeConfig(array $config): array
    {
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $config[$key] = self::normalizeConfig($value);
            }
        }

        ksort($config);
        return $config;
    }

    private function buildDatabaseConfig(array $config): array
    {
        $defaults = [
            // [required]
            'database_type' => defined('DBDRIVER') ? DBDRIVER : 'mysql',
            'server' => defined('DBHOST') ? DBHOST : '127.0.0.1',
            'database_name' => defined('DBNAME') ? DBNAME : '',
            'username' => defined('DBUSER') ? DBUSER : '',
            'password' => defined('DBPASS') ? DBPASS : '',

            // [optional]
            'charset' => defined('DBCHARSET') ? DBCHARSET : 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'port' => defined('DBPORT') ? DBPORT : 3306,

            // Enable logging.
            'logging' => true,

            // Error mode
            // PDO::ERRMODE_SILENT (default) | PDO::ERRMODE_WARNING | PDO::ERRMODE_EXCEPTION
            'error' => PDO::ERRMODE_EXCEPTION,

            'option' => [
                PDO::ATTR_CASE => PDO::CASE_NATURAL
            ],
            'command' => [
                'SET SQL_MODE=ANSI_QUOTES'
            ]
        ];

        return array_replace_recursive($defaults, $config);
    }
}
