<?php

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\ValueConverter;

class SCSSCompiler {

  private string $scss_dir;
  private string $css_dir;
  private string $cache;
  private array $compile_errors;
  private Compiler $scssc;
  private string $sourcemaps;

  public function __construct(string $scss_dir, string $css_dir, string $compile_method, string $sourcemaps) {

    $this->scss_dir = $scss_dir;
    $this->css_dir = $css_dir;
    $this->compile_errors = [];
    $this->scssc = new Compiler();

    $this->cache = STATIC_PATH . '/cache/';

    $this->scssc->setOutputStyle($compile_method);
    $this->scssc->setImportPaths($this->scss_dir);

    $this->sourcemaps = $sourcemaps;

    add_action('wp_loaded', [$this,'wp_scss_needs_compiling']);
  }

  public function get_scss_dir(): string {
    return $this->scss_dir;
  }

  public function get_css_dir(): string {
    return $this->css_dir;
  }

  public function get_compile_errors(): array {
    return $this->compile_errors;
  }

  public function compile(): void {

    $input_files = [];

    foreach(new DirectoryIterator($this->scss_dir) as $file) {
      if (substr($file, 0, 1) != "_" && pathinfo($file->getFilename(), PATHINFO_EXTENSION) == 'scss') {
        $input_files[] = $file->getFilename();
      }
    }

    foreach ($input_files as $scss_file) {
      $input = $this->scss_dir . $scss_file;
      $outputName = preg_replace("/\.[^$]*/", ".css", $scss_file);
      $output = $this->css_dir . $outputName;

      $this->compiler($input, $output);
    }

    if (count($this->compile_errors) < 1) {
      if  ( is_writable($this->css_dir) ) {
        foreach (new DirectoryIterator($this->cache) as $cache_file) {
          if ( pathinfo($cache_file->getFilename(), PATHINFO_EXTENSION) == 'css') {
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
    /*global $wpscss_settings;
    if (defined('WP_SCSS_ALWAYS_RECOMPILE') && WP_SCSS_ALWAYS_RECOMPILE || (isset($wpscss_settings['always_recompile']) ? $wpscss_settings['always_recompile'] === "1" : false)) {
      return true;
    }*/

    $latest_scss = 0;
    $latest_css = 0;

    foreach ( new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->scss_dir), RecursiveDirectoryIterator::SKIP_DOTS) as $sfile ) {
      if (pathinfo($sfile->getFilename(), PATHINFO_EXTENSION) == 'scss') {
        $file_time = $sfile->getMTime();

        if ( (int) $file_time > $latest_scss) {
          $latest_scss = $file_time;
        }
      }
    }

    foreach ( new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->css_dir), RecursiveDirectoryIterator::SKIP_DOTS) as $cfile ) {
      if (pathinfo($cfile->getFilename(), PATHINFO_EXTENSION) == 'css') {
        $file_time = $cfile->getMTime();

        if ( (int) $file_time > $latest_css) {
          $latest_css = $file_time;
        }
      }
    }

    if ($latest_scss > $latest_css) {
      return true;
    } else {
      return false;
    }
  }

  public function style_url_enqueued(string $url): mixed {
    global $wp_styles;
    foreach($wp_styles->queue as $wps_name){
      $wps = $wp_styles->registered[$wps_name];
      if($wps->src == $url){
        return $wps;
      }
    }
    return false;
  }

  /*public function enqueue_files(string $base_folder_path, string $css_folder): void {
    if($base_folder_path === wp_get_upload_dir()['basedir']){
      $enqueue_base_url = wp_get_upload_dir()['baseurl'];
    }
    else if($base_folder_path === WPSCSS_PLUGIN_DIR){
      $enqueue_base_url = plugins_url();
    }
    else if($base_folder_path === get_template_directory()){
      $enqueue_base_url = get_template_directory_uri();
    }
    else{
      $enqueue_base_url = get_stylesheet_directory_uri();
    }
    foreach( new DirectoryIterator($this->css_dir) as $stylesheet ) {
      if ( pathinfo($stylesheet->getFilename(), PATHINFO_EXTENSION) == 'css' ) {
        $name = $stylesheet->getBasename('.css') . '-style';
        $uri = $enqueue_base_url . $css_folder . $stylesheet->getFilename();
        $ver = $stylesheet->getMTime();

        wp_register_style(
          $name,
          $uri,
          array(),
          $ver,
          $media = 'all' );

        if(!$this->style_url_enqueued($uri)){
          wp_enqueue_style($name);
        }
      }
    }
  }*/

  public function set_variables(array $variables) {
    $this->scssc->addVariables(array_map('ScssPhp\ScssPhp\ValueConverter::parseValue', $variables));
  }

  function wp_scss_needs_compiling() {
      global $wpscss_compiler;
      $needs_compiling = apply_filters('wp_scss_needs_compiling', $wpscss_compiler->needs_compiling());
      if ( $needs_compiling ) {
        $this->wp_scss_compile();
        //wpscss_handle_errors();
      }
    }
    

    function wp_scss_compile() {
      global $wpscss_compiler;
      $variables = apply_filters('wp_scss_variables', array());
      foreach ($variables as $variable_key => $variable_value) {
        if (strlen(trim($variable_value)) == 0) {
          unset($variables[$variable_key]);
        }
      }
      $wpscss_compiler->set_variables($variables);
      $wpscss_compiler->compile();
    }
}