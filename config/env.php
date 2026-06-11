<?php
function get_env_vars(): array {
    static $vars = null;
    if ($vars !== null) return $vars;
    $vars = [];
    $file = __DIR__ . '/../.env';
    if (!file_exists($file)) return $vars;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (!$line || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $vars[trim($k)] = trim($v);
    }
    return $vars;
}
