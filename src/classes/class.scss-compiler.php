<?php

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\ValueConverter;
//use ScssPhp\ScssPhp\OutputStyle; v.2.0.0

class SCSSCompiler {

    private array $scss_dirs; // Artık array olarak kabul ediliyor
    private string $css_dir;
    private string $cache;
    private array $compile_errors;
    private Compiler $scssc;
    private string $sourcemaps;

    //public function __construct(array $scss_dirs, string $css_dir, string $sourcemaps, OutputStyle $compile_method = OutputStyle::COMPRESSED) { v2.0.0
    public function __construct(array $scss_dirs, string $css_dir, string $sourcemaps, string $compile_method = "compressed") {
        $this->scss_dirs = $scss_dirs;
        $this->css_dir = $css_dir;
        $this->compile_errors = [];
        $this->scssc = new Compiler();

        $this->cache = STATIC_PATH . '/cache/';

        $this->scssc->setOutputStyle($compile_method);

        // Tüm dizinleri SCSSPHP'ye setImportPaths ile aktar
        $this->scssc->setImportPaths($this->scss_dirs);

        $this->sourcemaps = $sourcemaps;

        add_action('wp_loaded', [$this, 'wp_scss_needs_compiling']);
    }

    public function get_scss_dirs(): array {
        return $this->scss_dirs;
    }

    public function get_css_dir(): string {
        return $this->css_dir;
    }

    public function get_compile_errors(): array {
        return $this->compile_errors;
    }

    public function compile(): void {
        $input_files = [];

        // Tüm SCSS dizinlerini dolaş ve dosyaları topla
        foreach ($this->scss_dirs as $scss_dir) {
            foreach (new DirectoryIterator($scss_dir) as $file) {
                if (substr($file, 0, 1) != "_" && pathinfo($file->getFilename(), PATHINFO_EXTENSION) == 'scss') {
                    $input_files[] = $scss_dir . $file->getFilename();
                }
            }
        }

        foreach ($input_files as $input) {
            $outputName = preg_replace("/\.[^$]*/", ".css", basename($input));
            $output = $this->css_dir . $outputName;

            $this->compiler($input, $output);
        }

        if (count($this->compile_errors) < 1) {
            if (is_writable($this->css_dir)) {
                foreach (new DirectoryIterator($this->cache) as $cache_file) {
                    if (pathinfo($cache_file->getFilename(), PATHINFO_EXTENSION) == 'css') {
                        file_put_contents($this->css_dir . $cache_file, file_get_contents($this->cache . $cache_file));
                        unlink($this->cache . $cache_file->getFilename());
                    }
                }
            } else {
                $errors = [
                    'file' => 'CSS Directory',
                    'message' => "File Permissions Error, permission denied. Please make your CSS directory writable."
                ];
                $this->compile_errors[] = $errors;
            }
        }
    }

    private function compiler(string $in, string $out): void {
        if (!file_exists($this->cache)) {
            mkdir($this->cache, 0644);
        }
        if (is_writable($this->cache)) {
            try {
                $map = basename($out) . '.map';
                $this->scssc->setSourceMap(constant('ScssPhp\ScssPhp\Compiler::' . $this->sourcemaps));
                $this->scssc->setSourceMapOptions([
                    'sourceMapWriteTo' => $this->css_dir . $map,
                    'sourceMapURL' => $map,
                    'sourceMapBasepath' => rtrim(ABSPATH, '/'),
                    'sourceRoot' => home_url('/'),
                ]);

                $compilationResult = $this->scssc->compileString(file_get_contents($in), $in);
                $css = $compilationResult->getCss();

                file_put_contents($this->cache . basename($out), $css);
            } catch (Exception $e) {
                $errors = [
                    'file' => basename($in),
                    'message' => $e->getMessage(),
                ];
                $this->compile_errors[] = $errors;
            }
        } else {
            $errors = [
                'file' => $this->cache,
                'message' => "File Permission Error, permission denied. Please make the cache directory writable."
            ];
            $this->compile_errors[] = $errors;
        }
    }

    public function needs_compiling(): bool {
        $latest_scss = 0;
        $latest_css = 0;

        foreach ($this->scss_dirs as $scss_dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scss_dir), RecursiveDirectoryIterator::SKIP_DOTS) as $sfile) {
                if (pathinfo($sfile->getFilename(), PATHINFO_EXTENSION) == 'scss') {
                    $file_time = $sfile->getMTime();
                    if ((int) $file_time > $latest_scss) {
                        $latest_scss = $file_time;
                    }
                }
            }
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->css_dir), RecursiveDirectoryIterator::SKIP_DOTS) as $cfile) {
            if (pathinfo($cfile->getFilename(), PATHINFO_EXTENSION) == 'css') {
                $file_time = $cfile->getMTime();
                if ((int) $file_time > $latest_css) {
                    $latest_css = $file_time;
                }
            }
        }

        return $latest_scss > $latest_css;
    }

    public function set_variables(array $variables) {
        $this->scssc->addVariables(array_map('ScssPhp\ScssPhp\ValueConverter::parseValue', $variables));
    }

    public function wp_scss_needs_compiling() {
        global $wpscss_compiler;
        $needs_compiling = apply_filters('wp_scss_needs_compiling', $wpscss_compiler->needs_compiling());
        if ($needs_compiling) {
            $this->wp_scss_compile();
        }
    }

    public function wp_scss_compile() {
        global $wpscss_compiler;
        $variables = apply_filters('wp_scss_variables', []);
        foreach ($variables as $variable_key => $variable_value) {
            if (strlen(trim($variable_value)) == 0) {
                unset($variables[$variable_key]);
            }
        }
        $wpscss_compiler->set_variables($variables);
        $wpscss_compiler->compile();
    }
}
