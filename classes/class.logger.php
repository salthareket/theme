<?php

class Logger {
    private $logFile;

    public function __construct() {
        // Log dosyasının yolu - wp-content klasöründe log-process adlı bir dosya
        $this->logFile = WP_CONTENT_DIR . '/log-process.log';
        
        // Log dosyası yoksa oluştur
        if (!file_exists($this->logFile)) {
            $this->createLogFile();
        }
    }

    private function createLogFile() {
        // Log dosyasını oluşturur
        $logMessage = "Log Dosyası Oluşturuldu - " . date('Y-m-d H:i:s') . "\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    private function writeLog($message) {
        // Mesajı log dosyasına yazma
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    public function logAction($functionName, $description) {
        if(ENABLE_LOGS){
            $message = "{$functionName} - {$description}";
            $this->writeLog($message);
        }
    }
}
