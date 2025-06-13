<?php

namespace Core\Routing;

use Core\Services\Http\Header;
use Core\Services\Http\Method;
use Core\Services\Http\Uri;
use Illuminate\Database\Capsule\Manager;
use JetBrains\PhpStorm\NoReturn;
use JsonException;
use SimpleXMLElement;
use Throwable;

/**
 * APIControllers — Базовый абстрактный контроллер для возврата структурированных API-ответов.
 */
abstract class APIControllers
{
    private static array $buffer = [];

    public static function addResult(string $key, mixed $value): void
    {
        self::$buffer[$key] = $value;
    }

    public static function setRawResult(array $data): void
    {
        self::$buffer = $data;
    }

    public static function clear(): void
    {
        self::$buffer = [];
    }

    /**
     * @throws JsonException
     */
    #[NoReturn]
    public static function set(?int $code = 200, ?string $status = null, string $type = 'json'): void
    {
        $response = self::buildResponse(self::$buffer, $code, $status);
        Header::code($response['code']);
        Header::header($type);
        echo self::formatOutput($response, $type);
        exit();
    }

    /**
     * @throws JsonException
     */
    #[NoReturn] public static function setData(mixed $data = [], ?int $code = 200, string $type = 'json', ?string $status = null): void
    {
        $response = self::buildResponse($data, $code, $status);
        Header::code($response['code']);
        Header::header($type);
        echo self::formatOutput($response, $type);
        exit();
    }

    private static function buildResponse(mixed $data, ?int $code = 200, ?string $status = null): array
    {
        $response = [
            'result' => $data,
            'code'   => $code,
            'status' => $status ?? self::resolveStatus($code),
            'core'   => [
                'generation' => sprintf('%.4f sec.', microtime(true) - ($_SERVER["REQUEST_TIME_FLOAT"] ?? microtime(true))),
                'memory'     => self::formatMemory(memory_get_usage()),
            ]
        ];

        if ($_ENV['developer'] === "true") {
            $traceData = $data instanceof Throwable
                ? $data->getTrace()
                : debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);

            $response['debug'] = [
                'caller'   => self::getCallerFunction(),
                'ip'       => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'token'    => self::getBearerToken(),
                'sql'      => self::getAllSQLFormatted(),
                'request'  => [
                    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                    'uri'    => $_SERVER['REQUEST_URI'] ?? '',
                ],
                'headers'  => getallheaders(),
                'session'  => $_SESSION ?? [],
                'trace'    => array_map(fn($frame) => [
                    'file'     => $frame['file'] ?? '[internal]',
                    'line'     => $frame['line'] ?? 0,
                    'class'    => $frame['class'] ?? null,
                    'type'     => $frame['type'] ?? null,
                    'function' => $frame['function'] ?? null,
                    'args'     => [],
                ], array_slice($traceData, 0, 15)),
            ];

            if ($data instanceof Throwable) {
                $response['debug']['exception'] = [
                    'message' => $data->getMessage(),
                    'class'   => get_class($data),
                    'file'    => $data->getFile(),
                    'line'    => $data->getLine(),
                ];
            }
        }

        return $response;
    }

    private static function resolveStatus(int $code): string
    {
        return match (true) {
            $code >= 400 => 'fail',
            $code >= 200 => 'success',
            default      => 'error'
        };
    }

    /**
     * @throws JsonException
     */
    private static function formatOutput(array $response, string $type): string
    {
        return match ($type) {
            'xml'  => self::toXml($response),
            'text' => self::toText($response),
            'html' => self::toHtml($response),
            default => json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        };
    }

    private static function formatMemory(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private static function toXml(array $data, ?SimpleXMLElement $xml = null): string
    {
        $xml = $xml ?? new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><response></response>');
        foreach ($data as $key => $value) {
            $key = is_numeric($key) ? "item$key" : $key;
            if (is_array($value)) {
                self::toXml($value, $xml->addChild($key));
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }
        return $xml->asXML();
    }

    private static function toText(array $data): string
    {
        return $data['result']['message'] ?? '';
    }

    private static function toHtml(array $data): string
    {
        return $data['result']['message'] ?? '';
    }

    public static function getBuffer(): array
    {
        return self::$buffer;
    }

    private static function getCallerFunction(): array
    {
        return Repository::retrieve(Method::current(), Uri::segmentString());
    }


    private static function getBearerToken(): ?string
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        if (!$authHeader) return null;
        if (preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private static function getAllSQLFormatted(): array
    {
        try {
            $logs = Manager::connection()->getQueryLog();
            $queries = [];

            foreach ($logs as $entry) {
                $query = $entry['query'];
                $bindings = $entry['bindings'] ?? [];

                foreach ($bindings as $binding) {
                    if (is_null($binding)) {
                        $binding = 'NULL';
                    } else {
                        $binding = "'" . addslashes((string)$binding) . "'";
                    }

                    $query = preg_replace('/\?/', $binding, $query, 1);
                }

                $queries[] = [
                    'query' => $query,
                    'time'  => $entry['time'] ?? null,
                ];
            }

            return $queries;
        } catch (\Throwable) {
            return [];
        }
    }

}
