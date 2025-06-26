<?php
/**
 * Plugin Name: WP Rocket Htaccess Fixer (PRO)
 * Description: WP Rocket .htaccess dosyasına PSI uyumlu cache policy bloğunu otomatik ekler.
 * Author: Tolga Koçak
 */

add_action('after_rocket_clean_domain', 'salthub_fix_htaccess_policy', 20);

function salthub_fix_htaccess_policy() {
    $htaccess_path = ABSPATH . '.htaccess';
    $marker_start  = '# BEGIN PSI Cache Policy Fix';
    $marker_end    = '# END PSI Cache Policy Fix';

    $fix_block = <<<HTACCESS
$marker_start
<IfModule mod_headers.c>

  <FilesMatch "\\.(js|css|woff|woff2|ttf|otf|eot|svg|jpg|jpeg|png|gif|webp|avif|ico)$">
    Header set Cache-Control "public, max-age=31536000"
  </FilesMatch>

  <FilesMatch "\\.(html|htm)$">
    Header set Cache-Control "no-store, no-cache, must-revalidate"
    Header set Pragma "no-cache"
    Header set Expires "0"
  </FilesMatch>

</IfModule>
$marker_end
HTACCESS;

    if ( ! file_exists( $htaccess_path ) ) {
        error_log('[WPR FIX] .htaccess bulunamadı!');
        return;
    }

    // Yedek al
    @copy($htaccess_path, $htaccess_path . '.bak');

    $contents = file_get_contents($htaccess_path);

    // Mevcut blok varsa sil
    $contents = preg_replace("/$marker_start(.|\s)*?$marker_end/", '', $contents);

    // WP Rocket bloğundan hemen sonra ekle
    if (strpos($contents, '# END WP Rocket') !== false) {
        $contents = str_replace('# END WP Rocket', "# END WP Rocket\n\n" . $fix_block, $contents);
    } else {
        $contents .= "\n\n" . $fix_block;
    }

    file_put_contents($htaccess_path, $contents);
    error_log('[WPR FIX] PSI Cache Policy Fix başarıyla uygulandı.');
}
