<?php

use MatthiasMullie\Minify;

class MethodClass {
    public function createFiles($includeParentFolderName = false, $platform = 'frontend') {
        
        if (!in_array($platform, ["frontend", "admin"])) {
            return;
        }

        $methodsDirs = [];

        if ($platform == "frontend") {
            $storeDir = THEME_INCLUDES_PATH . 'methods/';
            $methodsDirs[] = THEME_INCLUDES_PATH . 'methods/';
        } elseif ($platform == "admin") {
            $storeDir = THEME_INCLUDES_PATH . 'admin/';
            $methodsDirs[] = THEME_INCLUDES_PATH . 'admin/';
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
                        if ($methodDir == "woo" && !ENABLE_ECOMMERCE) {
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
        $indexPhpFile = fopen($tempPhpFilePath, 'w');
        fwrite($indexPhpFile, $this->optimizeCode($indexPhpContent));
        fclose($indexPhpFile);

        // Kod hatalarını kontrol et
        $errors = [];
        if($this->checkForSyntaxErrors($tempPhpFilePath)){
            unlink($tempPhpFilePath);
            $errors = $this->checkForSyntaxErrors($storeDir);
        }

        if (!$errors) {
            // Eğer hata yoksa temp dosyasını sil
            unlink($tempPhpFilePath);

            // index.php dosyasını oluştur veya güncelle
            $indexPhpFile = fopen($storeDir . 'index.php', 'w');
            fwrite($indexPhpFile, $this->optimizeCode($indexPhpContent));
            fclose($indexPhpFile);

        } else {
            // Eğer hata varsa temp dosyasını sil ve hata mesajını görüntüle
            unlink($tempPhpFilePath);
            //echo "Kod hataları tespit edildi:\n$error";
        }

        // index.js dosyasını oluşturma
        $indexJsFile = fopen($storeDir . 'index.js', 'w');
        fwrite($indexJsFile, $this->optimizeCode($indexJsContent));
        fclose($indexJsFile);
        $this->copyToTheme($indexJsFile, $platform);
        
        return $errors;
    }

    private function copyToTheme($path, $platform) {
        $target_dir = get_template_directory() . '/static/js/min/';
        $target_file = $target_dir . ($platform=="frontend"?"methods":$platform).'.min.js'; // Hedef dosya
        $source_file = $path; // Kaynak dosya
        if (!file_exists($target_file)) {
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true); 
            }
            if (file_exists($source_file)) {
                copy($source_file, $target_file);
            }
        }
    }

    private function requirement($phpContent = "") {
        $settingLine = '$required_setting';

        if (strpos($phpContent, $settingLine) !== false) {
            $settingValueStart = strpos($phpContent, $settingLine) + strlen($settingLine);
            $settingValueEnd = strpos($phpContent, ";", $settingValueStart);

            if ($settingValueStart !== false && $settingValueEnd !== false) {
                $variableName = trim(substr($phpContent, $settingValueStart, $settingValueEnd - $settingValueStart));
                $equalsIndex = strpos($variableName, "=");

                // remove required_setting line
                $settingLineLength = $settingValueEnd - $settingValueStart + 1;
                $phpContent = substr_replace($phpContent, "", $settingValueStart, $settingLineLength);
                $phpContent = str_replace($settingLine, "", $phpContent);

                if ($equalsIndex !== false) {
                    $variableName = trim(str_replace("=", "", $variableName));
                }
                if (defined($variableName)) {
                    if (!constant($variableName)) {
                        $phpContent = "";
                    }
                }
            }
        }
        return $phpContent;
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
                    $indexJsContent .= "ajax_hooks['{$subfolderName}'] = {$compressedJsContent};\n";
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
                // Alt klasörleri işleme (rekürsif olarak)
                $pathName = ($includeParentFolderName ? $subfolderName . '/' : '') . $subDir;
                $this->processSubfolder($subfolderPath . '/' . $subDir, $pathName, $indexPhpContent, $indexJsContent, $includeParentFolderName, $platform);
            }
        }
    }

    private function removeEchoTags($content) {
        $content = preg_replace('/<\?(php)?|\?>/i', '', $content);
        return $content;
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
            $extraDirs[] = get_stylesheet_directory() . '/theme/includes/methods/';
        } elseif ($platform == "admin") {
            $extraDirs[] = get_stylesheet_directory() . '/theme/includes/admin/';
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
            return "var ajax_hooks = {};\n";
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
        $fileList = [$file];
        chdir(get_stylesheet_directory()."/vendor/bin/");
        //error_log(get_stylesheet_directory()."/vendor/bin/");
        $command = 'parallel-lint';
        $options = [
            '-e' => 'php',
            '--exclude' => '.git', // .git ve vendor dizinlerini hariç tut
            '--colors' => null, // Renkli çıktı kullan
            '--no-progress' => null, // İlerleme çubuğunu kapat
            '--json' => null
        ];
        $arguments = [];
        foreach ($options as $option => $value) {
            if ($value === null) {
                $arguments[] = $option;
            } else {
                $arguments[] = $option . ' ' . escapeshellarg($value);
            }
        }
        $arguments[] = implode(' ', array_map('escapeshellarg', $fileList));
        $output = [];
        $returnCode = 0;
        //error_log($command . ' ' . implode(' ', $arguments));
        exec($command . ' ' . implode(' ', $arguments), $output, $returnCode);
        //$outputContent = implode(PHP_EOL, $output);
        //error_log(json_encode($output));
        $errors = [];
        if($output){
            $output = json_decode($output[0], true);
            $errors = $output["results"]["errors"];            
        }
        //error_log(json_encode($errors));
        return $errors;
    }

    public function log($functionName, $description){
        $log = new Logger();
        $log->logAction($functionName, $description);
    }
}