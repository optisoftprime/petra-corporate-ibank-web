<?php

/**
 * Minimal environment/config loader. Deliberately hand-rolls what a package
 * like vlucas/phpdotenv would do, so that reading config introduces no
 * Composer dependency.
 *
 * Resolution order for Env::get($key, $default):
 *   1. A real process environment variable (getenv($key)) — this is what
 *      Docker/docker-compose/any real deployment sets via `environment:` or
 *      `env_file`, and always wins when present.
 *   2. A KEY=VALUE line from a `.env` file at the repo root, if one exists.
 *      This only matters for running the PHP sources directly on a
 *      developer machine without containers; inside a container getenv()
 *      already has everything, so this branch is simply never reached.
 *   3. $default.
 *
 * Shared by both apps via the same class-autoload path CoreFront's other
 * classes already use (Admin has no Env class of its own).
 */
class Env {
    private static $dotenv = null;

    private static function loadDotenv() {
        if (self::$dotenv !== null) {
            return;
        }
        self::$dotenv = [];
        // Repo root, two levels above CoreFront/classes/. Only meaningful for
        // local (non-container) development — see the class docblock.
        $path = dirname(__DIR__, 2) . '/.env';
        if (!is_file($path) || !is_readable($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            $value = trim(substr($line, $eq + 1));
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }
            self::$dotenv[$key] = $value;
        }
    }

    /**
     * Plain string config value. An explicitly empty value (KEY= with
     * nothing after it) is a real value, not "unset": SMTP_SECURE must be
     * settable to '' for a plaintext test SMTP server. Only a key that is
     * genuinely absent falls back to $default.
     */
    public static function get($key, $default = null) {
        $fromEnv = getenv($key);
        if ($fromEnv !== false) {
            return $fromEnv;
        }
        self::loadDotenv();
        if (array_key_exists($key, self::$dotenv)) {
            return self::$dotenv[$key];
        }
        return $default;
    }

}
