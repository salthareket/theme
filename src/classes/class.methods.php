<?php

namespace SaltHareket;
use MatthiasMullie\Minify;

/**
 * MethodClass — Frontend/Admin method dosyalarını otomatik birleştirir.
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-04-03
 *     - Add: Initial versioned release
 *
 * How to use:
 *   $mc = new MethodClass();
 *   $mc->createFiles(false, 'frontend');  // Frontend method dosyalarını birleştir
 *   $mc->createFiles(false, 'admin');     // Admin method dosyalarını birleştir
 */
class MethodClass {
    public function createFiles($includeParentFolderName = false, $platform = 'frontend') {
        
        if (!in_array($platform, ["frontend", "admin"])) {
            return;
        }

        $methodsDirs = [];

        if ($platform == "frontend") {
            $storeDir = SH_INCLUDES_PATH . 'methods/';
            $methodsDirs[] = SH_INCLUDES_PATH . 'methods/';
        } elseif ($platform == "admin") {
            $storeDir = SH_INCLUDES_PATH . 'admin/';
            $methodsDirs[] = SH_INCLUDES_PATH . 'admin/';
        }

        // Ek klasörleri buraya ekleyebilirsiniz.
        $extraDirs = $this->getExtraDirs($platform);
        $methodsDirs = array_merge($methodsDirs, $extraDirs);

        $indexPhpContent = $this->getIndexPhpHeader($platform);
        $indexJsContent  = $this->getIndexJsHeader($platform);

        // Methods klasörlerini işle
        foreach ($methodsDirs as $methodsDir) {
            // Methods klasöründe gezinme
            if (is_dir($methodsDir)) {
                $methodDirs = scandir($methodsDir);
                foreach ($methodDirs as $methodDir) {
                    if ($methodDir !== '.' && $methodDir !== '..' && $methodDir !== '_deprecated' && is_dir($methodsDir . $methodDir)) {
                        // Alt klasörleri işleme
                        if ($methodDir == "woo" && (!defined('ENABLE_ECOMMERCE') || !ENABLE_ECOMMERCE)) {
                            continue;
                        }
                        $this->processSubfolder($methodsDir . $methodDir, $methodDir, $indexPhpContent, $indexJsContent, $includeParentFolderName, $platform);
                    }
                }
            }
        }
        
        $indexPhpContent .= $this->getIndexPhpFooter($platform);
        $indexJsContent  .= $this->getIndexJsFooter($platform);

        // index.php dosyasını geçici olarak oluşturma
        $tempPhpFilePath = $storeDir . 'index_temp.php';
        file_put_contents($tempPhpFilePath, $this->optimizeCode($indexPhpContent), LOCK_EX);

        // Kod hatalarını kontrol et
        $errors = [];
        if(isLocalhost()){
            $errors = $this->checkForSyntaxErrors($tempPhpFilePath);
        }

        if (!$errors) {
            unlink($tempPhpFilePath);
            file_put_contents($storeDir . 'index.php', $this->optimizeCode($indexPhpContent), LOCK_EX);
            $this->copyToTheme($storeDir . 'index.php', THEME_INCLUDES_PATH . ($platform=="frontend"?"methods":$platform) ."/index.php");
        } else {
            if(file_exists($tempPhpFilePath)){
                unlink($tempPhpFilePath);
            }
        }

        // index.js dosyasını oluşturma
        file_put_contents($storeDir . 'index.js', $this->optimizeCode($indexJsContent), LOCK_EX);
        $this->copyToTheme($storeDir . 'index.js', STATIC_PATH . 'js/'.($platform=="frontend"?"methods":$platform).'.min.js');
        
        return $errors;
    }

    //private function copyToTheme($path, $platform) {
    private function copyToTheme($source_file, $target_file) {

        // Kaynak dosya kontrolü
        if (!file_exists($source_file)) {
            //error_log("Kaynak dosya bulunamadı: $source_file");
            return;
        }

        // Dosya kopyalama (mevcutsa üzerine yazılır)
        if (!copy($source_file, $target_file)) {
            //error_log("Dosya kopyalama başarısız: $source_file -> $target_file");
        } else {
            //error_log("Dosya başarıyla kopyalandı (üzerine yazıldı): $target_file");
        }
    }

    private function requirement($phpContent = "") {
        $settingLine = '$required_setting';

        if (strpos($phpContent, $settingLine) === false) {
            return $phpContent;
        }

        // Extract the line: $required_setting = EXPRESSION;
        $settingValueStart = strpos($phpContent, $settingLine);
        $settingValueEnd = strpos($phpContent, ";", $settingValueStart);

        if ($settingValueStart === false || $settingValueEnd === false) {
            return $phpContent;
        }

        // Get the full line and extract expression after "="
        $fullLine = substr($phpContent, $settingValueStart, $settingValueEnd - $settingValueStart + 1);
        $equalsPos = strpos($fullLine, "=");
        if ($equalsPos === false) return $phpContent;

        $expression = trim(substr($fullLine, $equalsPos + 1, -1)); // remove "=" and ";"

        // Remove the $required_setting line from content
        $phpContent = str_replace($fullLine, "", $phpContent);

        // Evaluate the expression safely
        // Supports: single constant, CONST && CONST, CONST || CONST, !CONST
        $result = $this->evaluate_requirement($expression);

        return $result ? $phpContent : "";
    }

    /**
     * Safely evaluate a requirement expression made of constants and logical operators
     * Supports: CONST, CONST && CONST, CONST || CONST, !CONST
     */
    private function evaluate_requirement($expression) {
        $expression = trim($expression);

        // Handle || (OR) — split and check if any part is true
        if (strpos($expression, '||') !== false) {
            $parts = array_map('trim', explode('||', $expression));
            foreach ($parts as $part) {
                if ($this->evaluate_requirement($part)) return true;
            }
            return false;
        }

        // Handle && (AND) — split and check if all parts are true
        if (strpos($expression, '&&') !== false) {
            $parts = array_map('trim', explode('&&', $expression));
            foreach ($parts as $part) {
                if (!$this->evaluate_requirement($part)) return false;
            }
            return true;
        }

        // Handle negation: !CONST
        $negate = false;
        if (strpos($expression, '!') === 0) {
            $negate = true;
            $expression = trim(substr($expression, 1));
        }

        // Single constant check
        $value = defined($expression) ? (bool) constant($expression) : false;
        return $negate ? !$value : $value;
    }

    private function processSubfolder($subfolderPath, $subfolderName, &$indexPhpContent, &$indexJsContent, $includeParentFolderName, $platform) {
        $phpFilePath = $subfolderPath . '/index.php';
        if (file_exists($phpFilePath)) {
            // index.php dosyasını oluşturma
            $phpContent = file_get_contents($phpFilePath);
            $phpContent = $this->requirement($phpContent);
            if (!empty($phpContent)) {
                if ($platform == "frontend") {
                    $indexPhpContent .= "    case '{$subfolderName}':\n";
                }

                $indexPhpContent .= $this->removeEchoTags($phpContent);
                if ($platform == "frontend") {
                    $indexPhpContent .= "\nbreak;\n";
                }
            }
        }

        $jsFilePath = $subfolderPath . '/index.js';
        if (file_exists($jsFilePath)) {
            // index.js dosyasını okuma ve içeriği ekleme
            $jsContent = file_get_contents($jsFilePath);
            $jsContent = $this->requirement($jsContent);
            if (!empty($jsContent)) {
                $compressedJsContent = $this->compressJsCode($jsContent);
                if ($platform == "frontend") {
                    $indexJsContent .= "window.ajax_hooks['{$subfolderName}'] = {$compressedJsContent};\n";
                }
                if ($platform == "admin") {
                    $indexJsContent .= "{$compressedJsContent}\n";
                }
            }
        }

        // Alt klasörlerde gezinme
        $subDirs = scandir($subfolderPath);
        foreach ($subDirs as $subDir) {
            if ($subDir !== '.' && $subDir !== '..' && $subDir !== '_deprecated' && is_dir($subfolderPath . '/' . $subDir)) {
                // WooCommerce check (recursive'de de)
                if ($subDir === "woo" && (!defined('ENABLE_ECOMMERCE') || !ENABLE_ECOMMERCE)) {
                    continue;
                }
                $pathName = ($includeParentFolderName ? $subfolderName . '/' : '') . $subDir;
                $this->processSubfolder($subfolderPath . '/' . $subDir, $pathName, $indexPhpContent, $indexJsContent, $includeParentFolderName, $platform);
            }
        }
    }

    /*private function removeEchoTags($content) {
        $content = preg_replace('/<\?(php)?|\?>/i', '', $content);
        return $content;
    }*/
    private function removeEchoTags($content) {
        $trimmedContent = trim($content);
        if (strpos($trimmedContent, '<?php') === 0) {
            $trimmedContent = substr($trimmedContent, 5);
        }
        if (substr($trimmedContent, -2) === '?>') {
            $trimmedContent = substr($trimmedContent, 0, -2);
        }
        return $trimmedContent;
    }


    private function optimizeCode($code) {
        $lines = explode("\n", $code);
        $optimizedCode = '';

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if (!empty($trimmedLine)) {
                $optimizedCode .= $trimmedLine . "\n";
            }
        }

        return $optimizedCode;
    }

    private function compressJsCode($code) {
        $minifier = new Minify\JS();
        $minifier->add($code);
        $compressedCode = $minifier->minify();

        return $compressedCode;
    }

    private function getExtraDirs($platform) {
        $extraDirs = [];
        if ($platform == "frontend") {
            $extraDirs[] = THEME_INCLUDES_PATH . 'methods/';
        } elseif ($platform == "admin") {
            $extraDirs[] = THEME_INCLUDES_PATH . 'admin/';
        }
        return $extraDirs;
    }

    private function getIndexPhpHeader($platform) {
        if ($platform == "frontend") {
            return "<?php\n\nswitch (\$method) {\n";
        } elseif ($platform == "admin") {
            return "<?php\n\n";
        }
    }

    private function getIndexPhpFooter($platform) {
        if ($platform == "frontend") {
            return "}\n";
        } elseif ($platform == "admin") {
            return "";
        }
    }

    private function getIndexJsHeader($platform) {
        if ($platform == "frontend") {
            return "window.ajax_hooks = {};\n";
        } elseif ($platform == "admin") {
            return "$ = jQuery.noConflict();\njQuery(document).ready(function($){\n";
        }
    }

    private function getIndexJsFooter($platform) {
        if ($platform == "frontend") {
            return "\n";
        } elseif ($platform == "admin") {
            return "});\n";
        }
    }

    public function checkForSyntaxErrors($file) {

        $vendorPath = get_stylesheet_directory() . '/vendor/bin'; // Projenin vendor/bin dizini
        if (is_dir($vendorPath)) {
            putenv('PATH=' . getenv('PATH') . PATH_SEPARATOR . $vendorPath);
        }

        $fileList = escapeshellarg($file);
        $parallelLintPath = get_stylesheet_directory() . '/vendor/bin/parallel-lint';

        if (!file_exists($parallelLintPath)) {
            //error_log("Hata: parallel-lint dosyası bulunamadı: " . $parallelLintPath);
            return ["Hata: parallel-lint komutu bulunamadı."];
        }

        // Komut çalıştırma
        $command = escapeshellcmd("php {$parallelLintPath} --json {$fileList}");
        //error_log("Komut: " . $command); // Komutu logla

        $output = [];
        $returnCode = 0;

        exec($command, $output, $returnCode);

        // Log sonuçlarını
        //error_log("Çıktı: " . print_r($output, true));
        //error_log("Return Code: " . $returnCode);

        // Hata varsa döndür
        if ($returnCode !== 0 || empty($output)) {
            //return ["Syntax hatası algılandı veya komut çalıştırılamadı."];
        }

        // JSON çözümlemesi
        //$jsonOutput = json_decode(implode('', $output), true);

        //if (json_last_error() !== JSON_ERROR_NONE) {
         //   //error_log("JSON Hatası: " . json_last_error_msg());
            //return ["JSON çözümlemesinde hata oluştu."];
        //}

        return $this->parseParallelLintOutput($output);//$jsonOutput['results']['errors'] ?? [];
    }

    public function parseParallelLintOutput($output) {
        $jsonOutput = '';

        // Çıktıdaki JSON formatını ayır
        foreach ($output as $line) {
            if (str_starts_with(trim($line), '{') && str_ends_with(trim($line), '}')) {
                $jsonOutput = $line;
                break;
            }
        }

        if (empty($jsonOutput)) {
            //error_log("Hata: JSON formatlı çıktı bulunamadı.");
            return [];
        }

        // JSON'u çözümle
        $decoded = json_decode($jsonOutput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            //error_log("JSON Hatası: " . json_last_error_msg());
            return [];
        }

        return $decoded['results']['errors'] ?? [];
    }


    public function log($functionName, $description){
        $log = new Logger();
        $log->logAction($functionName, $description);
    }
}