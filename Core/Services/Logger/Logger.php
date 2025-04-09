<?php
namespace Core\Services\Logger;

use \Carbon\Carbon;
use Core\Services\Path\Path;

class Logger {

    protected string $logDirectory = '';

    public function __construct() {
        $this->logDirectory = Path::exceptionLog();

        if (!is_dir($this->logDirectory)) {
            mkdir($this->logDirectory, 0777, true);
        }
    }

    public function instanceWrite($data): void
    {
        $formattedTime = $this->getFormattedTime();
        $message = $this->formatMessage($data);
        $logMessage = "[$formattedTime] $message";

        $filename = $this->getLogFilename();
        $filepath = $this->logDirectory . '/' . $filename;

        file_put_contents($filepath, $logMessage . PHP_EOL, FILE_APPEND);
    }

    protected function getFormattedTime(): string
    {
        return Carbon::now()->format('d-M-Y H:i:s');
    }

    protected function formatMessage($data): string
    {
        if (is_string($data)) {
            return $data;
        } elseif (is_array($data) || is_object($data)) {
            return json_encode($data, JSON_UNESCAPED_UNICODE);
        } else {
            return 'Unsupported data type for logging.';
        }
    }

    protected function getLogFilename(): string
    {
        $now = Carbon::now();
        return sprintf('log_%s_%s_%s_t_%s.log',
            $now->year,
            $now->month,
            $now->day,
            $now->hour
        );
    }

    public static function write($data): void
    {
        $logger = new self();
        $logger->instanceWrite($data);
    }
}
