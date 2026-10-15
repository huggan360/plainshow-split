<?php
// One database connection. Credentials live in config/database.php, outside git.
function splitDb(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        $config = splitConfig('database');
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn = new mysqli($config['host'], $config['user'], $config['password'], $config['database']);
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

function splitConfig(string $name): array
{
    static $cache = [];
    return $cache[$name] ??= require dirname(__DIR__) . "/config/$name.php";
}
