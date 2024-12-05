<?php
namespace SaltHareket;

include_once "variables.php";
include_once "starter.php";

Class Theme{
	private static function copyIncludes()
    {
        $srcDir = __DIR__ . '/includes';
        $destDir = get_template_directory() . '/includes';

        // Eğer includes klasörü varsa, kopyalamaya başla
        if (is_dir($srcDir)) {
            self::recurseCopy($srcDir, $destDir);
            echo " Includes folder copied to theme root!";
        } else {
            echo " Includes folder not found!";
        }
    }
    private static function copyStatic()
    {
        $srcDir = __DIR__ . '/static';
        $destDir = get_template_directory() . '/static';

        // Eğer static klasörü varsa, kopyalamaya başla
        if (is_dir($srcDir)) {
            self::recurseCopy($srcDir, $destDir);
            echo " static folder copied to theme root!";
        } else {
            echo " static folder not found!";
        }
    }

    // Klasörleri ve dosyaları kopyalamak için recursive fonksiyon
    private static function recurseCopy($src, $dest)
    {
        // Kaynak klasörü var mı kontrol et
        $dir = opendir($src);
        @mkdir($dest);

        // Dosya/dizinleri kopyala
        while (($file = readdir($dir)) !== false) {
            if ($file == '.' || $file == '..') {
                continue;
            }

            $srcPath = $src . '/' . $file;
            $destPath = $dest . '/' . $file;

            if (is_dir($srcPath)) {
                // Eğer alt klasörse, rekürsif olarak kopyala
                self::recurseCopy($srcPath, $destPath);
            } else {
                // Dosya ise, kopyala
                copy($srcPath, $destPath);
            }
        }
        closedir($dir);
    }
	public static function init(){
		echo "pop";
		self::copyIncludes();
        self::copyStatic();
		//new Starter();
	}
}