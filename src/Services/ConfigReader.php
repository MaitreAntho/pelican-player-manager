<?php

namespace MaitreAntho\PlayerManager\Services;

/** Reads one setting out of the config file formats game servers use. */
final class ConfigReader
{
    public static function read(string $kind, string $content, string $key): ?string
    {
        return match ($kind) {
            'prop', 'ini' => self::keyValue($content, $key),
            'cfg' => self::cfg($content, $key),
            'xml' => self::xml($content, $key),
            'json' => self::json($content, $key),
            default => null,
        };
    }

    /** key=value lines (.properties, .ini; sections ignored, last one wins). */
    private static function keyValue(string $content, string $key): ?string
    {
        $value = null;
        foreach (preg_split('/\r?\n/', $content) as $line) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*[=:]\s*(.*?)\s*$/i', $line, $m)) {
                $value = trim($m[1], "\"'");
            }
        }

        return $value;
    }

    /** Source / Quake cfg: rcon_password "secret", set rcon_password secret, seta ... */
    private static function cfg(string $content, string $key): ?string
    {
        $value = null;
        foreach (preg_split('/\r?\n/', $content) as $line) {
            $line = preg_replace('#\s*//.*$#', '', $line);
            if (preg_match('/^\s*(?:set[as]?\s+)?' . preg_quote($key, '/') . '\s+(?:"([^"]*)"|(\S+))/i', $line, $m)) {
                $value = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '');
            }
        }

        return $value;
    }

    /** 7 Days to Die style: <property name="KEY" value="..."/> */
    private static function xml(string $content, string $key): ?string
    {
        return preg_match('/<property\s+name="' . preg_quote($key, '/') . '"\s+value="([^"]*)"/i', $content, $m) ? html_entity_decode($m[1]) : null;
    }

    private static function json(string $content, string $path): ?string
    {
        $v = json_decode($content, true);
        foreach (explode('.', $path) as $part) {
            if (!is_array($v) || !array_key_exists($part, $v)) {
                return null;
            }
            $v = $v[$part];
        }

        return is_scalar($v) ? (string) $v : null;
    }
}
