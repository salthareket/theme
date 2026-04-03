<?php

use Peast\Peast;
use Peast\Renderer;
use Peast\Formatter\Compact;
use Peast\Formatter\PrettyPrint;

/**
 * JS_Batch_Modernizer
 * Converts global-scope JS files into ES Modules with automatic import resolution.
 *
 * - Scans source directories for functions, classes, variables
 * - Splits each into individual atom files with export
 * - Wraps plugin libraries (UMD, jQuery, global var) for module compatibility
 * - Resolves cross-file dependencies via 2-pass placeholder system
 * - Detects and prevents circular dependencies
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-04-03
 *     - Add: Initial versioned release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * $folders = [
 *     ["src" => "/path/to/functions", "folder" => "functions"],
 *     ["src" => "/path/to/plugins/*", "folder" => "plugins"],  // * = plugin mode
 * ];
 * $processor = new JS_Batch_Modernizer($folders, "/output/modules");
 * $processor->run();
 *
 * // Output: /output/modules/functions/myFunc.js, /output/modules/plugins/swiper.js, etc.
 * // Each file has proper import/export statements
 *
 * ──────────────────────────────────────────────────────────
 */
class JS_Batch_Modernizer {
    private $sources = [];
    private $output_dir;
    private $master_index = [];

    public function __construct(array $sources, $output) {
        $this->sources = $sources;
        $this->output_dir = rtrim($output, '/');
    }

    public function run() {
        // 1. Scan all sources → build master_index
        foreach ($this->sources as $config) {
            $this->scan_source_directory($config);
        }

        // 2. Split/wrap files with placeholders
        foreach ($this->sources as $config) {
            $is_plugin_mode = str_contains($config['src'], '*');
            $clean_src = rtrim($config['src'], '/*');
            $target_path = $this->output_dir . '/' . $config['folder'];

            if (!is_dir($target_path)) wp_mkdir_p($target_path);
            if (!is_dir($clean_src)) continue;

            $files = new DirectoryIterator($clean_src);
            foreach ($files as $file) {
                if ($file->isDot() || $file->getExtension() !== 'js') continue;
                $content = file_get_contents($file->getPathname());

                try {
                    if ($is_plugin_mode) {
                        $this->save_plugin_file_smart($content, $file->getFilename(), $target_path, $config['folder']);
                    } else {
                        $this->save_function_atoms_with_peast($content, $target_path, $config['folder']);
                    }
                } catch (\Exception $e) {
                    // Skip files that can't be parsed
                }
            }
        }

        // 3. Resolve all import placeholders
        $this->finalize_imports();

        // Debug: dump master index
        file_put_contents($this->output_dir . '/master_index_debug.json', json_encode($this->master_index, JSON_PRETTY_PRINT));
    }

    // ─── PHASE 1: SCANNING ───────────────────────────────

    private function scan_source_directory($config) {
        $clean_src = rtrim(str_replace('*', '', $config['src']), '/');
        if (!is_dir($clean_src)) return;

        $is_plugin_mode = ($config['folder'] === 'plugins');
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($clean_src));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'js') continue;
            $filename = $file->getFilename();

            // Plugin folder: only parse -init files, others are libraries
            if ($is_plugin_mode && !str_ends_with(str_replace('.js', '', $filename), '-init')) {
                $lib_name = str_replace('.js', '', $filename);
                $this->master_index[$lib_name] = $config['folder'] . '/' . $filename;
                continue;
            }

            try {
                $content = file_get_contents($file->getPathname());
                $ast = Peast::latest($content)->parse();

                foreach ($ast->getBody() as $node) {
                    $names = $this->extract_names_from_node($node);
                    foreach ($names as $name) {
                        $this->master_index[$name] = $is_plugin_mode
                            ? $config['folder'] . '/' . $filename
                            : $config['folder'] . '/' . $name . '.js';
                    }
                }
            } catch (\Exception $e) { continue; }
        }
    }

    private function extract_names_from_node($node) {
        $found = [];
        if ($node instanceof \Peast\Syntax\Node\FunctionDeclaration || $node instanceof \Peast\Syntax\Node\ClassDeclaration) {
            $id = $node->getId();
            if ($id) $found[] = $id->getName();
        } elseif ($node instanceof \Peast\Syntax\Node\VariableDeclaration) {
            foreach ($node->getDeclarations() as $decl) {
                $id = $decl->getId();
                if ($id) $found[] = $id->getName();
            }
        } elseif ($node instanceof \Peast\Syntax\Node\ExpressionStatement) {
            $expr = $node->getExpression();
            if ($expr instanceof \Peast\Syntax\Node\AssignmentExpression) {
                $left = $expr->getLeft();
                if ($left instanceof \Peast\Syntax\Node\Identifier) {
                    $found[] = $left->getName();
                }
            }
        }
        return $found;
    }

    // ─── PHASE 2: ATOM SPLITTING ─────────────────────────

    private function save_function_atoms_with_peast($content, $target_path, $current_folder) {
        try {
            $ast = Peast::latest($content)->parse();
            $renderer = new Renderer();
            $renderer->setFormatter(new Compact());

            foreach ($ast->getBody() as $node) {
                $name = null;
                $code = null;

                if ($node instanceof \Peast\Syntax\Node\FunctionDeclaration || $node instanceof \Peast\Syntax\Node\ClassDeclaration) {
                    $name = $node->getId()->getName();
                    $exportNode = new \Peast\Syntax\Node\ExportNamedDeclaration();
                    $exportNode->setDeclaration($node);
                    $code = $renderer->render($exportNode);
                } elseif ($node instanceof \Peast\Syntax\Node\VariableDeclaration) {
                    foreach ($node->getDeclarations() as $decl) {
                        $name = $decl->getId()->getName();
                        $exportNode = new \Peast\Syntax\Node\ExportNamedDeclaration();
                        $exportNode->setDeclaration($node);
                        $code = $renderer->render($exportNode);
                    }
                } elseif ($node instanceof \Peast\Syntax\Node\ExpressionStatement &&
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

    // ─── PHASE 2: PLUGIN WRAPPING ────────────────────────

    private function save_plugin_file_smart($content, $filename, $target_path, $current_folder) {
        $clean_name = str_replace('.js', '', $filename);
        $placeholder = "/* IMPORT_PLACEHOLDER_FOR_{$clean_name} */\n";
        $is_init_file = str_ends_with($clean_name, '-init');

        if ($is_init_file) {
            $final = $this->process_init_file($content);
        } else {
            $final = $this->process_library_file($content, $filename);
        }

        file_put_contents($target_path . '/' . $filename, $placeholder . $final);
    }

    private function process_init_file($content) {
        try {
            $ast = Peast::latest($content)->parse();
            $newBody = [];
            $exportedNames = [];

            foreach ($ast->getBody() as $node) {
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
            $renderer = new Renderer();
            $renderer->setFormatter(new Compact());
            $final = $renderer->render($ast);

            if (!empty($exportedNames)) {
                $assigns = implode(' ', array_map(fn($n) =>
                    "if(typeof window.{$n}==='undefined') window.{$n}={$n};", $exportedNames));
                $final .= "\nif(typeof window!=='undefined'){ {$assigns} }";
            }

            return $final;
        } catch (\Exception $e) {
            return $content;
        }
    }

    private function process_library_file($content, $filename) {
        $type = $this->detect_library_type($content);
        $detectedName = $this->detect_global_name($content);

        $header = "var global=window;var self=window;\nvar jQuery=window.jQuery||window.$||{};var $=jQuery;\n";
        $final = $header;

        switch ($type) {
            case 'UMD_LIBRARY':
                $final .= "\nif(typeof window.jQuery==='undefined'){window.jQuery=window.$||{};}\n";
                $final .= "(function(define,exports,module,require){\nvar self=window;var global=window;\n";
                $final .= $content;
                $final .= "\n}).call(window,undefined,undefined,undefined,undefined);\n";
                break;

            case 'GLOBAL_VAR':
                $final .= "\n" . $content . "\n";
                if ($detectedName) {
                    $final .= "if(typeof {$detectedName}!=='undefined') window.{$detectedName}={$detectedName};";
                }
                break;

            default: // STANDARD, JQUERY_PLUGIN
                $final .= "\n(function(jQuery,$){\n" . $content . "\n}).call(window,window.jQuery,window.jQuery);";
                break;
        }

        $final .= $detectedName
            ? "\nexport default (window.{$detectedName}||window.jQuery);"
            : "\nexport default window.jQuery;";

        return $final;
    }

    private function detect_library_type($content) {
        if (str_contains($content, 'define.amd') || str_contains($content, 'module.exports')) return 'UMD_LIBRARY';
        $clean = preg_replace('!/\*.*?\*/!s', '', substr($content, 0, 5000));
        if (preg_match('/(?:^|\s|;)(?:var|const|let|class)\s+([a-zA-Z0-9_]+)\s*(?:=|\{)/', $clean, $m)) {
            $skip = ['e','t','n','r','i','o','s','a','window','document','jQuery','$','_'];
            if (!in_array($m[1], $skip)) return 'GLOBAL_VAR';
        }
        if (str_contains($content, '$.fn.') || str_contains($content, 'jQuery.fn.')) return 'JQUERY_PLUGIN';
        return 'STANDARD';
    }

    private function detect_global_name($content) {
        $clean = preg_replace('!/\*.*?\*/!s', '', substr($content, 0, 5000));
        if (preg_match('/(?:^|\s|;)(?:var|const|let|class)\s+([a-zA-Z0-9_]+)\s*(?:=|\{)/', $clean, $m)) {
            $skip = ['e','t','n','r','i','o','s','a','window','document','jQuery','$','_'];
            return in_array($m[1], $skip) ? '' : $m[1];
        }
        return '';
    }

    // ─── PHASE 3: IMPORT RESOLUTION ──────────────────────

    private function finalize_imports() {
        if (!is_dir($this->output_dir)) return;

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->output_dir));
        $pseudo_map = [':in-viewport' => 'is-in-viewport'];

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'js') continue;

            $full_path = $file->getPathname();
            $content = file_get_contents($full_path);

            if (!preg_match('/\/\* IMPORT_PLACEHOLDER_FOR_(.*) \*\//', $content, $match)) continue;

            $current_item_name = trim($match[1]);
            $rel_path = str_replace([$this->output_dir . DIRECTORY_SEPARATOR, $this->output_dir . '/'], '', $full_path);
            $normalized_rel_path = str_replace('\\', '/', $rel_path);
            $current_folder = explode('/', $normalized_rel_path)[0];

            $local_defs = $this->get_local_definitions($content);
            $local_defs[] = $current_item_name;

            $needed_imports = [];

            // Master index scan
            foreach ($this->master_index as $item_name => $item_path) {
                $item_path = str_replace('\\', '/', $item_path);
                if ($item_path === $normalized_rel_path || in_array($item_name, $local_defs)) continue;

                // Circular dependency prevention for classes
                if (!empty($current_item_name) && ctype_upper($current_item_name[0])) {
                    $target_full_path = $this->output_dir . '/' . $item_path;
                    if (file_exists($target_full_path)) {
                        $target_content = file_get_contents($target_full_path);
                        if (str_contains($target_content, "new " . $current_item_name)) continue;
                    }
                }

                if (preg_match('/\b' . preg_quote($item_name, '/') . '\b/', $content)) {
                    $url = $this->calculate_rel_url($current_folder, $item_path);
                    $is_plugin = str_contains($item_path, 'plugins/');

                    if ($is_plugin) {
                        $needed_imports[$url]['side_effect'] = true;
                    } else {
                        $needed_imports[$url]['named'][] = $item_name;
                    }
                }
            }

            // Pseudo selector imports
            foreach ($pseudo_map as $pseudo => $target_file_name) {
                if (str_contains($content, $pseudo)) {
                    foreach ($this->master_index as $name => $path) {
                        if (str_contains($path, $target_file_name)) {
                            $url = $this->calculate_rel_url($current_folder, str_replace('\\', '/', $path));
                            $needed_imports[$url]['side_effect'] = true;
                            break;
                        }
                    }
                }
            }

            // Build import lines
            $import_lines = [];
            foreach ($needed_imports as $url => $data) {
                if (!empty($data['side_effect'])) {
                    $import_lines[] = "import '{$url}';";
                }
                if (!empty($data['named'])) {
                    $unique_funcs = array_unique($data['named']);
                    $import_lines[] = "import { " . implode(', ', $unique_funcs) . " } from '{$url}';";
                }
            }

            $new_content = preg_replace(
                '/\/\* IMPORT_PLACEHOLDER_FOR_.* \*\//',
                implode("\n", $import_lines),
                $content
            );
            file_put_contents($full_path, $new_content);
        }
    }

    // ─── HELPERS ─────────────────────────────────────────

    private function get_local_definitions($content) {
        $locals = [];
        if (preg_match_all('/(?:function\s+|class\s+|var\s+|let\s+|const\s+)([a-zA-Z0-9_]+)/', $content, $m)) {
            $locals = array_merge($locals, $m[1]);
        }
        if (preg_match_all('/export\s+(?:function|class|const|let|var)\s+([a-zA-Z0-9_]+)/', $content, $m)) {
            $locals = array_merge($locals, $m[1]);
        }
        return array_unique($locals);
    }

    private function calculate_rel_url($current_folder, $target_path) {
        $parts = explode('/', $target_path);
        return ($parts[0] === $current_folder) ? "./{$parts[1]}" : "../{$parts[0]}/{$parts[1]}";
    }
}

// ─── TEST HOOK (disabled — change "init---" to "init" to test) ───
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
