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

    // Yeni kural bloğu (Uzantılar ve video formatları eklendi)
    $fix_block = $marker_start . "\n" .
"<IfModule mod_headers.c>
  <FilesMatch \"\.(js|css|woff|woff2|ttf|otf|eot|svg|jpg|jpeg|png|gif|webp|avif|ico|mp4|webm|ogv)$\">
    Header set Cache-Control \"public, max-age=31536000\"
  </FilesMatch>
  <FilesMatch \"\.(html|htm)$\">
    Header set Cache-Control \"no-store, no-cache, must-revalidate\"
    Header set Pragma \"no-cache\"
    Header set Expires \"0\"
  </FilesMatch>
</IfModule>\n" . 
    $marker_end;

    if (!file_exists($htaccess_path)) return;

    // Yedekleme özelliği korunuyor
    @copy($htaccess_path, $htaccess_path . '.bak');

    $contents = file_get_contents($htaccess_path);

    // 1. TEMİZLİK: Markerlar arasındaki HER ŞEYİ sil (İçeriğe bakma, sadece markerlara bak)
    // /s modifier satır sonlarını yakalar. /m çoklu satır için.
    $regex = "/" . preg_quote($marker_start, '/') . ".*?" . preg_quote($marker_end, '/') . "/s";
    $contents = preg_replace($regex, '', $contents);

    // 2. YERLEŞTİRME: WP Rocket bloğundan sonra ekle
    if (strpos($contents, '# END WP Rocket') !== false) {
        $contents = str_replace('# END WP Rocket', "# END WP Rocket\n\n" . $fix_block, $contents);
    } else {
        $contents = trim($contents) . "\n\n" . $fix_block;
    }

    // 3. BOŞLUK SÜPÜRGESİ: 3 ve daha fazla boş satırı 2'ye düşürür, birikmeyi engeller.
    $contents = preg_replace("/\n{3,}/", "\n\n", $contents);

    file_put_contents($htaccess_path, trim($contents) . "\n");
}