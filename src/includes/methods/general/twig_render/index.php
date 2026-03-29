<?php
$context = Timber::context();
$context['data'] = $vars['data'] ?? [];
echo Timber::compile([$vars['template'] . '.twig'], $context);
wp_die();
