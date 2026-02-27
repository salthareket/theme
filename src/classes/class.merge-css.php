<?php

namespace SaltHareket\Theme;

use Sabberworm\CSS\Parser as CSSParser;
use Sabberworm\CSS\Settings as CSSSettings;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\CSSList\AtRuleBlockList;
use MatthiasMullie\Minify\CSS as Minifier;
use Irmmr\RTLCss\Parser as RTLParser;

class MergeCSS {
    private $files;
    private $output_path;
    private $minify;
    private $rtl;
    private $mRules = [];
    private $mMedias = [];

    public function __construct(array $files, string $output = "", bool $minify = false, bool $rtl = false) {
        $this->files = $files;
        $this->output_path = $output;
        $this->minify = $minify;
        $this->rtl = $rtl;
    }

    public function run() {
        $combined_raw_css = "";

        foreach ($this->files as $file) {
            if (file_exists($file)) {
                $combined_raw_css .= file_get_contents($file) . "\n";
            }
        }

        if (empty(trim($combined_raw_css))) return "";

        try {
            $oSettings = CSSSettings::create()->withMultibyteSupport(true);
            $oParser = new CSSParser($combined_raw_css, $oSettings);
            $oDoc = $oParser->parse();

            foreach ($oDoc->getContents() as $oContent) {
                if ($oContent instanceof DeclarationBlock) {
                    $this->processBlockToStorage($oContent, $this->mRules);
                } elseif ($oContent instanceof AtRuleBlockList) {
                    $queryName = "@" . $oContent->atRuleName() . " " . $oContent->atRuleArgs();
                    if (!isset($this->mMedias[$queryName])) $this->mMedias[$queryName] = [];
                    foreach ($oContent->getContents() as $oInnerContent) {
                        if ($oInnerContent instanceof DeclarationBlock) {
                            $this->processBlockToStorage($oInnerContent, $this->mMedias[$queryName]);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            //error_log("MergeCSS Parser Hatası: " . $e->getMessage());
            return "/* CSS Parser Error */";
        }

        // 1. Birleşmiş (Deduplicated) LTR CSS'i oluştur (Ham hali)
        $finalCSS = $this->buildFinalCSS();

        // 2. Çıktı Yönetimi
        if (!empty($this->output_path)) {
            $dir = dirname($this->output_path);
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            // --- LTR DOSYASI ---
            $ltrOutput = $finalCSS;
            if ($this->minify) {
                $minifier = new Minifier();
                $minifier->add($ltrOutput);
                $ltrOutput = $minifier->minify();
            }
            file_put_contents($this->output_path, $ltrOutput);

            // --- RTL DOSYASI ---
            if ($this->rtl) {
                try {
                    // RTL için tekrar parse et (Nesne üzerinden işlem yapmalıyız)
                    $rtl_parser = new CSSParser($finalCSS, $oSettings);
                    $rtl_tree = $rtl_parser->parse();
                    
                    $rtl_css_processor = new RTLParser($rtl_tree);
                    $rtl_css_processor->flip();
                    $rtl_output = $rtl_tree->render(); // Sabberworm çıktısı (Minify değil)

                    // RTL çıktısını da minify et
                    if ($this->minify) {
                        $rtl_minifier = new Minifier();
                        $rtl_minifier->add($rtl_output);
                        $rtl_output = $rtl_minifier->minify();
                    }

                    $rtl_path = str_replace('.css', '-rtl.css', $this->output_path);
                    file_put_contents($rtl_path, $rtl_output);
                } catch (\Exception $e) {
                    //error_log("RTL Dönüştürme Hatası: " . $e->getMessage());
                }
            }

            return $this->output_path;
        }

        // Eğer dosya yolu verilmediyse ve sadece string isteniyorsa
        if ($this->minify) {
            $minifier = new Minifier();
            $minifier->add($finalCSS);
            $finalCSS = $minifier->minify();
        }

        return $finalCSS;
    }

    private function processBlockToStorage($oBlock, &$storage) {
        $selectors = $oBlock->getSelectors();
        $rules = $oBlock->getRules();
        foreach ($selectors as $oSelector) {
            $sSelector = trim($oSelector->getSelector());
            if (!isset($storage[$sSelector])) $storage[$sSelector] = [];
            foreach ($rules as $oRule) {
                $propName = $oRule->getRule();
                $value = $oRule->getValue();
                $storage[$sSelector][$propName] = (string)$value . ($oRule->getIsImportant() ? ' !important' : '');
            }
        }
    }

    private function buildFinalCSS() {
        $css = "/* Merged by MergeCSS Class */\n";
        foreach ($this->mRules as $selector => $props) {
            $css .= "$selector {\n";
            foreach ($props as $name => $val) { $css .= "  $name: $val;\n"; }
            $css .= "}\n";
        }
        foreach ($this->mMedias as $query => $selectors) {
            $css .= "\n$query {\n";
            foreach ($selectors as $selector => $props) {
                $css .= "  $selector {\n";
                foreach ($props as $name => $val) { $css .= "    $name: $val;\n"; }
                $css .= "  }\n";
            }
            $css .= "}\n";
        }
        return $css;
    }
}