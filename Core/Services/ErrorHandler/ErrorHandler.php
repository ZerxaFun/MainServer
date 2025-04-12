<?php

namespace Core\Services\ErrorHandler;

use Core\Define;
use Core\Services\Path\Path;
use ErrorException;
use Exception;

class ErrorHandler
{
    public static array $levels = [
        E_ERROR => 'Fatal Error',
        E_PARSE => 'Parse Error',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_NOTICE => 'Notice',
        E_WARNING => 'Warning',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_USER_NOTICE => 'Notice',
        E_USER_WARNING => 'Warning',
        E_USER_ERROR => 'Error',
    ];

    public function __construct()
    {
        set_error_handler([__CLASS__, 'error']);
        register_shutdown_function([__CLASS__, 'fatal']);
        set_exception_handler([__CLASS__, 'exception']);
    }

    public static function initialize(): ErrorHandler
    {
        return new self();
    }

    private static function highlightCode(string $file, int $line): array
    {
        if (!is_readable($file)) {
            return [];
        }

        $handle = fopen($file, 'rb');
        $lines = [];
        $currentLine = 0;

        while (!feof($handle)) {
            $currentLine++;
            $temp = fgets($handle);

            if ($currentLine > $line + 15) {
                break;
            }

            if ($currentLine >= ($line - 15) && $currentLine <= ($line + 15)) {
                $lines[] = [
                    'number' => $currentLine,
                    'highlighted' => ($currentLine === $line),
                    'code' => self::highlightString($temp),
                ];
            }
        }

        fclose($handle);

        return $lines;
    }

    private static function highlightString(string $string): string
    {
        $highlighted = highlight_string("<?php " . $string, true);
        $highlighted = preg_replace('#&lt;\?php\s#', '', $highlighted, 1);

        $highlighted = str_replace(
            ['<span style="color: #0000BB">', '<span style="color: #007700">'],
            ['<span style="color: #4078f2">', '<span style="color: #50a14f">'],
            $highlighted
        );

        return $highlighted;
    }

    private static function formatBacktrace(array $backtrace): array
    {
        if (empty($backtrace)) {
            return $backtrace;
        }

        $trace = [];

        foreach ($backtrace as $entry) {
            $function = $entry['class'] ?? '';
            $function .= $entry['type'] ?? '';
            $function .= $entry['function'] . '()';

            $arguments = [];
            if (!empty($entry['args'])) {
                foreach ($entry['args'] as $arg) {
                    ob_start();
                    var_dump($arg);
                    $arguments[] = htmlspecialchars(ob_get_clean());
                }
            }

            $location = [];
            if (isset($entry['file'])) {
                $location['file'] = $entry['file'];
                $location['line'] = $entry['line'];
                $location['code'] = self::highlightCode($entry['file'], $entry['line']);
            }

            $trace[] = [
                'function' => $function,
                'arguments' => $arguments,
                'location' => $location,
            ];
        }

        return $trace;
    }

    /**
     * @throws ErrorException
     */
    public static function error(int $code, string $message, string $file, int $line): bool
    {
        if ((error_reporting() & $code) !== 0) {
            if (!$_ENV['developer'] && $code === E_USER_NOTICE) {
                $error = compact('code', 'message', 'file', 'line');
                $error['type'] = 'Notice';
                self::writeLogs("{$error['type']}: {$message} in {$file} at line {$line}");
            } else {
                throw new ErrorException($message, $code, 0, $file, $line);
            }
        }

        return true;
    }

    public static function fatal(): void
    {
        $e = error_get_last();
        if ($e && (error_reporting() & $e['type']) !== 0) {
            self::exception(new ErrorException($e['message'], $e['type'], 0, $e['file'], $e['line']));
        }
    }

    public static function writeLogs(string $message): bool
    {
        $logFile = Path::exceptionLog() . gmdate('Y_m_d') . '.log';
        return file_put_contents($logFile, '[' . gmdate('d-M-Y H:i:s') . "] {$message}" . PHP_EOL, FILE_APPEND);
    }

    public static function exception(object $exception): void
    {
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $error = [
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'type' => $exception instanceof ErrorException
                    ? 'ErrorException: ' . (self::$levels[$exception->getCode()] ?? 'Unknown Error')
                    : get_class($exception),
            ];

            self::writeLogs("{$error['type']}: {$error['message']} in {$error['file']} at line {$error['line']}");

            if ($_ENV['developer'] === 'true') {
                $error['backtrace'] = self::formatBacktrace($exception->getTrace());
                $error['highlighted'] = self::highlightCode($error['file'], $error['line']);
                @header('HTTP/1.1 500 Internal Server Error');

                include sprintf("%sErrorHandler.php", Path::base('Core'  . DIRECTORY_SEPARATOR . 'Routing' . DIRECTORY_SEPARATOR . 'Theme' . DIRECTORY_SEPARATOR));
            } else {

                include sprintf("%s500.php", Path::base('Core' . DIRECTORY_SEPARATOR . 'Routing' . DIRECTORY_SEPARATOR . 'Theme' . DIRECTORY_SEPARATOR));
            }

        } catch (Exception $e) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo $e->getMessage() . ' in ' . $e->getFile() . ' (line ' . $e->getLine() . ').';
        }

        exit(1);
    }
}