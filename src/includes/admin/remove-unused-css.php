<?php

$removeUnusedCss = new \Momentum81\PhpRemoveUnusedCss\RemoveUnusedCssBasic();
            $whitelist_file = get_template_directory() . '/theme/static/data/css_safelist.json';
            $whitelist = file_get_contents($whitelist_file);
            $whitelist = json_decode($whitelist, true);
            $whitelist = $whitelist["dynamicSafelist"];
            $whitelist_custom = [
                'fa-facebook',
                'fa-facebook-f',
                'fa-x-twitter',
                'fa-instagram',
                'fa-linkedin',
                'fa-threads',
                'plyr',
                '/^fa-file-/',
                '/^header-tools-/',
                '/^offcanvas-/',
                '/^btn-/',
                '/^z-/',
                '/^p-/',
                '/^px-/',
                '/^py-/',
                '/^pt-/',
                '/^pb-/',
                '/^ps-/',
                '/^pe-/',
                '/^m-/',
                '/^mx-/',
                '/^my-/',
                '/^mt-/',
                '/^mb-/',
                '/^ms-/',
                '/^me-/',
                '/^g-/',
                '/^gx-/',
                '/^gy-/',
                '/^d-/',
                '/^h-/',
                '/^mh-/',
                '/^min-/',
                '/^mw-/',
                '/^row-/',
                '/^col-/',
                '/^flex-/',
                '/^ratio-/',
                '/^font-/',
                '/^border-/',
                '/^swiper-/',
                '/^plyr/',
                '/^plyr-/',
                '/^plyr_/',
                '/^ug-/',
                '/^tease-/',
                '/^img-/',
                '/^bg-/',
                '/^aos-/',
                '/^data-aos/',
            ];
            $whitelist = array_merge($whitelist, $whitelist_custom);
            $whitelist = implode(",", $whitelist);
            $removeUnusedCss->whitelist($whitelist)
                ->styleSheets(
                    get_template_directory() . '/static/css/*.css',
                )
                ->htmlFiles(
                    get_template_directory() . '/templates/**/*.twig',
                    SH_PATH . '/templates/**/*.twig',
                    get_template_directory() . '/static/js/**/*.js',
                    get_template_directory() . '**/*.php',
                    WP_PLUGIN_DIR  . '**/*.php'
                )
                ->setFilenameSuffix('.refactored.min')
                ->minify()
                ->refactor()
                ->saveFiles();