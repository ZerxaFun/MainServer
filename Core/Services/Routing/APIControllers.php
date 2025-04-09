<?php

namespace Core\Services\Routing;

use Core\Services\Http\Header;
use JetBrains\PhpStorm\NoReturn;
use JsonException;
use SimpleXMLElement;

abstract class APIControllers
{
    #[NoReturn]
    public static function setData(array $result = [], ?int $statusCode = 200, ?string $status = null, string $type = 'json'): void
    {
        $response = [
            'result' => $result,
            'code'   => $statusCode ?? 200,
            'status' => $status ?? 'success',
            'core'   => [
                'generation' => sprintf('%.4f sec.', microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]),
                'memory'     => self::formatMemory(memory_get_usage()),
            ]
        ];

        Header::code($response['code']);
        (new Header())->header($type);

        try {
            echo match ($type) {
                'xml'   => self::toXml($response),
                'text'  => self::toText($response),
                'html'  => self::toHtml($response),
                default => json_encode($response, JSON_THROW_ON_ERROR),
            };
        } catch (JsonException $e) {
            self::handleJsonException($e);
        }

        die();
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

    #[NoReturn]
    private static function handleJsonException(JsonException $e): void
    {
        Header::code(500);
        echo json_encode([
            'error'   => 'Internal Server Error',
            'message' => $e->getMessage()
        ], JSON_THROW_ON_ERROR);
        die();
    }
}
