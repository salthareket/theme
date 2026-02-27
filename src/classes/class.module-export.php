<?php

use Peast\Peast;
use Peast\Renderer;
use Peast\Formatter\Compact;
use Peast\Formatter\PrettyPrint;

class JS_Batch_Modernizer_v1 {
    private $sources = [];
    private $output_dir;
    private $master_index = [];

    private $reserved_words = [
        'abstract', 'arguments', 'await', 'boolean', 'break', 'byte', 'case', 'catch', 
        'char', 'class', 'const', 'continue', 'debugger', 'default', 'delete', 'do', 
        'double', 'else', 'enum', 'eval', 'export', 'extends', 'false', 'final', 
        'finally', 'float', 'for', 'function', 'goto', 'if', 'implements', 'import', 
        'in', 'instanceof', 'int', 'interface', 'let', 'long', 'native', 'new', 
        'null', 'package', 'private', 'protected', 'public', 'return', 'short', 
        'static', 'super', 'switch', 'synchronized', 'this', 'throw', 'throws', 
        'transient', 'true', 'try', 'typeof', 'var', 'void', 'volatile', 'while', 'with', 'yield'
    ];

    public function __construct(array $sources, $output) {
        $this->sources = $sources;
        $this->output_dir = rtrim($output, '/');
    }

    public function run() {
        // 1. AŞAMA: Master Index Oluştur (Sadece gerçek atomlar)
        foreach ($this->sources as $config) {
            if (str_contains($config['src'], '*')) continue; 
            $clean_src = rtrim($config['src'], '/');
            if (!is_dir($clean_src)) continue;

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($clean_src));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'js') continue;
                $this->scan_top_level_functions($file->getPathname(), $config['folder']);
            }
        }

        // 2. AŞAMA: Kaydetme
        foreach ($this->sources as $config) {
            $is_plugin_mode = str_contains($config['src'], '*');
            $clean_src = rtrim($config['src'], '/*');
            $target_path = $this->output_dir . '/' . $config['folder'];
            
            if (!is_dir($target_path)) mkdir($target_path, 0777, true);
            if (!is_dir($clean_src)) continue;

            $files = new DirectoryIterator($clean_src);
            foreach ($files as $file) {
                if ($file->isDot() || $file->getExtension() !== 'js') continue;
                $content = file_get_contents($file->getPathname());
                
                if ($is_plugin_mode) {
                    $this->save_plugin_file($content, $file->getFilename(), $target_path, $config['folder']);
                } else {
                    $this->save_function_atoms($content, $file->getFilename(), $target_path, $config['folder']);
                }
            }
        }
    }

    private function scan_top_level_functions($path, $folder) {
        $content = file_get_contents($path);
        // Artık ^ (satır başı) kuralını kaldırdık, dosyanın genelindeki ana tanımları yakalıyoruz
        $pattern = '/\b(function|class)\s+([a-zA-Z0-9_]+)|\b(var|const|let)\s+([a-zA-Z0-9_]+)\s*=\s*(?:function|class|\([^)]*\)\s*=>)/';
        if (preg_match_all($pattern, $content, $matches)) {
            $names = array_filter(array_merge($matches[2], $matches[4]));
            foreach ($names as $name) {
                if (!in_array($name, $this->reserved_words)) {
                    $this->master_index[$name] = "{$folder}/{$name}.js";
                }
            }
        }
    }

    private function save_plugin_file($content, $filename, $target_path, $current_folder) {
        // Önce yanlış eklenen importları ve exportları bi süpürelim
        $content = str_replace('export ', '', $content);
        
        // YENİ: Sadece master_index'teki GERÇEK ana fonksiyonlar için import üret
        $imports = $this->generate_imports($content, $current_folder);

        if (str_contains($content, 'typeof define') || str_contains($content, '!function')) {
            // Kütüphane mantığı (Masonry vb.)
            $lib_name = ucfirst(explode('-', str_replace('.js', '', $filename))[0]);
            $final = "/** Library: {$filename} **/\n" . $imports . "\n" . $content;
            $final .= "\n\nexport default window.{$lib_name};";
        } else {
            // NORMAL PLUGIN (Swiper, Plyr vb.)
            // Sadece satır başında duran (0. kolon) tanımların başına export koy
            $pattern = '/^(function|var|const|let|class)\s+([a-zA-Z0-9_]+)/m';
            
            $content = preg_replace_callback($pattern, function($m) {
                // Eğer yakalanan isim bizim ana atom listemizde varsa export ekle
                return 'export ' . $m[0];
            }, $content);

            $final = "/** Plugin: {$filename} **/\n" . $imports . "\n" . $content;
        }

        file_put_contents($target_path . '/' . $filename, $final);
    }

    private function save_function_atoms($content, $filename, $target_path, $current_folder) {
        // Regex: Sadece satır başından başlayan tanımları yakalar
        $pattern = '/^(function\s+([a-zA-Z0-9_]+)|class\s+([a-zA-Z0-9_]+)|(?:var|const|let)\s+([a-zA-Z0-9_]+)\s*=\s*(?:function|class|\([^)]*\)\s*=>))/m';
        
        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
        
        foreach ($matches[0] as $index => $match) {
            $start_pos = $match[1];
            $name = !empty($matches[2][$index][0]) ? $matches[2][$index][0] : 
                   (!empty($matches[3][$index][0]) ? $matches[3][$index][0] : $matches[4][$index][0]);

            if (in_array($name, $this->reserved_words)) continue;

            $end_pos = $this->find_block_end($content, $start_pos);
            if ($end_pos !== false) {
                $block = substr($content, $start_pos, $end_pos - $start_pos);
                $imports = $this->generate_imports($block, $current_folder, $name);
                
                $final = "/** Atom: {$name} **/\n" . $imports . "\n";
                $final .= "export " . $block . "\n";
                // Global window bridge
                $final .= "if(typeof window.{$name}==='undefined'){window.{$name}={$name};}";
                
                file_put_contents($target_path . '/' . $name . '.js', $final);
            }
        }
    }

    private function generate_imports($content, $current_folder, $current_name = '') {
        $imports = "";
        foreach ($this->master_index as $func => $path) {
            if ($func === $current_name) continue;
            // Fonksiyon isminin içerikte TAM KELİME (\b) olarak geçtiğini doğrula
            if (preg_match('/\b' . preg_quote($func, '/') . '\b/', $content)) {
                $parts = explode('/', $path);
                $url = ($parts[0] === $current_folder) ? "./{$parts[1]}" : "../{$parts[0]}/{$parts[1]}";
                $imports .= "import { {$func} } from '{$url}';\n";
            }
        }
        return $imports;
    }

    private function find_block_end($content, $start) {
        $br_start = strpos($content, '{', $start);
        if ($br_start === false) return false;
        $count = 1; $curr = $br_start + 1; $len = strlen($content);
        while ($count > 0 && $curr < $len) {
            if ($content[$curr] === '{') $count++;
            else if ($content[$curr] === '}') $count--;
            $curr++;
        }
        return ($count === 0) ? $curr : false;
    }
}
class JS_Batch_Modernizer_v2{
    private $sources = [];
    private $output_dir;
    private $master_index = [];

    public function __construct(array $sources, $output) {
        $this->sources = $sources;
        $this->output_dir = rtrim($output, '/');
    }

    public function run() {
        // 1. AŞAMA: Master Index (Hangi atom nerede biliyoruz)
        foreach ($this->sources as $config) {
            if (str_contains($config['src'], '*')) continue; 
            $clean_src = rtrim($config['src'], '/');
            if (!is_dir($clean_src)) continue;

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($clean_src));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'js') continue;
                $this->scan_top_level($file->getPathname(), $config['folder']);
            }
        }

        // 2. AŞAMA: Dosyaları Kaydetme
        foreach ($this->sources as $config) {
            $is_plugin_mode = str_contains($config['src'], '*');
            $clean_src = rtrim($config['src'], '/*');
            $target_path = $this->output_dir . '/' . $config['folder'];
            
            if (!is_dir($target_path)) mkdir($target_path, 0777, true);
            if (!is_dir($clean_src)) continue;

            $files = new DirectoryIterator($clean_src);
            foreach ($files as $file) {
                if ($file->isDot() || $file->getExtension() !== 'js') continue;
                $content = file_get_contents($file->getPathname());
                
                if ($is_plugin_mode) {
                    $this->save_plugin_file($content, $file->getFilename(), $target_path, $config['folder']);
                } else {
                    $this->save_function_atoms($content, $file->getFilename(), $target_path, $config['folder']);
                }
            }
        }
    }

    private function scan_top_level($path, $folder) {
        $content = file_get_contents($path);
        // Sadece en dıştaki fonksiyon isimlerini topla
        if (preg_match_all('/^function\s+([a-zA-Z0-9_]+)/m', $content, $matches)) {
            foreach ($matches[1] as $name) {
                $this->master_index[$name] = "{$folder}/{$name}.js";
            }
        }
    }

    private function save_plugin_file($content, $filename, $target_path, $current_folder) {
        // 1. Önce tüm 'export ' kelimelerini temizle
        $content = preg_replace('/export\s+/', '', $content);
        
        // 2. Importları oluştur
        $imports = $this->generate_imports($content, $current_folder);

        if (str_contains($content, 'typeof define') || str_contains($content, '!function')) {
            $lib_name = ucfirst(explode('-', str_replace('.js', '', $filename))[0]);
            $final = "/** Library: {$filename} **/\n" . $imports . "\n" . $content . "\n\nexport default window.{$lib_name};";
        } else {
            // 3. YENİ REGEX: Satır başı şartı (^) yerine 'boundary' (\b) kullanıyoruz.
            // (?<!\{) -> Önünde { olmasın (bir blok içinde olmasın)
            // \b(async\s+)?function\s+init_ -> Bağımsız bir init_ fonksiyonu olsun
            $pattern = '/(?<!\{)\b((?:async\s+)?function\s+init_[a-zA-Z0-9_]+)/';

            if (preg_match($pattern, $content)) {
                // Sadece ana fonksiyonun önüne export ekle
                $content = preg_replace($pattern, 'export $1', $content);
            } else {
                // Eğer init_ yoksa, dosyanın en başındaki ilk fonksiyonu zorla export et
                $content = preg_replace('/\b(function\s+[a-zA-Z0-9_]+)/', 'export $1', $content, 1);
            }

            $final = "/** Plugin: {$filename} **/\n" . $imports . "\n" . $content;
        }

        file_put_contents($target_path . '/' . $filename, $final);
    }

    private function save_function_atoms($content, $filename, $target_path, $current_folder) {
        // Atomlar için de sadece satır başında başlayan fonksiyonları böl
        preg_match_all('/^(async\s+)?(function\s+([a-zA-Z0-9_]+))/m', $content, $matches, PREG_OFFSET_CAPTURE);
        
        foreach ($matches[2] as $idx => $match) {
            $start = $match[1];
            $name = $matches[3][$idx][0];
            $end = $this->find_block_end($content, $start);

            if ($end !== false) {
                $block = substr($content, $start, $end - $start);
                $imports = $this->generate_imports($block, $current_folder, $name);
                
                $final = "/** Atom: {$name} **/\n" . $imports . "\nexport " . $block . "\n";
                $final .= "if(typeof window.{$name}==='undefined'){window.{$name}={$name};}";
                
                file_put_contents($target_path . '/' . $name . '.js', $final);
            }
        }
    }

    private function generate_imports($content, $current_folder, $current_name = '') {
        $imports = "";
        foreach ($this->master_index as $func => $path) {
            if ($func === $current_name) continue;
            // İçerikte tam kelime olarak geçiyorsa import ekle
            if (preg_match('/\b' . preg_quote($func, '/') . '\b/', $content)) {
                $parts = explode('/', $path);
                $url = ($parts[0] === $current_folder) ? "./{$parts[1]}" : "../{$parts[0]}/{$parts[1]}";
                $imports .= "import { {$func} } from '{$url}';\n";
            }
        }
        return $imports;
    }

    private function find_block_end($content, $start) {
        $br_start = strpos($content, '{', $start);
        if ($br_start === false) return false;
        $count = 1; $curr = $br_start + 1; $len = strlen($content);
        while ($count > 0 && $curr < $len) {
            if ($content[$curr] === '{') $count++;
            else if ($content[$curr] === '}') $count--;
            $curr++;
        }
        return ($count === 0) ? $curr : false;
    }
}
class JS_Batch_Modernizer_v3{

    private $sources = [];

    private $output_dir;

    private $master_index = [];


    public function __construct(array $sources, $output) {

        $this->sources = $sources;

        $this->output_dir = rtrim($output, '/');

    }


    public function run() {

        foreach ($this->sources as $config) {

            if (str_contains($config['src'], '*')) continue; 

            $this->scan_source_directory($config);

        }


        foreach ($this->sources as $config) {

            $is_plugin_mode = str_contains($config['src'], '*');

            $clean_src = rtrim($config['src'], '/*');

            $target_path = $this->output_dir . '/' . $config['folder'];

            

            if (!is_dir($target_path)) mkdir($target_path, 0777, true);

            if (!is_dir($clean_src)) continue;


            $files = new DirectoryIterator($clean_src);

            foreach ($files as $file) {

                if ($file->isDot() || $file->getExtension() !== 'js') continue;

                $content = file_get_contents($file->getPathname());

                

                try {

                    if ($is_plugin_mode) {

                        $this->save_plugin_file_smart($content, $file->getFilename(), $target_path, $config['folder']);

                    } else {

                        // PARÇALAMA (Artık jQuery eklentilerini de kapsıyor)

                        $this->save_function_atoms_with_peast($content, $target_path, $config['folder']);

                    }

                } catch (\Exception $e) {

                    //error_log("Modernizer Hatası (" . $file->getFilename() . "): " . $e->getMessage());

                }

            }

        }

    }


    private function scan_source_directory($config) {

        $clean_src = rtrim($config['src'], '/');

        if (!is_dir($clean_src)) return;

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($clean_src));

        foreach ($files as $file) {

            if ($file->getExtension() !== 'js') continue;

            try {

                $content = file_get_contents($file->getPathname());

                $ast = Peast::latest($content)->parse();

                foreach ($ast->getBody() as $node) {

                    $name = null;


                    // 1. Düz Fonksiyonlar

                    if ($node instanceof \Peast\Syntax\Node\FunctionDeclaration) {

                        $name = $node->getId()->getName();

                    } 

                    // 2. Class Tanımları (YENİ EKLENDİ)

                    elseif ($node instanceof \Peast\Syntax\Node\ClassDeclaration) {

                        $name = $node->getId()->getName();

                    }

                    // 3. jQuery Eklentileri

                    elseif ($this->is_jquery_plugin($node)) {

                        $name = $this->get_jquery_plugin_name($node);

                    }

                    // 4. Değişken Atamaları (var favorites = new ...) (YENİ EKLENDİ)

                    elseif ($node instanceof \Peast\Syntax\Node\VariableDeclaration) {

                        foreach ($node->getDeclarations() as $decl) {

                            $name = $decl->getId()->getName();

                        }

                    }


                    if ($name) {

                        $this->master_index[$name] = $config['folder'] . '/' . $name . '.js';

                    }

                }

            } catch (\Exception $e) { continue; }

        }

    }


    private function save_function_atoms_with_peast($content, $target_path, $current_folder) {

        try {

            $ast = \Peast\Peast::latest($content)->parse();

            $renderer = new \Peast\Renderer();

            $renderer->setFormatter(new \Peast\Formatter\Compact());


            foreach ($ast->getBody() as $node) {

                $name = null;

                $minified_code = null;


                // DURUM A: Düz Fonksiyon Tanımı

                if ($node instanceof \Peast\Syntax\Node\FunctionDeclaration) {

                    $name = $node->getId()->getName();

                    $exportNode = new \Peast\Syntax\Node\ExportNamedDeclaration();

                    $exportNode->setDeclaration($node);

                    $minified_code = $renderer->render($exportNode);

                } 

                // DURUM B: Class Tanımı (YENİ EKLENDİ - favorites.js içindeki classları yakalar)

                elseif ($node instanceof \Peast\Syntax\Node\ClassDeclaration) {

                    $name = $node->getId()->getName();

                    $exportNode = new \Peast\Syntax\Node\ExportNamedDeclaration();

                    $exportNode->setDeclaration($node);

                    $minified_code = $renderer->render($exportNode);

                }

                // DURUM C: Variable/Instance Tanımı (var favorites = new FavoritesManager())

                elseif ($node instanceof \Peast\Syntax\Node\VariableDeclaration) {

                    foreach ($node->getDeclarations() as $decl) {

                        $name = $decl->getId()->getName();

                        $exportNode = new \Peast\Syntax\Node\ExportNamedDeclaration();

                        $exportNode->setDeclaration($node); // Tüm var/let/const satırını export et

                        $minified_code = $renderer->render($exportNode);

                    }

                }

                // DURUM D: jQuery Plugin

                elseif ($this->is_jquery_plugin($node)) {

                    $name = $this->get_jquery_plugin_name($node);

                    $minified_code = $renderer->render($node);

                    $minified_code .= "\nconst {$name} = $.fn.{$name}; export { {$name} };";

                }


                // Dosyaya Yazma İşlemi

                if ($name && $minified_code) {

                    $imports = $this->generate_imports($minified_code, $current_folder, $name);

                    $final = $imports . $minified_code;

                    $final .= "\nif(typeof window!=='undefined' && typeof window.{$name}==='undefined'){window.{$name}={$name};}";


                    file_put_contents($target_path . '/' . $name . '.js', $final);

                }

            }

        } catch (\Exception $e) { 

            //error_log("Dinamik Parçalama Hatası: " . $e->getMessage()); 

        }

    }


    // YARDIMCI: Bu bir $.fn... ataması mı?

    private function is_jquery_plugin($node) {

        if ($node instanceof \Peast\Syntax\Node\ExpressionStatement) {

            $expr = $node->getExpression();

            if ($expr instanceof \Peast\Syntax\Node\AssignmentExpression) {

                $left = $expr->getLeft();

                if ($left instanceof \Peast\Syntax\Node\MemberExpression) {

                    $obj = $left->getObject();

                    if ($obj instanceof \Peast\Syntax\Node\MemberExpression) {

                        // $.fn kısmını kontrol et

                        $raw = (new Renderer())->setFormatter(new Compact())->render($obj);

                        return str_contains($raw, '$.fn') || str_contains($raw, 'jQuery.fn');

                    }

                }

            }

        }

        return false;

    }


    // YARDIMCI: Atamadaki isim ne? (fitEmbedBackground)

    private function get_jquery_plugin_name($node) {

        $expr = $node->getExpression();

        $left = $expr->getLeft();

        return $left->getProperty()->getName() ?? $left->getProperty()->getValue();

    }


    private function save_plugin_file_smart($content, $filename, $target_path, $current_folder) {

        $is_init_file = str_ends_with(str_replace('.js', '', $filename), '-init');

        

        if ($is_init_file) {

            // --- SENARYO 1: -INIT DOSYALARI ---

            try {

                $ast = \Peast\Peast::latest($content)->parse();

                $newBody = []; $exportedNames = [];

                foreach ($ast->getBody() as $node) {

                    if ($node instanceof \Peast\Syntax\Node\FunctionDeclaration || $node instanceof \Peast\Syntax\Node\ClassDeclaration) {

                        $nodeName = $node->getId()->getName();

                        $exportedNames[] = $nodeName;

                        $exportNode = new \Peast\Syntax\Node\ExportNamedDeclaration();

                        $exportNode->setDeclaration($node);

                        $newBody[] = $exportNode;

                    } else { $newBody[] = $node; }

                }

                $ast->setBody($newBody);

                $renderer = new \Peast\Renderer();

                $renderer->setFormatter(new \Peast\Formatter\Compact());

                $clean_code = $renderer->render($ast);

                $imports = $this->generate_imports($clean_code, $current_folder);

                $final = $imports . $clean_code;

                if (!empty($exportedNames)) {

                    $assigns = "";

                    foreach ($exportedNames as $eName) { $assigns .= "if(typeof window.{$eName}==='undefined') window.{$eName} = {$eName}; "; }

                    $final .= "\n/* Global Bridge */\nif(typeof window!=='undefined'){ {$assigns} }";

                }

            } catch (\Exception $e) {

                $final = $this->generate_imports($content, $current_folder) . $content;

            }

        } else {

            // --- SENARYO 2: PLUGIN PROFILER (TAM SENİN İSTEDİĞİN GİBİ) ---

            

            $type = 'STANDARD';

            $detectedName = "";

            $cleanForAnalysis = preg_replace('!/\*.*?\*/!s', '', substr($content, 0, 5000));


            // 1. Profil Saptama: GLOBAL_VAR (Swiper, Bootbox vb.)

            if (preg_match('/(?:^|\s|;)(?:var|const|let|class)\s+([a-zA-Z0-9_]+)\s*(?:=|\{)/', $cleanForAnalysis, $matches)) {

                $potential = $matches[1];

                if (!in_array($potential, ['e','t','n','r','i','o','s','a','window','document','jQuery','$','_'])) {

                    $detectedName = $potential;

                    $type = 'GLOBAL_VAR';

                }

            }


            // 2. Profil Saptama: UMD_LIBRARY (isInViewport vb.)

            if (str_contains($content, 'define.amd') || str_contains($content, 'module.exports')) {

                $type = 'UMD_LIBRARY';

            }


            // 3. Profil Saptama: JQUERY_PLUGIN

            if ($type === 'STANDARD' && (str_contains($content, '$.fn.') || str_contains($content, 'jQuery.fn.'))) {

                $type = 'JQUERY_PLUGIN';

            }


            // Ortak Header

            $header = "var global = window; var self = window;\nvar jQuery = window.jQuery || window.$ || {}; var $ = jQuery;\n";

            $final = $header;


            // --- PROFİLLER BURADA ---

            switch ($type) {

                case 'UMD_LIBRARY':

                    // 1. Önce window.jQuery'nin varlığından emin oluyoruz.

                    $final .= "\nif(typeof window.jQuery === 'undefined') { window.jQuery = window.$ || {}; }\n";

                    

                    // 2. Kütüphaneyi kandırıp her halükarda window.jQuery'ye yazmasını sağlıyoruz.

                    // define/exports/module/require hepsini undefined yaparak 'else' koluna (global) zorluyoruz.

                    $final .= "(function(define, exports, module, require) {\n";

                    $final .= "  var self = window; var global = window;\n";

                    $final .= $content;

                    $final .= "\n}).call(window, undefined, undefined, undefined, undefined);\n";


                    // 3. --- KRİTİK NOKTA: MANUEL SELECTOR ENJEKSİYONU ---

                    // Eğer kütüphane bir şekilde içerde kaldıysa, window.jQuery'ye selector'ı zorla öğretiyoruz.

                    if (str_contains($content, 'in-viewport')) {

                        $final .= "

                        (function($){

                            if($ && $.expr) {

                                var p = $.expr.pseudos || $.expr[':'];

                                if(!p['in-viewport']) {

                                    // Plugin içindeki o ve r fonksiyonlarına ulaşmak için kodu tekrar simüle etmiyoruz,

                                    // Eğer plugin yüklenmişse $.fn.isInViewport üzerinden çalışır.

                                    p['in-viewport'] = function(elem, i, m) {

                                        return $(elem).isInViewport({tolerance: m[3]});

                                    };

                                }

                            }

                        })(window.jQuery);";

                    }

                    break;


                case 'GLOBAL_VAR':

                    // Swiper/Bootbox gibi yapıları oldugu gibi bırakıp window bridge kuruyoruz

                    $final .= "\n" . $content . "\n";

                    $final .= "\nif(typeof {$detectedName} !== 'undefined') window.{$detectedName} = {$detectedName};";

                    break;


                case 'JQUERY_PLUGIN':

                case 'STANDARD':

                default:

                    // Standart jQuery sarmalı

                    $final .= "\n(function(jQuery, $) {\n" . $content . "\n}).call(window, window.jQuery, window.jQuery);";

                    break;

            }


            // Evrensel Export (Profil ne olursa olsun yakalanan isme göre)

            if ($detectedName) {

                $final .= "\n\nexport default (window.{$detectedName} || window.jQuery);";

            } else {

                $final .= "\n\nexport default window.jQuery;";

            }

        }


        file_put_contents($target_path . '/' . $filename, $final);

    }


    private function generate_imports($content, $current_folder, $current_name = '') {

        $imports = "";

        $pseudo_map = [':in-viewport' => 'is-in-viewport.js', ':draggable' => 'jquery-ui-draggable.js'];


        // 1. ADIM: Yerel tanımları bul

        preg_match_all('/(?:function\s+|class\s+|var\s+|let\s+|const\s+|(?:\$|jQuery)\.fn\.)([a-zA-Z0-9_]+)/', $content, $matches);

        $local_definitions = $matches[1] ?? [];


        // 2. ADIM: Master Index üzerinden tara

        foreach ($this->master_index as $func => $path) {

            if ($func === $current_name || in_array($func, $local_definitions)) continue;


            // 3. ADIM: TAM DİNAMİK FİLTRE (Circular Dependency Önleyici)

            // Eğer şu an bir Class dosyası yazıyorsak:

            if (!empty($current_name) && ctype_upper($current_name[0])) {

                

                // Hedef dosyanın içeriğini kontrol et (instance mı değil mi?)

                $target_full_path = $this->output_dir . '/' . $path;

                if (file_exists($target_full_path)) {

                    $target_content = file_get_contents($target_full_path);

                    

                    // Eğer hedef dosya "new CurrentClass" içeriyorsa, bu bir instance dosyasıdır.

                    // Kısır döngü olmaması için bunu Class içine import ETME.

                    if (str_contains($target_content, "new " . $current_name)) {

                        continue; 

                    }

                }

            }


            // 4. ADIM: Standart Kelime Kontrolü ve Import Ekleme

            if (preg_match('/\b' . preg_quote($func, '/') . '\b/', $content)) {

                // Pseudo kontrolü

                $is_plugin = false;

                foreach ($pseudo_map as $p_file) { if (str_contains($path, $p_file)) { $is_plugin = true; break; } }

                if ($is_plugin) continue;


                $parts = explode('/', $path);

                $url = ($parts[0] === $current_folder) ? "./{$parts[1]}" : "../{$parts[0]}/{$parts[1]}";

                

                if (strpos($imports, "{ {$func} }") === false) {

                    $imports .= "import { {$func} } from '{$url}';\n";

                }

            }

        }

        return $imports;

    }
} 

class JS_Batch_Modernizer{
    private $sources = [];
    private $output_dir;
    private $master_index = [];

    public function __construct(array $sources, $output) {
        $this->sources = $sources;
        $this->output_dir = rtrim($output, '/');
    }
    public function run() {
        // 1. ADIM: İlk tarama (Artık yıldızlı klasörleri de kapsıyor!)
        foreach ($this->sources as $config) {
            // Yıldız olsa da olmasa da tara ki içindeki fonksiyonları indexleyelim
            $this->scan_source_directory($config);
        }

        // 2. ADIM: Parçalama ve Kayıt (Placeholder ile)
        foreach ($this->sources as $config) {
            $is_plugin_mode = str_contains($config['src'], '*');
            $clean_src = rtrim($config['src'], '/*');
            $target_path = $this->output_dir . '/' . $config['folder'];
            
            if (!is_dir($target_path)) mkdir($target_path, 0777, true);
            if (!is_dir($clean_src)) continue;

            $files = new DirectoryIterator($clean_src);
            foreach ($files as $file) {
                if ($file->isDot() || $file->getExtension() !== 'js') continue;
                $content = file_get_contents($file->getPathname());
                
                if ($is_plugin_mode) {
                    $this->save_plugin_file_smart($content, $file->getFilename(), $target_path, $config['folder']);
                } else {
                    $this->save_function_atoms_with_peast($content, $target_path, $config['folder']);
                }
            }
        }

        // 3. ADIM: Finalize (Tüm dosyalar oluştuktan sonra importları bağla)
        $this->finalize_imports();
        
        // Debug için JSON dökümü
        file_put_contents($this->output_dir . '/master_index_debug.json', json_encode($this->master_index, JSON_PRETTY_PRINT));
    }

    private function scan_source_directory($config) {
        $clean_src = rtrim(str_replace('*', '', $config['src']), '/');
        if (!is_dir($clean_src)) return;
        
        $is_plugin_mode = ($config['folder'] === 'plugins');
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($clean_src));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'js') continue;
            $filename = $file->getFilename();

            // Plugin klasöründe sadece -init dosyalarını parçalamak için tara, diğerleri kütüphanedir
            if ($is_plugin_mode && !str_ends_with(str_replace('.js', '', $filename), '-init')) {
                // Standart kütüphaneyi "default" olarak indexle
                $lib_name = str_replace('.js', '', $filename);
                $this->master_index[$lib_name] = $config['folder'] . '/' . $filename;
                continue;
            }

            try {
                $content = file_get_contents($file->getPathname());
                $ast = \Peast\Peast::latest($content)->parse();
                
                foreach ($ast->getBody() as $node) {
                    $names = $this->extract_names_from_node($node);
                    foreach ($names as $name) {
                        if ($is_plugin_mode) {
                            $this->master_index[$name] = $config['folder'] . '/' . $filename;
                        } else {
                            $this->master_index[$name] = $config['folder'] . '/' . $name . '.js';
                        }
                    }
                }
            } catch (\Exception $e) { continue; }
        }
    }

    // YARDIMCI: Düğümden (Node) tanımlanan isimleri çıkarır (Function, Class, Var, Object Assignment)
    private function extract_names_from_node($node) {
        $found = [];
        if ($node instanceof \Peast\Syntax\Node\FunctionDeclaration || $node instanceof \Peast\Syntax\Node\ClassDeclaration) {
            $found[] = $node->getId()->getName();
        } elseif ($node instanceof \Peast\Syntax\Node\VariableDeclaration) {
            foreach ($node->getDeclarations() as $decl) {
                $found[] = $decl->getId()->getName();
            }
        } elseif ($node instanceof \Peast\Syntax\Node\ExpressionStatement) {
            $expr = $node->getExpression();
            // waiting_init = { ... } veya waiting_init.add = ... durumları
            if ($expr instanceof \Peast\Syntax\Node\AssignmentExpression) {
                $left = $expr->getLeft();
                if ($left instanceof \Peast\Syntax\Node\Identifier) {
                    $found[] = $left->getName();
                }
            }
        }
        return $found;
    }

    private function save_function_atoms_with_peast($content, $target_path, $current_folder) {
        try {
            $ast = \Peast\Peast::latest($content)->parse();
            $renderer = new \Peast\Renderer();
            $renderer->setFormatter(new \Peast\Formatter\Compact());

            foreach ($ast->getBody() as $node) {
                $name = null; $code = null;

                if ($node instanceof \Peast\Syntax\Node\FunctionDeclaration || $node instanceof \Peast\Syntax\Node\ClassDeclaration) {
                    $name = $node->getId()->getName();
                    $exportNode = new \Peast\Syntax\Node\ExportNamedDeclaration();
                    $exportNode->setDeclaration($node);
                    $code = $renderer->render($exportNode);
                } 
                elseif ($node instanceof \Peast\Syntax\Node\VariableDeclaration) {
                    foreach ($node->getDeclarations() as $decl) {
                        $name = $decl->getId()->getName();
                        $exportNode = new \Peast\Syntax\Node\ExportNamedDeclaration();
                        $exportNode->setDeclaration($node);
                        $code = $renderer->render($exportNode);
                    }
                }
                // waiting_init = { ... } gibi ifadeleri yakala
                elseif ($node instanceof \Peast\Syntax\Node\ExpressionStatement && 
                        $node->getExpression() instanceof \Peast\Syntax\Node\AssignmentExpression) {
                    $expr = $node->getExpression();
                    if ($expr->getLeft() instanceof \Peast\Syntax\Node\Identifier) {
                        $name = $expr->getLeft()->getName();
                        $code = "export const {$name} = " . $renderer->render($expr->getRight()) . ";";
                    }
                }

                if ($name && $code) {
                    $this->master_index[$name] = $current_folder . '/' . $name . '.js';
                    $placeholder = "/* IMPORT_PLACEHOLDER_FOR_{$name} */\n";
                    $bridge = "\nif(typeof window!=='undefined' && typeof window.{$name}==='undefined'){window.{$name}={$name};}";
                    file_put_contents($target_path . '/' . $name . '.js', $placeholder . $code . $bridge);
                }
            }
        } catch (\Exception $e) { }
    }

    // YARDIMCI: Bu bir $.fn... ataması mı?
    private function is_jquery_plugin($node) {
        if ($node instanceof \Peast\Syntax\Node\ExpressionStatement) {
            $expr = $node->getExpression();
            if ($expr instanceof \Peast\Syntax\Node\AssignmentExpression) {
                $left = $expr->getLeft();
                if ($left instanceof \Peast\Syntax\Node\MemberExpression) {
                    $obj = $left->getObject();
                    if ($obj instanceof \Peast\Syntax\Node\MemberExpression) {
                        // $.fn kısmını kontrol et
                        $raw = (new Renderer())->setFormatter(new Compact())->render($obj);
                        return str_contains($raw, '$.fn') || str_contains($raw, 'jQuery.fn');
                    }
                }
            }
        }
        return false;
    }

    // YARDIMCI: Atamadaki isim ne? (fitEmbedBackground)
    private function get_jquery_plugin_name($node) {
        $expr = $node->getExpression();
        $left = $expr->getLeft();
        return $left->getProperty()->getName() ?? $left->getProperty()->getValue();
    }

    private function save_plugin_file_smart($content, $filename, $target_path, $current_folder) {
        $clean_name = str_replace('.js', '', $filename);
        $placeholder = "/* IMPORT_PLACEHOLDER_FOR_{$clean_name} */\n";
        $is_init_file = str_ends_with($clean_name, '-init');
        
        if ($is_init_file) {
            try {
                $ast = \Peast\Peast::latest($content)->parse();
                $newBody = []; $exportedNames = [];
                foreach ($ast->getBody() as $node) {
                    // Fonksiyon, Class ve Değişkenleri (lazyLoadInstance vb.) export listesine al
                    if ($node instanceof \Peast\Syntax\Node\FunctionDeclaration || 
                        $node instanceof \Peast\Syntax\Node\ClassDeclaration ||
                        $node instanceof \Peast\Syntax\Node\VariableDeclaration) {
                        
                        if ($node instanceof \Peast\Syntax\Node\VariableDeclaration) {
                            foreach ($node->getDeclarations() as $decl) {
                                $exportedNames[] = $decl->getId()->getName();
                            }
                        } else {
                            $exportedNames[] = $node->getId()->getName();
                        }

                        $exportNode = new \Peast\Syntax\Node\ExportNamedDeclaration();
                        $exportNode->setDeclaration($node);
                        $newBody[] = $exportNode;
                    } else { 
                        $newBody[] = $node; 
                    }
                }
                $ast->setBody($newBody);
                $renderer = new \Peast\Renderer();
                $renderer->setFormatter(new \Peast\Formatter\Compact());
                $final = $renderer->render($ast);
                
                // Global Bridge: window.fonksiyon = fonksiyon;
                if (!empty($exportedNames)) {
                    $assigns = "";
                    foreach ($exportedNames as $eName) { 
                        $assigns .= "if(typeof window.{$eName}==='undefined') window.{$eName} = {$eName}; "; 
                    }
                    $final .= "\n/* Global Bridge */\nif(typeof window!=='undefined'){ {$assigns} }";
                }
            } catch (\Exception $e) { $final = $content; }
        } else {
            // Standart Kütüphane (Profiler) Mantığı
            $type = 'STANDARD';
            $detectedName = "";
            $cleanForAnalysis = preg_replace('!/\*.*?\*/!s', '', substr($content, 0, 5000));

            if (preg_match('/(?:^|\s|;)(?:var|const|let|class)\s+([a-zA-Z0-9_]+)\s*(?:=|\{)/', $cleanForAnalysis, $matches)) {
                $potential = $matches[1];
                if (!in_array($potential, ['e','t','n','r','i','o','s','a','window','document','jQuery','$','_'])) {
                    $detectedName = $potential; $type = 'GLOBAL_VAR';
                }
            }
            if (str_contains($content, 'define.amd') || str_contains($content, 'module.exports')) $type = 'UMD_LIBRARY';
            if ($type === 'STANDARD' && (str_contains($content, '$.fn.') || str_contains($content, 'jQuery.fn.'))) $type = 'JQUERY_PLUGIN';

            $header = "var global = window; var self = window;\nvar jQuery = window.jQuery || window.$ || {}; var $ = jQuery;\n";
            $final = $header;

            switch ($type) {
                case 'UMD_LIBRARY':
                    $final .= "\nif(typeof window.jQuery === 'undefined') { window.jQuery = window.$ || {}; }\n";
                    $final .= "(function(define, exports, module, require) {\n var self = window; var global = window;\n" . $content . "\n}).call(window, undefined, undefined, undefined, undefined);\n";
                    break;
                case 'GLOBAL_VAR':
                    $final .= "\n" . $content . "\nif(typeof {$detectedName} !== 'undefined') window.{$detectedName} = {$detectedName};";
                    break;
                default:
                    $final .= "\n(function(jQuery, $) {\n" . $content . "\n}).call(window, window.jQuery, window.jQuery);";
                    break;
            }
            $final .= $detectedName ? "\nexport default (window.{$detectedName} || window.jQuery);" : "\nexport default window.jQuery;";
        }

        file_put_contents($target_path . '/' . $filename, $placeholder . $final);
    }

    private function generate_imports($content, $current_folder, $current_name = '') {
        $imports = "";
        $pseudo_map = [':in-viewport' => 'is-in-viewport.js', ':draggable' => 'jquery-ui-draggable.js'];

        // 1. ADIM: Yerel tanımları bul
        preg_match_all('/(?:function\s+|class\s+|var\s+|let\s+|const\s+|(?:\$|jQuery)\.fn\.)([a-zA-Z0-9_]+)/', $content, $matches);
        $local_definitions = $matches[1] ?? [];

        // 2. ADIM: Master Index üzerinden tara
        foreach ($this->master_index as $func => $path) {
            if ($func === $current_name || in_array($func, $local_definitions)) continue;

            // 3. ADIM: TAM DİNAMİK FİLTRE (Circular Dependency Önleyici)
            // Eğer şu an bir Class dosyası yazıyorsak:
            if (!empty($current_name) && ctype_upper($current_name[0])) {
                
                // Hedef dosyanın içeriğini kontrol et (instance mı değil mi?)
                $target_full_path = $this->output_dir . '/' . $path;
                if (file_exists($target_full_path)) {
                    $target_content = file_get_contents($target_full_path);
                    
                    // Eğer hedef dosya "new CurrentClass" içeriyorsa, bu bir instance dosyasıdır.
                    // Kısır döngü olmaması için bunu Class içine import ETME.
                    if (str_contains($target_content, "new " . $current_name)) {
                        continue; 
                    }
                }
            }

            // 4. ADIM: Standart Kelime Kontrolü ve Import Ekleme
            if (preg_match('/\b' . preg_quote($func, '/') . '\b/', $content)) {
                // Pseudo kontrolü
                $is_plugin = false;
                foreach ($pseudo_map as $p_file) { if (str_contains($path, $p_file)) { $is_plugin = true; break; } }
                if ($is_plugin) continue;

                $parts = explode('/', $path);
                $url = ($parts[0] === $current_folder) ? "./{$parts[1]}" : "../{$parts[0]}/{$parts[1]}";
                
                if (strpos($imports, "{ {$func} }") === false) {
                    $imports .= "import { {$func} } from '{$url}';\n";
                }
            }
        }
        return $imports;
    }

    private function finalize_imports() {
        if (!is_dir($this->output_dir)) return;

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->output_dir));
        
        // Pseudo selector eşleşmesi (Tam dosya adın: is-in-viewport.js)
        $pseudo_map = [
            ':in-viewport' => 'is-in-viewport' 
        ];

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'js') continue;

            $full_path = $file->getPathname();
            $content = file_get_contents($full_path);
            
            if (!preg_match('/\/\* IMPORT_PLACEHOLDER_FOR_(.*) \*\//', $content, $match)) continue;
            
            $current_item_name = trim($match[1]);
            $rel_path = str_replace([$this->output_dir . DIRECTORY_SEPARATOR, $this->output_dir . '/'], '', $full_path);
            $normalized_rel_path = str_replace('\\', '/', $rel_path);
            $current_folder = explode('/', $normalized_rel_path)[0];

            // 1. Kendi içindeki tanımları bul (Örn: init_plyr içindeki her şeyi yakala)
            $local_defs = $this->get_local_definitions($content);
            $local_defs[] = $current_item_name;

            $needed_imports = []; 

            // 2. MASTER INDEX TARAMASI
            foreach ($this->master_index as $item_name => $item_path) {
                $item_path = str_replace('\\', '/', $item_path);

                // Dosya kendisiyse veya dosya içinde tanımlıysa atla
                if ($item_path === $normalized_rel_path || in_array($item_name, $local_defs)) continue;

                // --- OTOMATİK PLUGIN TESPİTİ ---
                // Eğer dosya "plugins/" klasöründeyse süslü parantez kullanma
                $is_plugin = str_contains($item_path, 'plugins/');

                // Kelime kontrolü (\b kelime sınırı)
                // Plugin ise sadece varlığını kontrol et, fonksiyon ise çağrılıp çağrılmadığına bak
                if (preg_match('/\b' . preg_quote($item_name, '/') . '\b/', $content)) {
                    $url = $this->calculate_rel_url($current_folder, $item_path);
                    
                    if ($is_plugin) {
                        $needed_imports[$url]['side_effect'] = true;
                    } else {
                        $needed_imports[$url]['named'][] = $item_name;
                    }
                }
            }

            // 3. PSEUDO SELECTOR KONTROLÜ (:in-viewport)
            foreach ($pseudo_map as $pseudo => $target_file_name) {
                if (str_contains($content, $pseudo)) {
                    // Master index'te is-in-viewport'u ara
                    foreach ($this->master_index as $name => $path) {
                        if (str_contains($path, $target_file_name)) {
                            $url = $this->calculate_rel_url($current_folder, str_replace('\\', '/', $path));
                            $needed_imports[$url]['side_effect'] = true;
                            break;
                        }
                    }
                }
            }

            // 4. IMPORT SATIRLARINI YAZ
            $import_lines = [];
            foreach ($needed_imports as $url => $data) {
                // Önce yan etkileri (pluginler) yaz: import './plugins/plyr.js';
                if (!empty($data['side_effect'])) {
                    $import_lines[] = "import '{$url}';";
                }
                // Sonra fonksiyonları yaz: import { functionName } from './functions/name.js';
                if (!empty($data['named'])) {
                    $unique_funcs = array_unique($data['named']);
                    $import_lines[] = "import { " . implode(', ', $unique_funcs) . " } from '{$url}';";
                }
            }

            $new_content = preg_replace('/\/\* IMPORT_PLACEHOLDER_FOR_.* \*\//', implode("\n", $import_lines), $content);
            file_put_contents($full_path, $new_content);
        }
    }

    // YARDIMCI: Dosya içindeki yerel tanımları (Local Definitions) bulur
    private function get_local_definitions($content) {
        $locals = [];
        // function name(), class Name, var/let/const name
        if (preg_match_all('/(?:function\s+|class\s+|var\s+|let\s+|const\s+)([a-zA-Z0-9_]+)/', $content, $matches)) {
            $locals = array_merge($locals, $matches[1]);
        }
        // "export function name" gibi durumlar
        if (preg_match_all('/export\s+(?:function|class|const|let|var)\s+([a-zA-Z0-9_]+)/', $content, $matches)) {
            $locals = array_merge($locals, $matches[1]);
        }
        return array_unique($locals);
    }

    // YARDIMCI: Tireli dosya adlarını (html-to-image) camelCase'e çevirir (JS import kuralı)
    private function normalize_js_name($name) {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
    }

    // URL hesaplayıcı (Master Index yolunu ./ veya ../ formatına çevirir)
    private function calculate_rel_url($current_folder, $target_path) {
        $parts = explode('/', $target_path); // [0] => klasör (functions/plugins), [1] => dosya adı
        if ($parts[0] === $current_folder) {
            return "./{$parts[1]}";
        } else {
            return "../{$parts[0]}/{$parts[1]}";
        }
    }
}

add_action("init---", function(){
    $folders = [
        [
            "src" => SH_STATIC_PATH . 'js/production/functions',
            "folder" => "functions"
        ],
        [
            "src" => STATIC_PATH . 'js/plugins/*',
            "folder" => "plugins"
        ]
    ];
    $output = STATIC_PATH . 'js/modules';
    $processor = new JS_Batch_Modernizer($folders, $output);
    $processor->run();
});