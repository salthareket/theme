<?php

add_filter( 'timber/acf-gutenberg-blocks-templates', function () {
    return ['vendor/salthareket/theme/src/templates/blocks', 'theme/templates/blocks', 'templates/blocks'];
});

add_filter( 'timber/acf-gutenberg-blocks-data', function( $context ){
    if ( array_key_exists('fields', $context) && is_array($context['fields']) ) {
        //error_log("------------------------------- timber/acf-gutenberg-blocks-data");
        //error_log(print_r($context['fields'], true));
        if (isset($context['fields']['block_settings']['custom_id'])) {
            $custom_id = $context['fields']['block_settings']['custom_id'];
            //error_log("custom_id:".$custom_id);
            // Eğer ID boşsa (yeni blok) VEYA 'block_' ile başlayan bir ID gelmişse (kopyalanmışsa), yeni ID üret.
            //if (empty($custom_id) || strpos($custom_id, 'block_') === 0) {
                $context['fields']['block_settings']['custom_id'] = 'block_' . md5(uniqid('', true));
            //}
        }
        $upload_dir = wp_upload_dir();
        $context['fields']['upload_url'] = $upload_dir['baseurl'];
    }
    return $context;
});

add_filter( 'timber/acf-gutenberg-blocks-default-data', function( $data ){
    /*$data['default'] = array(
        'post_type' => 'post',
    );
    $data['pages'] = array(
        'post_type' => 'page',
    );*/
    return $data;
});

add_filter( 'timber/acf-gutenberg-blocks-example-identifier', function( $sufix ){
    return $sufix;
});

add_filter( 'timber/acf-gutenberg-blocks-preview-identifier', function( $sufix ){
    return $sufix;
});






/**
 * Tek bir galeri öğesinin (resim/video) HTML'ini oluşturur.
 *
 * @param array $imageData Mevcut resim/video verileri.
 * @param string $ratioClass Bootstrap 'ratio ratio-X' sınıfı.
 * @param string $extraClass Medya öğesi için ek CSS sınıfları.
 * @param string $videoViewType Video görünüm modu ('image' için poster, 'video' için oynatıcı).
 * @param bool $lightbox LightGallery entegrasyonu aktif mi.
 * @return string Oluşturulan HTML.
 */
function _generate_gallery_item_html(array $imageData, string $ratioClass, string $extraClass, string $videoViewType, bool $lightbox): string {
    $mediaHtml = '';
    $mediaClass = 'img-fluid object-fit-cover object-position-center ' . $extraClass;
    
    // 1. Medya İçeriğini Oluşturma
    if ($imageData['type'] === 'image' && ($imageData['img-src'] ?? false)) {
        // Resim içeriği
        $args = [
            "src" => $imageData["id"] ?? null,
            "class" => $mediaClass,
            "preview" => is_admin(),
            "attrs" => [],
        ];
        $mediaHtml = get_image_set($args);
        
    } elseif (in_array($imageData['type'], ["file", "embed"])) {
        // Video/Dosya içeriği
        if ($videoViewType === "video") {
            // Tam video oynatıcı (get_video() çağrılır)
            $videoArgs = [
                "video_type" => $imageData["type"],
                "video_settings" => [
                    "videoBg" => 1, "autoplay" => 0, "loop" => 0, "muted" => 0, "videoReact" => 1,
                    "controls" => 1, "controls_options" => ["play-large"], "controls_options_settings" => [],
                    "controls_hide" => 0, "ratio" => "", "custom_video_image" => "",
                    "video_image" => $imageData["poster"] ?? null, "vtt" => ""
                ],
            ];
            $videoDataKey = ($imageData["type"] == "file") ? "video_file" : "video_url";
            $videoArgs[$videoDataKey] = ($imageData["type"] == "file") 
                ? ["desktop" => $imageData["src"] ?? null] 
                : $imageData["src"] ?? null;
            
            $mediaHtml = get_video([
                "src" => $videoArgs, "class" => $extraClass, "init" => true, "lazy" => true, "attrs" => []
            ]);
            
        } else {
            // Poster resmi (Lightbox etkinse veya video_view_type 'image' ise)
            $args = [
                "src" => $imageData["poster"] ?? null,
                "class" => $mediaClass,
                "preview" => is_admin(),
                "attrs" => []
            ];
            $mediaHtml = get_image_set($args);
        }
    }

    if (empty($mediaHtml)) {
        return ''; // Gerekli veri yoksa hiçbir şey döndürme.
    }

    // 2. Kapsayıcı Etiketleri ve Öznitelikleri Oluşturma
    $tag = $lightbox ? 'a' : 'div';
    $attrs = ' class="col ' . ($lightbox ? 'gallery-item' : '') . '"';
    $href = '';
    $dataAttrs = '';

    if ($lightbox) {
        if ($imageData['type'] === 'image') {
            $href = ' href="' . esc_url($imageData['img-src'] ?? '#') . '"';
        } elseif ($imageData['type'] !== 'image' && $videoViewType === 'image') {
            // Video/File için Lightbox data öznitelikleri
            $href = ' href="#"';
            $dataAttrs .= ' data-lg-size="' . esc_attr($imageData['lg-size'] ?? '') . '"';
            $dataAttrs .= ' data-src="' . esc_attr($imageData['src'] ?? '') . '"';
            $dataAttrs .= ' data-poster="' . esc_attr($imageData['poster'] ?? '') . '"';
            $dataAttrs .= ' data-sub-html="' . esc_attr($imageData['sub-html'] ?? '') . '"';
        }
    }
    
    // 3. Nihai HTML çıktısı
    return sprintf(
        '<%1$s%2$s%3$s%4$s><div class="%5$s">%6$s</div></%1$s>',
        $tag, // %1$s: a veya div
        $href, // %2$s: href özniteliği
        $dataAttrs, // %3$s: data öznitelikleri
        $attrs, // %4$s: class özniteliği
        $ratioClass, // %5$s: ratio class
        $mediaHtml // %6$s: Medya içeriği
    );
}
/**
 * Rastgele sütun sayıları ve en boy oranları ile Bootstrap ızgara galerisi oluşturur.
 */
function block_gallery_pattern_random(
    array $images, 
    int $maxCol, 
    string $breakpoint, 
    array $ratios = ["4x3"], 
    int $gap = 3, 
    string $class = "", 
    ?string $video_view_type = "image", 
    bool $lightbox = false
): string {

    if (empty($video_view_type)) {
        $video_view_type = "image";
    }

    $htmlOutput = '';
    $index = 0;
    $totalImages = count($images);
    
    // Girdi sadeleştirme ve değişken tanımlama
    $ratios = empty($ratios) ? ["4x3"] : $ratios;
    $numRatios = count($ratios);
    $gapClass = sprintf('gx-%d gy-%d mb-%d', $gap, $gap, $gap);

    while ($index < $totalImages) {
        $remaining = $totalImages - $index;
        
        // Sütun ve Oranları Rastgele Seç
        $randomCol = min(rand(1, $maxCol), $remaining);
        $randomRatioIndex = rand(0, $numRatios - 1);
        $ratioClass = 'ratio ratio-' . $ratios[$randomRatioIndex];

        // 1. Satır Kapsayıcıyı Oluştur
        $rowColsClass = ($randomCol === 1) 
            ? '' 
            : sprintf(' row-cols-%s-%d row-cols-1', $breakpoint, $randomCol);
            
        $htmlOutput .= sprintf('<div class="row %s %s">', $rowColsClass, $gapClass);

        // 2. Sütunları Oluştur
        for ($i = 0; $i < $randomCol; $i++) {
            $imageData = $images[$index];
            
            // Yardımcı fonksiyon ile tek bir satırda HTML üret
            $htmlOutput .= _generate_gallery_item_html(
                $imageData,
                $ratioClass,
                $class,
                $video_view_type,
                $lightbox
            );

            $index++;
        }

        $htmlOutput .= '</div>';
    }

    return $htmlOutput;
}

/**
 * Önceden tanımlanmış desenlere göre Bootstrap ızgara galerisi oluşturur.
 */
function block_gallery_pattern(
    array $images, 
    ?array $patterns, 
    int $gap = 3, 
    string $class = "", 
    bool $loop = false, 
    ?string $video_view_type = "image", 
    bool $lightbox = false
): string {

    if (empty($video_view_type)) {
        $video_view_type = "image";
    }

    if (empty($patterns)) {
        return '';
    }

    $htmlOutput = '';
    $index = 0;
    $totalImages = count($images);
    $gapClass = sprintf('gx-%d gy-%d mb-%d', $gap, $gap, $gap);

    // Döngü sayısını hesapla
    $repeat = 1;
    if ($loop) {
        // PHP 7.4+ okunaklı ve tek satırda toplam sütun sayısını bulma
        $totalColumnsInPatternSet = array_sum(array_column($patterns, 'columns'));
        if ($totalColumnsInPatternSet > 0) {
            $repeat = (int) ceil($totalImages / $totalColumnsInPatternSet);
        }
    }

    // Ana Döngü (Desenin Tekrarı)
    for ($z = 1; $z <= $repeat; $z++) {
        
        // Desenleri Tek Tek Uygula
        foreach ($patterns as $pattern) {
            
            // Eğer tüm resimler işlenmişse, tüm döngülerden çık
            if ($index >= $totalImages) {
                break 2;
            }
            
            // Desen parametrelerini güvenli bir şekilde al
            $col        = (int) ($pattern["columns"] ?? 1);
            $ratio      = $pattern["ratio"] ?? "4x3";
            $breakpoint = $pattern["breakpoint"] ?? "md";
            $ratioClass = 'ratio ratio-' . $ratio;

            // 1. Satır Kapsayıcıyı Oluştur
            $rowColsClass = ($col === 1) 
                ? '' 
                : sprintf(' row-cols-%s-%d row-cols-1', $breakpoint, $col);
                
            $htmlOutput .= sprintf('<div class="row %s %s">', $rowColsClass, $gapClass);

            // 2. Sütunları Oluştur
            for ($i = 0; $i < $col; $i++) {
                if ($index >= $totalImages) {
                    break; // Sütun döngüsünden çık
                }

                $imageData = $images[$index];
                
                // Yardımcı fonksiyon ile tek bir satırda HTML üret
                $htmlOutput .= _generate_gallery_item_html(
                    $imageData,
                    $ratioClass,
                    $class,
                    $video_view_type,
                    $lightbox
                );

                $index++;
            }

            $htmlOutput .= '</div>';
        }
    }

    return $htmlOutput;
}



/*
function block_gallery_pattern_random($images, $maxCol, $breakpoint, $ratios=["4x3"], $gap=3, $class="", $video_view_type= "image", $lightbox = false) {

    $htmlOutput = '';
    $index = 0;
    $totalImages = count($images);

    if(!$ratios){
        $ratios = ["4x3"];
    }

    while ($index < $totalImages) {
        // Random number between 1 and maxCol, ensuring it doesn't exceed remaining images
        $randomCol = min(rand(1, $maxCol), $totalImages - $index);
        $randomRatio = min(rand(0, count($ratios)-1), count($ratios));
        $ratioClass = "ratio ratio-".$ratios[$randomRatio];
        $gapClass = 'gx-' . $gap . ' gy-' . $gap . ' mb-'.$gap;

        // Check if the row will contain only one image
        if ($randomCol === 1) {
            $htmlOutput .= '<div class="row ' . $gapClass . '">';
        } else {
            $htmlOutput .= '<div class="row row-cols-' . $breakpoint . '-' . $randomCol . ' row-cols-1 ' . $gapClass . '">';
        }

        for ($i = 0; $i < $randomCol; $i++) {
            switch($images[$index]["type"]){
                case "image":
                    if(isset($images[$index]["img-src"])){

                        $args = [
                            "src" => $images[$index]["id"], 
                            "class" => 'img-fluid object-fit-cover object-position-center '.$class,
                            "preview" => is_admin(),
                            "attrs" => [],
                        ];
                        if($lightbox){
                            $htmlOutput .= '<a href="'.$images[$index]["img-src"].'" class="col gallery-item">';
                        }else{
                            $htmlOutput .= '<div class="col">';
                        }
                        $htmlOutput .= '<div class="' . $ratioClass .'">'. get_image_set($args).'</div>';
                        if($lightbox){
                            $htmlOutput .= '</a>';
                        }else{
                            $htmlOutput .= '</div>';
                        }
                        $index++;                
                    }
                break;
                case "file" :
                case "embed" :
                
                    if($lightbox && $video_view_type == "image"){
                        $htmlOutput .= '<a href="#" data-lg-size="'.$images[$index]["lg-size"].'" data-src="'.$images[$index]["src"].'" data-poster="'.$images[$index]["poster"].'" data-sub-html="'.$images[$index]["sub-html"].'" class="col gallery-item">';
                    }else{
                        $htmlOutput .= '<div class="col">';
                    }

                    $htmlOutput .= '<div class="' . $ratioClass .'">';
                    if($video_view_type == "video"){
                        $args = array(
                            "video_type" => $images[$index]["type"],
                            "video_settings" => array(
                                "videoBg" => 1,
                                "autoplay" => 0,
                                "loop" => 0,
                                "muted" => 0,
                                "videoReact" => 1,
                                "controls" => 1,
                                "controls_options" => array(
                                     "play-large"
                                ),
                                "controls_options_settings" => array(),
                                "controls_hide" => 0,
                                "ratio" => "",
                                "custom_video_image" => "",
                                "video_image" => $images[$index]["poster"],
                                "vtt" => ""
                            )
                        );
                        if($images[$index]["type"] == "file"){
                            $args["video_file"] = array(
                                "desktop" => $images[$index]["src"]
                            );
                        }else{
                            $args["video_url"] = $images[$index]["src"];
                        }
                        $htmlOutput .= get_video([
                            "src" => $args,
                            "class" => $class,
                            "init" => true,
                            "lazy" => true,
                            "attrs" => []
                        ]);                        
                    }else{
                        $args = [
                            "src" => $images[$index]["poster"], 
                            "class" => 'img-fluid object-fit-cover object-position-center '.$class,
                            "preview" => is_admin(),
                            "attrs" => []
                        ];
                        $htmlOutput .= get_image_set($args);
                    }
                    $htmlOutput .= '</div>';
                    if($lightbox && $video_view_type == "image"){
                        $htmlOutput .= '</a>';
                    }else{
                        $htmlOutput .= '</div>';
                    }
                    $index++; 
                break;
            }
        }

        $htmlOutput .= '</div>';
    }

    return $htmlOutput;
}
function block_gallery_pattern($images, $patterns, $gap=3, $class="", $loop = false, $video_view_type= "image", $lightbox = false) {
    if(!$patterns){
        return;
    }

    $htmlOutput = '';
    $index = 0;
    $totalImages = count($images);

    $repeat = 1;
    if($loop){
        $totalColumns = array_sum(array_map(fn($item) => $item['columns'], $patterns));
        $repeat = ceil($totalImages / $totalColumns); 
    }

    for ($z = 1; $z <= $repeat; $z++) {
        foreach($patterns as $key => $pattern){
            $col = $pattern["columns"];
            $ratio = $pattern["ratio"];
            $breakpoint = $pattern["breakpoint"];
            $ratioClass = "ratio ratio-".$ratio;
            $gapClass = 'gx-' . $gap . ' gy-' . $gap . ' mb-'.$gap;

            // Check if the row will contain only one image
            if ($col === 1) {
                $htmlOutput .= '<div class="row ' . $gapClass . '">';
            } else {
                $htmlOutput .= '<div class="row row-cols-' . $breakpoint . '-' . $col . ' row-cols-1 ' . $gapClass . '">';
            }

            for ($i = 0; $i < $col; $i++) {
                if ($index >= $totalImages) {
                    $htmlOutput .= '</div>';
                    break 2; // İç ve dış for döngüsünden çık!
                }
                switch($images[$index]["type"]){
                    case "image":
                        if(isset($images[$index]["img-src"])){
                            $args = [
                                "src" => $images[$index]["id"], 
                                "class" => 'img-fluid object-fit-cover object-position-center '.$class,
                                "preview" => is_admin(),
                                "attrs" => []
                            ];
                            if($lightbox){
                                $htmlOutput .= '<a href="'.$images[$index]["img-src"].'" class="col gallery-item">';
                            }else{
                                $htmlOutput .= '<div class="col">';
                            }
                            $htmlOutput .= '<div class="' . $ratioClass .'">'. get_image_set($args).'</div>';
                            if($lightbox){
                                $htmlOutput .= '</a>';
                            }else{
                                $htmlOutput .= '</div>';
                            }
                            $index++;                
                        }
                    break;
                    case "file" :
                    case "embed" :
                        
                        if($lightbox && $video_view_type == "image"){
                            $htmlOutput .= '<a href="#" data-lg-size="'.$images[$index]["lg-size"].'" data-src="'.$images[$index]["src"].'" data-poster="'.$images[$index]["poster"].'" data-sub-html="'.$images[$index]["sub-html"].'" class="col gallery-item">';
                        }else{
                            $htmlOutput .= '<div class="col">';
                        }
                        $htmlOutput .= '<div class="' . $ratioClass .'">';
                            if($video_view_type == "video"){
                                $args = array(
                                    "video_type" => $images[$index]["type"],
                                    "video_settings" => array(
                                        "videoBg" => 1,
                                        "autoplay" => 0,
                                        "loop" => 0,
                                        "muted" => 0,
                                        "videoReact" => 1,
                                        "controls" => 1,
                                        "controls_options" => array(
                                             "play-large"
                                        ),
                                        "controls_options_settings" => array(),
                                        "controls_hide" => 0,
                                        "ratio" => "",
                                        "custom_video_image" => "",
                                        "video_image" => $images[$index]["poster"],
                                        "vtt" => ""
                                    )
                                );
                                if($images[$index]["type"] == "file"){
                                    $args["video_file"] = array(
                                        "desktop" => $images[$index]["src"]
                                    );
                                }else{
                                    $args["video_url"] = $images[$index]["src"];
                                }
                                $htmlOutput .= get_video([
                                    "src" => $args,
                                    "class" => $class,
                                    "init" => true,
                                    "lazy" => true,
                                    "attrs" => []
                                ]);
                            }else{
                                $args = [
                                    "src" => $images[$index]["poster"], 
                                    "class" => 'img-fluid object-fit-cover object-position-center '.$class,
                                    "preview" => is_admin(),
                                    "attrs" => []
                                ];
                                $htmlOutput .= get_image_set($args);
                            }
                            $htmlOutput .= '</div>';
                        if($lightbox && $video_view_type == "image"){
                            $htmlOutput .= '</a>';
                        }else{
                            $htmlOutput .= '</div>';
                        }
                        $index++; 
                    break;
                }
            }
            $htmlOutput .= '</div>';
        }
    }
    return $htmlOutput;
}
*/


function block_responsive_classes($field=[], $type="", $block_column=""){
    $sizes = array_reverse(array_keys($GLOBALS["breakpoints"]));//array("xxxl", "xxl","xl","lg","md","sm","xs");
    $tempClasses = [];
    $lastAlign = null;

    if((!empty($block_column) && $block_column["block"] != "bootstrap-columns")){ //&& empty($type)){
        $type = "align-items-";
    }

    foreach ($field as $key => $align) {
        if (!empty($align)) {
            if ($lastAlign !== null && $lastAlign !== $align) {
                $tempClasses[] = [
                    "key" => $prevKey === "xs" ? "" : $prevKey, // Son geçerli key
                    "align" => $lastAlign,
                ];
            }
            $lastAlign = $align;
            $prevKey = $key;
        }
    }
    if ($lastAlign !== null) {
        $tempClasses[] = [
            "key" => $prevKey === "xs" ? "" : $prevKey,
            "align" => $lastAlign,
        ];
    }

    // Class'ları oluştur
    $classes = [];
    foreach ($tempClasses as $index => $entry) {
        $prefix = $entry["key"] ? $entry["key"] . "-" : "";
        $classes[] = $type . $prefix . $entry["align"];
    }
    return $classes;
}
function block_responsive_column_classes($field = [], $type = "col-", $field_name = "") {
    $tempClasses = [];
    $lastValue = null;

    //$field = remove_empty_items($field);

    foreach ($field as $key => $value) {

        if ($value) {
            // Eğer $field_name doluysa, içindeki değeri kullan
            if (!empty($field_name) && isset($value[$field_name])) {
                $value = $value[$field_name];
            }

            if ($value !== $lastValue) {
                $col = ($key === "xs") ? "" : $key . "-";
                $tempClasses[] = $type . $col . $value;
                $lastValue = $value; // Son değeri güncelle
                $lastKey = $key;     // Son breakpoint güncelle
            } else {
                // Aynı değerde olanları sonuncuya göre düzenliyoruz
                $tempClasses[count($tempClasses) - 1] = $type . (($key === "xs") ? "" : $key . "-") . $value;
            }
        }
    }

    return $tempClasses; // Sınıfları birleştir ve döndür
}

function block_container_class($container="", $add_padding = true){
    $padding = $add_padding?"px-4 px-lg-3":"";
    $default = QueryCache::get_cached_option("default_container");//get_field("default_container", "options");
    $default = $default=="no"?"":"container".(empty($default)?"":"-".$default) . " {padding}";
    switch($container){
        case "" :
            $container = "container {padding}";
        break;
        case "default" :
            $container = $default;
        break;
        case "no" :
            $container = "";
        break;
        case "auto" :
            $container = "w-auto";
        break;
        default :
            $container = "container-".$container . " {padding}";
        break;
    }
    $container = trim(str_replace("{padding}", $padding, $container));
    return $container;
}

function block_container($container="", $stretch_height = false){
    $container = block_container_class($container);
    if(!empty($container) && $stretch_height){
        $container .= " h-inherit";
    }
    return $container;
}
function block_title_size($size=""){
    $default = get_option("options_default_title_size");
    $default = empty($default) ? "title-fluid" : $default;
    return ($size == "default" || empty($size) ? $default : $size);
}
function block_text_size($size=""){
    $default = get_option("options_default_text_size");
    $default = empty($default) ? "text-fluid" : $default;
    return ($size == "default" || empty($size) ? $default : $size);
}
function block_ratio($ratio=""){
    $default = get_option("options_default_ratio");
    $default = empty($default) ? "16x9" : $default;
    return ($ratio == "default" || empty($ratio) ? $default : $ratio);
}
function block_ratio_padding($ratio=""){
    $ratio = block_ratio($ratio);
    $ratio_val = explode("x", $ratio);
    $ratio_val[0] = $ratio_val[0] == 185 ? 1.85 : $ratio_val[0];
    $ratio_val[0] = $ratio_val[0] == 235 ? 2.35 : $ratio_val[0];
    return (number_format(($ratio_val[1]/$ratio_val[0]), 2) * 100);
}
function block_align($align, $block_column = [], $slide=false){
    $classes = [];
    if(!empty($block_column) || $slide){
        $direction_hr = "align-items-";
        $direction_vr = "justify-content-";
    }else{
        $direction_hr = "justify-content-";
        $direction_vr = "align-items-";
    }
    if(isset($align["hr"])){
        if($align["hr"] == "responsive"){
            $classes = array_merge($classes, block_responsive_classes($align["hr_responsive"], $direction_hr, $block_column));
        }else{
            $classes[] = $direction_hr.$align["hr"];
        }        
    }
    if(isset($align["vr"])){
        if($align["vr"] != "none"){
            if($align["vr"] == "responsive"){
                $classes = array_merge($classes, block_responsive_classes($align["vr_responsive"], $direction_vr, $block_column));
            }else{
                $classes[] = $direction_vr.$align["vr"];
            }
        }
    }
    if(isset($align["text"])){
        if($align["text"] == "responsive"){
            $classes = array_merge($classes, block_responsive_classes($align["text_responsive"], "text-", ""));
        }else{
            $classes[] = "text-".$align["text"];
        }
    }
    $classes = implode(" ", $classes);
    return $classes;
}
function block_classes($block, $fields, $block_column){
    $sizes = array_reverse(array_keys($GLOBALS["breakpoints"]));//array("xxxl", "xxl","xl","lg","md","sm","xs");
    $classes = [];

    $position = isset($fields["block_settings"]["position"]["position"]) ? $fields["block_settings"]["position"]["position"] : "relative";

    $classes[] = "position-".$position;

    if(isset($fields["block_settings"]["hero"])){
        if($fields["block_settings"]["hero"]){
            $classes[] = "block--hero";
            if($fields["block_settings"]["pt_header"]){
                $classes[] = "pt-header";
            }
        }
    }
    if($block_column){
        $classes[] = "block-".$block_column["block"];
        if($block_column["block"] != "archive"){
            $classes[] = "flex-column";
        }
        //$classes[] = "position-relative";
        if(isset($fields["block_settings"]["sticky_top"]) && $fields["block_settings"]["sticky_top"]){
            if($fields["block_settings"]["align"]["vr"] != "center" && $fields["block_settings"]["align"]["vr"] != "responsive"){
                $classes[] = "sticky-top";
            }
        }
    }else{
        $classes[] = "block-".sanitize_title($block["title"]);
        //$classes[] = "444 position-relative";
    }
    
    if(isset($fields["class"])){
        $classes[] = $fields["class"];
    }
    if(isset($block["className"])){
        if($block_column){
            if(str_replace("acf/", "", $block["name"]) == $block_column["block"]){
                $classes[] = $block["className"];
            }
        }else{
            $classes[] = $block["className"];
        }
    }

    /*if(isset($fields["block_settings"]["stretch_height"])){
        if($fields["block_settings"]["stretch_height"]){
            if($block_column){
                $classes[] = "h-100";
            }else{
                $classes[] = "flex-column";
            }
        }
    }*/

    /*if($block["align"]){
        $classes[] = "text-".$block["align"];
    }*/
    if(isset($fields["block_settings"])){
        $classes[] = block_spacing($fields["block_settings"]);
        $classes[] = "d-flex";
        if(empty(block_container($fields["block_settings"]["container"]))){
            $classes[] = "flex-column-justify-content-center";
        }

        $classes[] = block_align($fields["block_settings"]["align"], $block_column);

        /*if(isset($fields["block_settings"]["horizontal_align"]) && $fields["block_settings"]["horizontal_align"]){
            $classes = array_merge($classes, block_responsive_classes($fields["block_settings"]["horizontal_align"], "justify-content-", $block_column));
        }
        if($fields["block_settings"]["vertical_align"] != "none"){
            if(!empty($block_column)){
                $classes[] = "justify-content-".$fields["block_settings"]["vertical_align"];
            }else{
                $classes[] = "align-items-".$fields["block_settings"]["vertical_align"];
            }
        }
        if(isset($fields["block_settings"]["text_align"]) && $fields["block_settings"]["text_align"]){
            $classes = array_merge($classes, block_responsive_classes($fields["block_settings"]["text_align"], "text-", ""));
        }*/

        if ($fields["block_settings"]["height"] == "100%"){
            $classes[] = "h-100";
        }

        $classes[] = block_visibility($block, $fields, $block_column);

    }

    if(is_admin()){
        if(isset($block["lock"]) && $block["lock"]){
            $classes[] = "acf-block-locked";
        }
    }
    
    $classes = implode(" ", $classes);
    return $classes;
}
function block_attrs($block, $fields, $block_column){
    $attrs = [];

    $slider_autoheight = false;
    if(isset($fields["slider_settings"])){
        if(isset($fields["slider_settings"])){
            if($fields["slider_settings"]["autoheight"]){
                $slider_autoheight = true;
            }
        }
    }

    if(!empty($block["anchor"])){
        $attrs["id"] = $block["anchor"];
    }else{
        $attrs["id"] = $block["id"];
    }
    $attrs["data-index"] = isset($block["index"])?$block["index"]:0;

    if(isset($fields["block_settings"]["block_parallax"]) && !empty($fields["block_settings"]["block_parallax"]["active"])){
        $attrs["data-scroll"] = "";
        $scroll_speed = $fields["block_settings"]["block_parallax"]["scroll_speed"];
        if($scroll_speed > 0){
            $attrs["data-scroll-speed"] = $scroll_speed;
        }
        $attrs["data-scroll-position"] = $fields["block_settings"]["block_parallax"]["scroll_position"];
        $attrs["data-scroll-repeat"] = "";
        if($fields["block_settings"]["block_parallax"]["scroll_ignore_fold"]){
            $attrs["data-scroll-ignore-fold"] = "";
        }
        if($fields["block_settings"]["block_parallax"]["scroll_enable_touch_speed"]){
            $attrs["data-enable-touch-speed"] = "";
        }
        $attrs["data-scroll-direction"] = $fields["block_settings"]["block_parallax"]["scroll_direction"];
        if($fields["block_settings"]["block_parallax"]["scroll_progress"] != "none"){
            $attrs["data-scroll-event-property"] = $fields["block_settings"]["block_parallax"]["scroll_progress"];
            if($scroll_speed > 0){
                $attrs["data-scroll-css-progress"] = "";
            }else{
                $attrs["data-scroll-event-progress"] = "progressEvent";
            }
        }
    }

    $attrs = array2Attrs($attrs);
    return $attrs;
}


function block_visibility_v1($block, $fields, $block_column = null) {

    $visibility_field = $fields["block_settings"]["visibility"];

    if (empty($visibility_field) || !is_array($visibility_field)) return '';

    $display = $visibility_field['display'] ?? 'block';
    if ($display === 'none' && !is_admin()) return 'd-none';

    $display = $fields["block_settings"]["hero"] ? 'flex' : $display;

    $values = $visibility_field['visibility'] ?? [];
    if (empty($values)) return "d-{$display}";
    if(!is_array($values)) return "";

    $added_auto_or_none = false;

    // Mobile-first sıralama
    $breakpoints = array_reverse(array_keys($GLOBALS["breakpoints"]));

    $classes = [];

    foreach ($breakpoints as $breakpoint) {
        if (isset($values[$breakpoint])) {
            $value = $values[$breakpoint]?$display:"none";
            if ($value === 'none') {
                if (!$added_auto_or_none || $breakpoint === 'xs' || $breakpoint === 'sm') {
                    // 'xs' ve 'sm' gibi küçük breakpointler için auto/none değerlerini yine ekleyelim
                    $classes[] = "d-{$breakpoint}-{$value}";
                    $added_auto_or_none = true;
                }
            } else {
                // İlk dolu breakpoint için prefix kaldır
                if ($breakpoint === 'xs') {
                    $classes[] = "d-{$value}";
                } else {
                    $classes[] = "d-{$breakpoint}-{$value}";
                }
            }
        }
    }

    return implode(' ', $classes);
}


function block_visibility($block, $fields, $block_column = null) {

    $visibility_field = $fields["block_settings"]["visibility"] ?? [];

    if (empty($visibility_field) || !is_array($visibility_field)) return '';

    // Varsayılan görünüm değeri (e.g., 'block' veya 'flex')
    $display = $visibility_field['display'] ?? 'block'; 
    
    // Eğer tüm blok gizlenmişse ve admin değilsek, hemen d-none döndür.
    if ($display === 'none' && !is_admin()) return 'd-none';

    // 'hero' alanı varsa görünümü 'flex' olarak zorla (Mevcut mantık korunuyor)
    $display = $fields["block_settings"]["hero"] ? 'flex' : $display;

    $values = $visibility_field['visibility'] ?? [];
    
    // Eğer breakpoint ayarları boşsa, varsayılan görünüm sınıfını döndür.
    if (empty($values)) return "d-{$display}";
    if(!is_array($values)) return "";

    $classes = [];
    
    // Breakpoint listesi (küçükten büyüğe sıralanmış: xs, sm, md, lg, xl, xxl, xxxl)
    // Bu, Mobile-First sadelestirme (consolidation) mantığı için gereklidir.
    $breakpoints = array_keys($GLOBALS["breakpoints"]); 
    
    $prev_value = null; // Bir önceki breakpoint'in değerini tutar (none/block/flex)

    foreach ($breakpoints as $breakpoint) {
        
        // Breakpoint'in değeri (true/false) ayarlanmış mı?
        $is_visible = isset($values[$breakpoint]) ? (bool) $values[$breakpoint] : null;

        if ($is_visible !== null) {
            
            // Eğer true ise $display (block/flex), false ise 'none' olarak ata
            $current_value = $is_visible ? $display : "none";

            // 1. Kural: Değer bir önceki (daha küçük) breakpoint'teki değerden farklıysa class ekle.
            if ($current_value !== $prev_value) {
                
                // Admin'de gizleme sınıfı üretme (içerik editörde kaybolmasın)
                if (is_admin() && $current_value === 'none') {
                    // Gizleme sınıfını yok say, varsayılan görünürlük devam etsin.
                } else {
                    // 2. Kural: 'xs' (mobil) için prefix kullanma.
                    if ($breakpoint === 'xs') {
                        // d-block, d-none, d-flex gibi prefix'siz sınıf
                        $classes[] = "d-{$current_value}"; 
                    } else {
                        // Diğer breakpoint'ler için d-{breakpoint}-{value} formatını kullan.
                        $classes[] = "d-{$breakpoint}-{$current_value}";
                    }
                }
            }
            
            // Mevcut değeri bir sonraki döngü için kaydet.
            $prev_value = $current_value;
        }
        // Eğer breakpoint değeri ayarlanmamışsa, önceki değeri koruyarak devam et.
    }

    return implode(' ', $classes);
}






function block_spacing($settings) {
    $margin = $settings['margin'] ?? [];
    $padding = $settings['padding'] ?? [];

    $margin_classes = generate_spacing_classes($margin, 'm');
    $padding_classes = generate_spacing_classes($padding, 'p');

    $merged_classes = array_merge($margin_classes, $padding_classes);
    $combined_string = implode(' ', $merged_classes);

    return $combined_string;
}
function generate_spacing_classes($spacing, $prefix) {
    $classes = [];
    $directions = ['top' => 't', 'bottom' => 'b', 'left' => 's', 'right' => 'e'];
    $default = QueryCache::get_cached_option("default_" . ($prefix == "m" ? "margin" : "padding"));//get_field("default_" . ($prefix == "m" ? "margin" : "padding"), "options");

    // 'default' değerlerini doldur
    foreach ($spacing as $key => $item) {
        if ($item == "default") {
            $spacing[$key] = $default[$key];
        }
    }

    // Breakpoint listesi (büyükten küçüğe sıralanmış)
    $breakpoints = array_reverse(array_keys($GLOBALS["breakpoints"]));

    foreach ($directions as $key => $short) {
        if (isset($spacing[$key])) {
            if ($spacing[$key] == 'responsive' && isset($spacing[$key . '_responsive'])) {
                $added_auto_or_none = false; // Auto veya none eklenip eklenmediğini kontrol
                foreach ($breakpoints as $breakpoint) {
                    if (isset($spacing[$key . '_responsive'][$breakpoint])) {
                        $value = $spacing[$key . '_responsive'][$breakpoint];
                        if ($value === 'auto' || $value === 'none') {
                            if (!$added_auto_or_none || $breakpoint === 'xs' || $breakpoint === 'sm') {
                                // 'xs' ve 'sm' gibi küçük breakpointler için auto/none değerlerini yine ekleyelim
                                $classes[] = "{$prefix}{$short}-{$breakpoint}-{$value}";
                                $added_auto_or_none = true;
                            }
                        } else {
                            // İlk dolu breakpoint için prefix kaldır
                            if ($breakpoint === 'xs') {
                                $classes[] = "{$prefix}{$short}-{$value}";
                            } else {
                                $classes[] = "{$prefix}{$short}-{$breakpoint}-{$value}";
                            }
                        }
                    }
                }
            } elseif ($spacing[$key] !== 'default' && !empty($spacing[$key])) {
                $classes[] = "{$prefix}{$short}-{$spacing[$key]}";
            }
        }
    }

    // Yatay ve dikey eksenleri birleştir
    $combined_axes = [
        'y' => ['t', 'b'],
        'x' => ['s', 'e']
    ];

    foreach ($combined_axes as $axis => $sides) {
        $axis_classes = [];
        foreach ($classes as $key => $class) {
            foreach ($sides as $side) {
                if (strpos($class, "{$prefix}{$side}-") === 0) {
                    $axis_classes[] = $class;
                    unset($classes[$key]);
                }
            }
        }

        $grouped = [];
        foreach ($axis_classes as $class) {
            preg_match("/{$prefix}([se|tb])-((\w+-)?\w+)$/", $class, $matches);
            if (!empty($matches)) {
                $grouped[$matches[2]][] = $matches[1];
            }
        }

        foreach ($grouped as $value => $directions) {
            if (count($directions) === 2) {
                $classes[] = "{$prefix}{$axis}-{$value}";
            } else {
                foreach ($directions as $direction) {
                    $classes[] = "{$prefix}{$direction}-{$value}";
                }
            }
        }
    }

    return $classes;
}


function generate_breakpoint_css($classname, $breakpoints, $styles="") {
    $breakpoint_sizes = [
        "xs" => "0px", 
        "sm" => "576px", 
        "md" => "768px", 
        "lg" => "992px", 
        "xl" => "1200px", 
        "xxl" => "1400px", 
        "xxxl" => "1600px"
    ];

    if (empty($breakpoints)) return "";

    // Breakpoint'leri sıralı hale getir
    $selected = array_values(array_intersect(array_keys($breakpoint_sizes), (array)$breakpoints));

    if (empty($selected)) return "";

    $css = [];
    $start = $selected[0]; 
    $end = $start; 
    
    for ($i = 1; $i < count($selected); $i++) {
        $current = $selected[$i];
        $prev = $selected[$i - 1];

        // Ardışık olup olmadığını kontrol et
        if (array_search($current, array_keys($breakpoint_sizes)) === array_search($prev, array_keys($breakpoint_sizes)) + 1) {
            $end = $current; // Eğer ardışık ise bitiş noktasını güncelle
        } else {
            // Yeni bir grup başlat
            $css[] = generate_media_query($classname, $start, $end, $breakpoint_sizes, $styles);
            $start = $current;
            $end = $current;
        }
    }

    // Son grubu da ekle
    $css[] = generate_media_query($classname, $start, $end, $breakpoint_sizes, $styles);

    return implode("\n", array_filter($css));
}
function generate_media_query($classname, $start, $end, $breakpoint_sizes, $styles = "") {
    $min = $breakpoint_sizes[$start];

    // Eğer `end` son breakpoint değilse, max-width hesapla
    $breakpoint_keys = array_keys($breakpoint_sizes);
    $next_index = array_search($end, $breakpoint_keys) + 1;
    $max = isset($breakpoint_keys[$next_index]) 
        ? " and (max-width: calc(" . $breakpoint_sizes[$breakpoint_keys[$next_index]] . " - 1px))" 
        : "";

    if(empty($styles)){
      $styles = "display: none !important;";
    }
    return "@media (min-width: $min)$max { $classname { $styles } }";
}


function block_slider_controls($id = "", $controls = [], $direction = "horizontal", $autoheight = false, $continuous_scroll = false){
    $attrs = [];
    $tools = [];
    
    foreach($controls as $control){
        $css = [];
        $js = "";
        $type = $control["acf_fc_layout"];
        $placement = $control["placement"];
        $position_x = isset($control["position"]["x"])?$control["position"]["x"]:"center";
        $position_y = isset($control["position"]["y"])?$control["position"]["y"]:"center";

        $autoheight = $autoheight && $direction == "vertical" ? false : $autoheight;

        $space_prefix = "";
        $space_obj = $id." .swiper";
        if($placement == "outside"){
            $space_prefix = $autoheight?"padding-":"";
            $space_obj = $autoheight?$id." .card>.card-body":$space_obj;
        }

        switch($type){
            case "navigation" :
                
                $attrs[$type] = true;

                $size = unit_value($control["view"]["size"]);
                $sides_offset = unit_value($control["view"]["x"]);
                $top_offset = unit_value($control["view"]["y"]);

                $color = $control["view"]["color"];
                $color_dark = $control["view"]["color_dark"];

                if($position_x == "center"){
                    $top_offset = unit_value($control["view"]["y"]);
                    $sides_offset = "50%";
                }
                if($position_y == "center"){
                    $top_offset = "50%";
                    $sides_offset = unit_value($control["view"]["x"]);
                }
                if($position_x == "center" && $position_y == "center"){
                    $top_offset = "50%";
                    $sides_offset = unit_value($control["view"]["x"]);
                }

                $rotate = $direction == "vertical" && $control["view"]["rotate"] ? true : false;

                $css_top = "var(--swiper-navigation-top-offset)";
                $css_left = "var(--swiper-navigation-sides-offset)";
                $css_right = "var(--swiper-navigation-sides-offset)";
                $css_bottom = "var(--swiper-navigation-top-offset)";

                $css_size = "var(--swiper-navigation-size)";
                if($rotate){
                    if($position_y == "start" || $position_y == "end"){
                        $css_top = "calc(var(--swiper-navigation-top-offset) + calc(var(--swiper-navigation-width) - var(--swiper-navigation-size)) / 2)";
                    }
                    if($position_x == "start" || $position_x == "end"){
                        $css_left = "calc(var(--swiper-navigation-sides-offset) + calc(var(--swiper-navigation-size) - var(--swiper-navigation-width)) / 2)";
                    }
                    $css_size = "var(--swiper-navigation-width)";
                }

                $btn_prev = $id." .swiper-button-prev";
                $btn_next = $id." .swiper-button-next";

                if($control["hide"]){
                    $css[] = generate_breakpoint_css($id." .swiper-button-prev, ".$id." .swiper-button-next", $control["hide"]);
                    if($placement == "outside"){
                        $css[] = generate_breakpoint_css($space_obj, $control["hide"], "padding:0!important;top:0!important;bottom:0!important;left:0!important;right:0!important;");
                    }
                }
                
                switch($position_x."-".$position_y){
                        
                        //sol ust
                        case "start-start" :
                            if($direction == "vertical" && $rotate){

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."top: calc( calc(".$css_top." * 2) + calc(".$css_size." * 2));" .
                                        "height: auto;" .
                                    "}";
                                }
                           
                                $css[] = $btn_prev."{" .
                                        "top: ". $css_top .";" .
                                        "bottom: auto;" .
                                        "left: ".$css_left.";" .
                                        "right: auto;" .
                                        "margin-top:0;" .
                                "}";
                                $css[] = $btn_next."{" .
                                        "top: calc(".$css_top." + ".$css_size.");" .
                                        "bottom: auto;" .
                                        "left: ".$css_left.";" .
                                        "right: auto;" .
                                        "margin-top:0;" .
                                "}";                                    

                            }else{

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."top: calc( calc(".$css_top." * 2) + var(--swiper-navigation-size));" .
                                        "height: auto;" .
                                    "}";                                   
                                }
                                $css[] = $btn_prev."{" .
                                    "top: ".$css_top.";" .
                                    "bottom: auto;" .
                                    "left: ".$css_left.";" .
                                    "right: auto;" .
                                    "margin-top:0;" .
                                "}";
                                $css[] = $btn_next."{" .
                                    "top: ".$css_top.";" .
                                    "bottom: auto;" .
                                    "left: calc( ".$css_left." + var(--swiper-navigation-width));" .
                                    "right: auto;" .
                                    "margin-top:0;" .
                                "}";

                            }
                        break;

                        // sol orta
                        case "start-center" :
                            if($direction == "vertical" && $rotate){
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."left: calc( calc(".$css_left." * 2) + var(--swiper-navigation-width) );" .
                                        "width: auto;" .
                                    "}";                                   
                                }
                                $css[] = $btn_prev."{" .
                                    "top: ".$css_top.";" .
                                    "bottom: auto;" .
                                    "left: var(--swiper-navigation-sides-offset);" .
                                    "right:auto;" .
                                "}";
                                $css[] = $btn_next."{" .
                                    "top: calc(".$css_top." + ".$css_size.");" .
                                    "bottom: auto;" .
                                    "left: var(--swiper-navigation-sides-offset);" .
                                    "right:auto;" .
                                "}";
                            }else{
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."left: calc( calc(".$css_left." * 2) + calc(var(--swiper-navigation-width) * 2) );" .
                                        "width: auto;" .
                                    "}";                                 
                                }
                                $css[] = $btn_prev."{" .
                                    "top: ".$css_top.";" .
                                    "bottom: auto;" .
                                    "left: ".$css_left.";" .
                                    "right: auto;" .
                                "}";
                                $css[] = $btn_next."{" .
                                    "top: ".$css_top.";" .
                                    "bottom: auto;" .
                                    "left: calc( ".$css_left." + var(--swiper-navigation-width));" .
                                    "right:auto);" .
                                "}";
                            }
                        break;

                        //sol alt
                        case "start-end" :
                            if($direction == "vertical" && $rotate){
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."left: calc( calc(".$css_left." * 2) + var(--swiper-navigation-width) );" .
                                        "width: auto;" .
                                    "}";                                   
                                }
                                $css[] = $btn_prev."{" .
                                        "top: auto;" .
                                        "bottom: calc(".$css_top." + ".$css_size.");" .
                                        "left: ".$css_left.";" .
                                        "right: auto;" .
                                        "margin-top:0;" .
                                "}";
                                $css[] = $btn_next."{" .
                                        "top: auto;" .
                                        "bottom: ". $css_top .";" .
                                        "left: ".$css_left.";" .
                                        "right: auto;" .
                                        "margin-top:0;" .
                                "}";
                            }else{
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."left: calc( calc(".$css_left." * 2) + calc(var(--swiper-navigation-width) * 2) );" .
                                        "width: auto;" .
                                    "}";                                 
                                }
                                $css[] = $btn_prev."{" .
                                    "top: auto;" .
                                    "bottom: ".$css_top.";" .
                                    "left: ".$css_left.";" .
                                    "right: auto;" .
                                "}";
                                $css[] = $btn_next."{" .
                                    "top: auto;" .
                                    "bottom: ".$css_top.";" .
                                    "left: calc( ".$css_left." + var(--swiper-navigation-width));" .
                                    "right:auto);" .
                                "}";
                            }
                        break;

                        

                        //orta üst
                        case "center-start" : //ok
                            if($direction == "vertical" && $rotate){
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."top: calc( calc(".$css_top." * 2) + calc(".$css_size." * 2));" .
                                        "height: auto;" .
                                    "}";                                        
                                }
                                $css[] = $btn_prev."{" .
                                    "top: ". $css_top .";" .
                                    "bottom: auto;" .
                                    "left: ". $css_left .";" .
                                    "right:auto;" .
                                    "margin-top:0;" .
                                "}";
                                $css[] = $btn_next."{" .
                                    "top: calc(".$css_top." + ".$css_size.");" .
                                    "bottom: auto;" .
                                    "left: ".$css_left.";" .
                                    "right:auto;" .
                                    "margin-top:0;" .
                                "}";
                            }else{
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."top: calc( calc(".$css_top." * 2) + ".$css_size.");" .
                                        "height: auto;" .
                                    "}";                             
                                }
                                $css[] = $btn_prev."{" .
                                    "top: ". $css_top .";" .
                                    "bottom: auto;" .
                                    "left: calc(".$css_left." - var(--swiper-navigation-width));" .
                                    "right:auto;" .
                                    "margin-top:0;" .
                                "}";
                                $css[] = $btn_next."{" .
                                    "top: ". $css_top .";" .
                                    "bottom: auto;" .
                                    "left: calc( ".$css_left." + 0px );" .
                                    "right:auto;" .
                                    "margin-top:0;" .
                                "}";
                            }
                        break;

                        //orta
                        case "center-center" : //ok
                            if($direction == "vertical" && $rotate){

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."top: calc( calc(".$css_left." * 2) + var(--swiper-navigation-width) );" .
                                        $spce_prefix."bottom: calc( calc(".$css_left." * 2) + var(--swiper-navigation-width) );" .
                                            "height: auto;" .
                                    "}"; 
                                }
                                $css[] = $btn_prev."{" .
                                        "top: calc(".$css_left." + calc(var(--swiper-navigation-width) - var(--swiper-navigation-size)) / 2);" .
                                        "bottom: auto;" .
                                        "left: ".$css_top.";" .
                                        "right: auto;" .
                                        "margin-top: 0;" .
                                "}";
                                $css[] = $btn_next."{" .
                                        "top: auto;" .
                                        "bottom: calc(".$css_left." + calc(var(--swiper-navigation-width) - var(--swiper-navigation-size)) / 2);" .
                                        "left: ". $css_top .";" .
                                        "right: auto;" .
                                        "margin-top: 0;" .
                                "}";

                            }else{
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."left: calc( calc(".$css_left." * 2) + var(--swiper-navigation-width) );" .
                                        $space_prefix."right: calc( calc(".$css_left." * 2) + var(--swiper-navigation-width) );" .
                                        "width: auto;" .
                                    "}";                                   
                                }
                                $css[] = $btn_prev."{" .
                                    "top: ". $css_top .";" .
                                    "bottom: auto;" .
                                    "left: ".$css_left.";" .
                                    "right: auto;" .
                                "}";
                                $css[] = $btn_next."{" .
                                    "top: ". $css_top .";" .
                                    "bottom: auto;" .
                                    "left: auto;" .
                                    "right: ".$css_right.";" .
                                "}";
                            }
                        break;

                        //orta alt
                        case "center-end" : 
                            if($direction == "vertical" && $rotate){

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."bottom: calc( calc(".$css_top." * 2) + calc(".$css_size." * 2));" .
                                        "height: auto;" .
                                    "}";                             
                                }
                                $css[] = $btn_prev."{" .
                                        "top: auto;" .
                                        "bottom: calc(".$css_top." + ".$css_size.");" .
                                        "left: ". $css_left .";" .
                                        "right: auto;" .
                                        "margin-top:0;" .
                                "}";
                                $css[] = $btn_next."{" .
                                        "top: auto;" .
                                        "bottom: ". $css_top .";" .
                                        "left: ".$css_left.";" .
                                        "right: auto;" .
                                        "margin-top:0;" .
                                "}";                                    

                            }else{
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."bottom: calc( calc(".$css_top." * 2) + ".$css_size.");" .
                                        "height: auto;" .
                                    "}";                             
                                }
                                $css[] = $btn_prev."{" .
                                    "top: auto;" .
                                    "bottom: ". $css_top .";" .
                                    "left: calc(".$css_left." - var(--swiper-navigation-width));" .
                                    "right:auto;" .
                                    "margin-top:0;" .
                                "}";
                                $css[] = $btn_next."{" .
                                    "top: auto;" .
                                    "bottom: ". $css_top .";" .
                                    "left: calc( ".$css_left." + 0px );" .
                                    "right:auto;" .
                                    "margin-top:0;" .
                                "}";
                            }
                        break;


  
                        //sag ust
                        case "end-start" :
                            if($direction == "vertical" && $rotate){

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."top: calc( calc(".$css_top." * 2) + calc(".$css_size." * 2));" .
                                        "height: auto;" .
                                    "}";
                                }
                           
                                $css[] = $btn_prev."{" .
                                        "top: ". $css_top .";" .
                                        "bottom: auto;" .
                                        "left: auto;" .
                                        "right: ".$css_right.";" .
                                        "margin-top: 0;" .
                                "}";
                                $css[] = $btn_next."{" .
                                        "top: calc(".$css_top." + ".$css_size.");" .
                                        "bottom: auto;" .
                                        "left: auto;" .
                                        "right: ".$css_right.";" .
                                        "margin-top:0;" .
                                "}";                                    

                            }else{

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."top: calc( calc(".$css_top." * 2) + var(--swiper-navigation-size));" .
                                        "height: auto;" .
                                    "}";                                   
                                }
                                $css[] = $btn_prev."{" .
                                    "top: ".$css_top.";" .
                                    "bottom: auto;" .
                                    "left: auto;" .
                                    "right: calc( ".$css_right." + var(--swiper-navigation-width));" .
                                    "margin-top:0;" .
                                "}";
                                $css[] = $btn_next."{" .
                                    "top: ".$css_top.";" .
                                    "bottom: auto;" .
                                    "left: auto;" .
                                    "right: ".$css_right.";" .
                                    "margin-top:0;" .
                                "}";

                            }
                        break;

                        //sag orta
                        case "end-center" :
                            if($direction == "vertical" && $rotate){
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."right: calc( calc(".$css_right." * 2) + var(--swiper-navigation-width) );" .
                                        "width: auto;" .
                                    "}";                                   
                                }
                                $css[] = $btn_prev."{" .
                                    "top: ".$css_top.";" .
                                    "bottom: auto;" .
                                    "left: auto;" .
                                    "right:var(--swiper-navigation-sides-offset);" .
                                    "margin-top: 0;" .
                                "}";
                                $css[] = $btn_next."{" .
                                    "top: calc(".$css_top." + ".$css_size.");" .
                                    "bottom: auto;" .
                                    "left: auto;" .
                                    "right:var(--swiper-navigation-sides-offset);" .
                                    "margin-top: 0;" .
                                "}";
                            }else{
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."right: calc( calc(".$css_right." * 2) + calc(var(--swiper-navigation-width) * 2) );" .
                                        "width: auto;" .
                                    "}";                                 
                                }
                                $css[] = $btn_prev."{" .
                                    "top: ".$css_top.";" .
                                    "bottom: auto;" .
                                    "left: auto;" .
                                    "right: calc( ".$css_right." + var(--swiper-navigation-width));" .
                                    "margin-top: 0;" .
                                "}";
                                $css[] = $btn_next."{" .
                                    "top: ".$css_top.";" .
                                    "bottom: auto;" .
                                    "left: auto;" .
                                    "right: ".$css_right.";" .
                                    "margin-top: 0;" .
                                "}";
                            }
                        break;

                        //sag alt
                        case "end-end" :
                            if($direction == "vertical" && $rotate){
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."right: calc( calc(".$css_right." * 2) + var(--swiper-navigation-size) );" .
                                        "width: auto;" .
                                    "}";                                   
                                }
                                $css[] = $btn_prev."{" .
                                        "top: auto;" .
                                        "bottom: calc(".$css_top." + ".$css_size.");" .
                                        "left:  auto;" .
                                        "right:".$css_left.";" .
                                        "margin-top:0;" .
                                "}";
                                $css[] = $btn_next."{" .
                                        "top: auto;" .
                                        "bottom: ". $css_top .";" .
                                        "left: auto;" .
                                        "right: ".$css_left.";" .
                                        "margin-top:0;" .
                                "}";
                            }else{
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."right: calc( calc(".$css_right." * 2) + calc(var(--swiper-navigation-width) * 2) );" .
                                        "width: auto;" .
                                    "}";                                 
                                }
                                $css[] = $btn_prev."{" .
                                    "top: auto;" .
                                    "bottom: ".$css_top.";" .
                                    "left: auto;" .
                                    "right: calc( ".$css_right." + var(--swiper-navigation-width));" .
                                "}";
                                $css[] = $btn_next."{" .
                                    "top: auto;" .
                                    "bottom: ".$css_top.";" .
                                    "left: auto;" .
                                    "right: ".$css_right.";" .
                                    "margin-top:0;" .
                                "}";
                            }
                        break;
                }

                $values = [];
                $values["--swiper-navigation-color"] = $color;
                $values["--swiper-navigation-color-dark"] = $color_dark;
                $values["--swiper-navigation-size"] = $size;
                $values["--swiper-navigation-sides-offset"] = $sides_offset;
                $values["--swiper-navigation-top-offset"] = $top_offset;
                $values["--swiper-navigation-width"] = "calc(var(--swiper-navigation-size)/ 44 * 27)";
                $css[] = $id."{" .
                    implode("; ", array_map(fn($k, $v) => "$k: $v", array_keys($values), $values)) .
                "}";

                if($placement == "outside"){
                    $css[] = $id.">.card{" .
                        "min-height: inherit;" .
                    "}";                     
                }

                $css[] = $id." .swiper-button-prev, ".$id." .swiper-button-next{" .
                    "transition: color .3s ease-out, background-color .3s ease-out;" .
                "}";
                $css[] = $id." .swiper.slide-dark .swiper-button-prev, ".$id." .swiper.slide-dark .swiper-button-next{" .
                    "color: var(--swiper-navigation-color-dark);" .
                "}";

                if($rotate){
                    $css[] = $btn_prev.", ".$btn_next."{" .
                        "transform: rotate(90deg);" .
                    "}";
                }
            break;

            case "pagination" :
                $pagination_type = $control["type"];
                $attrs[$type] = $pagination_type;

                if($control["hide"]){
                    $css[] = generate_breakpoint_css($id." .swiper-pagination", $control["hide"]);
                    if($placement == "outside"){
                        $css[] = generate_breakpoint_css($space_obj, $control["hide"], "padding:0!important;top:0!important;bottom:0!important;left:0!important;right:0!important;");
                    }
                }

                if($pagination_type == "bullets"){

                    $values = [];

                    if(isset($control["view"]["bullets"]["visible"]) && $control["view"]["bullets"]["visible"] > 0){
                        $attrs["pagination-visible"] = $control["view"]["bullets"]["visible"];
                    }

                    $color = $control["view"]["bullets"]["active"]["color"];
                    $color_dark = $control["view"]["bullets"]["active"]["color_dark"];
                    $opacity = $control["view"]["bullets"]["active"]["opacity"];

                    $color_inactive = $control["view"]["bullets"]["inactive"]["color"];
                    $color_dark_inactive = $control["view"]["bullets"]["inactive"]["color_dark"];
                    $opacity_inactive = $control["view"]["bullets"]["inactive"]["opacity"];

                    $left = unit_value($control["view"]["bullets"]["left"]);
                    $right = unit_value($control["view"]["bullets"]["right"]);
                    $top = unit_value($control["view"]["bullets"]["top"]);
                    $bottom = unit_value($control["view"]["bullets"]["bottom"]);

                    $gap_x = unit_value($control["view"]["bullets"]["gap"]);
                    $gap_y = unit_value($control["view"]["bullets"]["gap"]);

                    $width = unit_value($control["view"]["bullets"]["width"]);
                    $height = unit_value($control["view"]["bullets"]["height"]);
                    $size = unit_value($control["view"]["bullets"]["width"]);
                    $border_radius = unit_value($control["view"]["bullets"]["border_radius"]);

                    if($position_x == "center"){
                        $left = "50%";
                    }
                    if($position_y == "center"){
                        $top = "50%";
                    }

                    $pagination = $id." .swiper-pagination";

                    switch($position_x."-".$position_y){
                        
                        //sol ust
                        case "start-start" :
                            if($direction == "vertical"){

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."left: calc( calc(var(--swiper-pagination-left) * 2) + var(--swiper-pagination-bullet-width));" .
                                        "width: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "height: auto;" .
                                    "transform:none;" .
                                "}";

                            }else{

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."top: calc( calc(var(--swiper-pagination-left) * 2) + var(--swiper-pagination-bullet-height));" .
                                        "height: auto;" .
                                    "}";                                   
                                }
                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "transform: none;" .
                                    "width: auto;" .
                                "}";

                            }
                        break;

                        // sol orta
                        case "start-center" :
                            if($direction == "vertical"){

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."left: calc( calc(var(--swiper-pagination-left) * 2) + var(--swiper-pagination-bullet-width));" .
                                        "width: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "width: auto;" .
                                    "height: auto;" .
                                    "transform: translateY(-50%);" .
                                "}";

                            }else{

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."left: calc( calc(var(--swiper-pagination-left) * 2) + var(--swiper-pagination-bullet-width));" .
                                        "width: auto;" .
                                    "}";                                   
                                }
                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "width: auto;" .
                                    "transform: translateY(-50%);" .
                                "}";
                                $css[] = $pagination." .swiper-pagination-bullet{" .
                                    "display: block;" .
                                    "margin: var(--swiper-pagination-bullet-vertical-gap);" .
                                "}";
                            }
                        break;

                        //sol alt
                        case "start-end" :
                            if($direction == "vertical"){

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."left: calc( calc(var(--swiper-pagination-left) * 2) + var(--swiper-pagination-bullet-width));" .
                                        "width: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: auto;" .
                                    "bottom: var(--swiper-pagination-bottom);" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "height: auto;" .
                                    "transform:none;" .
                                "}";                                  

                            }else{

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."left: calc( calc(var(--swiper-pagination-left) * 2) + var(--swiper-pagination-bullet-height));" .
                                        "height: auto;" .
                                    "}";                                   
                                }
                                $css[] = $pagination."{" .
                                    "top: auto;" .
                                    "bottom: var(--swiper-pagination-bottom);" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "height: auto;" .
                                    "transform:none;" .
                                "}";
                                $css[] = $pagination." .swiper-pagination-bullet{" .
                                    "display: block;" .
                                    "margin: var(--swiper-pagination-bullet-vertical-gap);" .
                                "}";
                            }
                        break;
                        
                        //orta üst
                        case "center-start" : //ok
                            if($direction == "vertical"){

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."top: calc( calc(var(--swiper-pagination-top) * 2) + var(--swiper-pagination-bullet-height));" .
                                        "height: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "width:auto;" .
                                    "height: auto;" .
                                    "transform:translateX(-50%);" .
                                "}";
                                $css[] = $pagination." .swiper-pagination-bullet{" .
                                    "display: inline-block;" .
                                    "margin: var(--swiper-pagination-bullet-horizontal-gap);" .
                                "}";                                

                            }else{

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."top: calc( calc(var(--swiper-pagination-top) * 2) + var(--swiper-pagination-bullet-height));" .
                                        "height: auto;" .
                                    "}";                                   
                                }
                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "width:auto;" .
                                    "height: auto;" .
                                    "transform:translateX(-50%);" .
                                "}";
                                /*$css[] = $pagination." .swiper-pagination-bullet{" .
                                    "display: block;" .
                                    "margin: var(--swiper-pagination-bullet-vertical-gap);" .
                                "}";*/
                            }
                        break;

                        //orta - orta alt
                        case "center-center" : //ok
                        case "center-end" :
                            if($direction == "vertical"){

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."bottom: calc( calc(var(--swiper-pagination-bottom) * 2) + var(--swiper-pagination-bullet-height));" .
                                        "height: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: auto;" .
                                    "bottom: var(--swiper-pagination-bottom);" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "width:auto;" .
                                    "height: auto;" .
                                    "transform:translateX(-50%);" .
                                "}";
                                $css[] = $pagination." .swiper-pagination-bullet{" .
                                    "display: inline-block;" .
                                    "margin: var(--swiper-pagination-bullet-horizontal-gap);" .
                                "}";                                

                            }else{

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."top: calc( calc(var(--swiper-pagination-bottom) * 2) + var(--swiper-pagination-bullet-height));" .
                                        "height: auto;" .
                                    "}";                                   
                                }
                                $css[] = $pagination."{" .
                                    "top: auto;" .
                                    "bottom: var(--swiper-pagination-bottom);" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "width: auto;" .
                                    "height: auto;" .
                                    "transform:translateX(-50%);" .
                                "}";
                                /*$css[] = $pagination." .swiper-pagination-bullet{" .
                                    "display: block;" .
                                    "margin: var(--swiper-pagination-bullet-vertical-gap);" .
                                "}";*/
                            }
                        break;


                        //sag ust
                        case "end-start" :
                            if($direction == "vertical"){

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."right: calc( calc(var(--swiper-pagination-right) * 2) + var(--swiper-pagination-bullet-width));" .
                                        "width: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: auto;" .
                                    "right: var(--swiper-pagination-right);" .
                                    "width: auto;" .
                                    "height: auto;" .
                                    "transform:none;" .
                                "}";                                  

                            }else{

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."top: calc( calc(var(--swiper-pagination-top) * 2) + var(--swiper-pagination-bullet-height));" .
                                        "height: auto;" .
                                    "}";                                   
                                }
                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: auto;" .
                                    "right: var(--swiper-pagination-right);" .
                                    "transform: none;" .
                                    "width: auto;" .
                                "}";
                                /*$css[] = $pagination." .swiper-pagination-bullet{" .
                                    "display: block;" .
                                    "margin: var(--swiper-pagination-bullet-vertical-gap);" .
                                "}";*/
                            }
                        break;

                        //sag orta
                        case "end-center" :
                            if($direction == "vertical"){

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."right: calc( calc(var(--swiper-pagination-right) * 2) + var(--swiper-pagination-bullet-width));" .
                                        "width: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: auto;" .
                                    "right: var(--swiper-pagination-right);" .
                                    "width: auto;" .
                                    "height: auto;" .
                                    "transform: translateY(-50%);" .
                                "}";                                  

                            }else{

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."right: calc( calc(var(--swiper-pagination-right) * 2) + var(--swiper-pagination-bullet-width));" .
                                        "width: auto;" .
                                    "}";                                   
                                }
                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: auto;" .
                                    "right: var(--swiper-pagination-right);" .
                                    "width: auto;" .
                                    "height: auto;" .
                                    "transform: translateY(-50%);" .
                                "}";
                                $css[] = $pagination." .swiper-pagination-bullet{" .
                                    "display: block;" .
                                    "margin: var(--swiper-pagination-bullet-vertical-gap);" .
                                "}";
                            }
                        break;

                        //sag alt
                        case "end-end" :
                            if($direction == "vertical"){

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."right: calc( calc(var(--swiper-pagination-right) * 2) + var(--swiper-pagination-bullet-width));" .
                                        "width: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: auto;" .
                                    "bottom: var(--swiper-pagination-bottom);" .
                                    "left: auto;" .
                                    "right: var(--swiper-pagination-right);" .
                                    "height: auto;" .
                                    "transform:none;" .
                                "}";                                  

                            }else{

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."bottom: calc( calc(var(--swiper-pagination-bottom) * 2) + var(--swiper-pagination-bullet-height));" .
                                        "height: auto;" .
                                    "}";                                   
                                }
                                $css[] = $pagination."{" .
                                    "top: auto;" .
                                    "bottom: var(--swiper-pagination-bottom);" .
                                    "left: auto;" .
                                    "right: var(--swiper-pagination-right);" .
                                    "width: auto;" .
                                    "height: auto;" .
                                    "transform:none;" .
                                "}";
                                /*$css[] = $pagination." .swiper-pagination-bullet{" .
                                    "display: block;" .
                                    "margin: var(--swiper-pagination-bullet-vertical-gap);" .
                                "}";*/
                            }
                        break;
                    }

                    if($placement == "outside"){
                        $css[] = $id.">.card{" .
                            "min-height: inherit;" .
                        "}";                     
                    }
                    $css[] = $pagination."{" .
                         "font-size: 0;" .
                         "z-index: 3;" .
                    "}";
                    
                    $css[] = $id." .swiper .swiper-pagination .swiper-pagination-bullet{" .
                         "transition: background .3s ease-out;" .
                    "}";
                    $css[] = $id." .swiper.slide-dark .swiper-pagination .swiper-pagination-bullet.swiper-pagination-bullet-active{" .
                         "background: var(--swiper-pagination-bullet-color-dark);" .
                    "}";
                    $css[] = $id." .swiper.slide-dark .swiper-pagination .swiper-pagination-bullet:not(.swiper-pagination-bullet-active){" .
                         "background: var(--swiper-pagination-bullet-inactive-color-dark);" .
                    "}";

                    $values["--swiper-pagination-color"] = $color;
                    $values["--swiper-pagination-bullet-color-dark"] = $color_dark;
                    $values["--swiper-pagination-bullet-opacity"] = $opacity;

                    $values["--swiper-pagination-bullet-inactive-color"] = $color_inactive;
                    $values["--swiper-pagination-bullet-inactive-color-dark"] = $color_dark_inactive;
                    $values["--swiper-pagination-bullet-inactive-opacity"] = $opacity_inactive;

                    $values["--swiper-pagination-bullet-size"] = $size;
                    $values["--swiper-pagination-bullet-width"] = $width;
                    $values["--swiper-pagination-bullet-height"] = $height;
                    $values["--swiper-pagination-bullet-border-radius"] = $border_radius;
                    $values["--swiper-pagination-bullet-horizontal-gap"] = $gap_x;
                    $values["--swiper-pagination-bullet-vertical-gap"] = $gap_y;
                    $values["--swiper-pagination-left"] = $left;
                    $values["--swiper-pagination-right"] = $right;
                    $values["--swiper-pagination-top"] = $top;
                    $values["--swiper-pagination-bottom"] = $bottom;

                    $css[] = $id."{" .
                        implode("; ", array_map(fn($k, $v) => "$k: $v", array_keys($values), $values)) .
                    "}";
                }

                if($pagination_type == "progressbar"){
                    
                    $values = [];
                    $position = $control["position_".$direction];
                    $color = $control["view"]["progressbar"]["color"];
                    $bg_color = $control["view"]["progressbar"]["color_bg"];
                    $size =  unit_value($control["view"]["progressbar"]["size"]);

                    $top = $bottom = $left = $right = "auto";
                
                    if($direction == "vertical"){
                        $left  = $position == "left" ? 0 : $left;
                        $right = $position == "right" ? 0 : $right;
                    }
                    if($direction == "horizontal"){
                        $top    = $position == "top" ? 0 : $top;
                        $bottom = $position == "bottom" ? 0 : $bottom;
                    }

                    $values["--swiper-pagination-progressbar-bg-color"] = $bg_color;
                    $values["--swiper-pagination-progressbar-size"] = $size;

                    if(isset($color)){
                        $values["--swiper-pagination-color"] = $color;
                    }

                    $pagination = $id." .swiper-pagination-progressbar";

                    $css[] = $pagination."{" .
                        "top: ".$top.";" .
                        "left: ".$left.";" .
                        "right: ".$right.";" .
                        "bottom: ".$bottom.";" .
                    "}";

                    if($placement == "outside"){
                        switch($position){
                            
                            case "top" :
                                $css[] = $space_obj."{" .
                                    $space_prefix."top: var(--swiper-pagination-progressbar-size);" .
                                    "height: auto;" .
                                "}";
                            break;

                            case "left" :
                                $css[] = $space_obj."{" .
                                    $space_prefix."left: var(--swiper-pagination-progressbar-size);" .
                                    "width: auto;" .
                                 "}";
                            break;

                            case "right" :
                                $css[] = $space_obj."{" .
                                    $space_prefix."right: var(--swiper-pagination-progressbar-size);" .
                                    "width: auto;" .
                                "}";
                            break;

                            case "bottom" :
                                $css[] = $space_obj."{" .
                                    $space_prefix."bottom: var(--swiper-pagination-progressbar-size);" .
                                    "height: auto;" .
                                "}";
                            break;

                        }
                    }

                    $css[] = $id."{" .
                        implode("; ", array_map(fn($k, $v) => "$k: $v", array_keys($values), $values)) .
                    "}";
                }

                if($pagination_type == "fraction"){
                    
                    $values = [];

                    $color = $control["view"]["fraction"]["color"];
                    $color_dark = $control["view"]["fraction"]["color_dark"];
                    $size = unit_value($control["view"]["fraction"]["size"]);
                    $width = "calc(".$size." * 2)";

                    $left = unit_value($control["view"]["fraction"]["left"]);
                    $right = unit_value($control["view"]["fraction"]["right"]);
                    $top = unit_value($control["view"]["fraction"]["top"]);
                    $bottom = unit_value($control["view"]["fraction"]["bottom"]);

                    if($position_x == "center"){
                        $left = "50%";
                    }
                    if($position_y == "center"){
                        $top = "50%";
                    }

                    $pagination = $id." .swiper-pagination-fraction";

                    switch($position_x."-".$position_y){
                        
                        //sol ust
                        case "start-start" :

                            if($placement == "outside"){
                                $css[] = $space_obj."{" .
                                    $space_prefix."top: calc( calc(var(--swiper-pagination-top) * 2) + var(--swiper-pagination-fraction-size));" .
                                    "width: auto;" .
                                "}";
                            }
                            $css[] = $pagination."{" .
                                "top: var(--swiper-pagination-top);" .
                                "bottom: auto;" .
                                "left: var(--swiper-pagination-left);" .
                                "right: auto;" .
                                "width: auto;" .
                                "height: auto;" .
                            "}";
                        break;

                        // sol orta
                        case "start-center" :

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."left: calc( calc(var(--swiper-pagination-left) * 2) + var(--swiper-pagination-fraction-width));" .
                                        "width: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "width: auto;" .
                                    "height: auto;" .
                                    "transform: translateY(-50%);" .
                                "}";
                           
                        break;

                        //sol alt
                        case "start-end" :
                         
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."bottom: calc( calc(var(--swiper-pagination-bottom) * 2) + var(--swiper-pagination-fraction-size));" .
                                        "height: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: auto;" .
                                    "bottom: var(--swiper-pagination-bottom);" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "width: auto;" .
                                    "height: auto;" .
                                    "text-align: left;" .
                                "}";
                           
                        break;

                        

                        //orta üst
                        case "center-start" : //ok
                          
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."top: calc( calc(var(--swiper-pagination-top) * 2) + var(--swiper-pagination-fraction-size));" .
                                        "height: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "width:auto;" .
                                    "height: auto;" .
                                    "transform:translateX(-50%);" .
                                    "text-align: center;" .
                                "}";

                        break;

                        //orta - orta alt
                        case "center-center" : //ok
                        case "center-end" :
                        
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."bottom: calc( calc(var(--swiper-pagination-bottom) * 2) + var(--swiper-pagination-fraction-size));" .
                                        "height: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: auto;" .
                                    "bottom: var(--swiper-pagination-bottom);" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "width:auto;" .
                                    "height: auto;" .
                                    "text-align: center;" .
                                "}";

                        break;



                        //sag ust
                        case "end-start" :
                           
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."top: calc( calc(var(--swiper-pagination-top) * 2) + var(--swiper-pagination-fraction-size));" .
                                        "height: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: auto;" .
                                    "right: var(--swiper-pagination-right);" .
                                    "width: auto;" .
                                    "height: auto;" .
                                    "text-align: right;" .
                                "}";
                            
                        break;

                        //sag orta
                        case "end-center" :

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."right: calc( calc(var(--swiper-pagination-right) * 2) + var(--swiper-pagination-fraction-width));" .
                                        "width: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: auto;" .
                                    "right: var(--swiper-pagination-right);" .
                                    "width: auto;" .
                                    "height: auto;" .
                                    "transform: translateY(-50%);" .
                                "}";                                  

                        break;

                        //sag alt
                        case "end-end" :
                    
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."bottom: calc( calc(var(--swiper-pagination-right) * 2) + var(--swiper-pagination-fraction-size));" .
                                        "height: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: auto;" .
                                    "bottom: var(--swiper-pagination-bottom);" .
                                    "left: auto;" .
                                    "right: var(--swiper-pagination-right);" .
                                    "height: auto;" .
                                    "text-align: right;" .
                                "}";                                  

                          
                        break;
                    }

                    if($placement == "outside"){
                        $css[] = $id.">.card{" .
                            "min-height: inherit;" .
                        "}";                     
                    }

                    $css[] = $pagination."{" .
                        "font-size: var(--swiper-pagination-fraction-size);" .
                        "z-index: 3;" .
                    "}";

                    $css[] = $id." .swiper.slide-dark .swiper-pagination-fraction{" .
                         "color: var(--swiper-pagination-fraction-color-dark);" .
                    "}";

                    $values["--swiper-pagination-fraction-color"] = $color;
                    $values["--swiper-pagination-fraction-color-dark"] = $color_dark;
                    $values["--swiper-pagination-fraction-size"] = $size;
                    $values["--swiper-pagination-fraction-width"] = $width;
                    $values["--swiper-pagination-left"] = $left;
                    $values["--swiper-pagination-right"] = $right;
                    $values["--swiper-pagination-top"] = $top;
                    $values["--swiper-pagination-bottom"] = $bottom;

                    if(isset($color)){
                        $values["--swiper-pagination-color"] = $color;
                    }
                    $css[] = $id."{" .
                        implode("; ", array_map(fn($k, $v) => "$k: $v", array_keys($values), $values)) .
                    "}";
                }

                if($pagination_type == "custom"){
                    
                    $values = [];

                    $color = $control["view"]["custom"]["color"];
                    $color_dark = $control["view"]["custom"]["color_dark"];
                    $size = unit_value($control["view"]["custom"]["size"]);
                    $width = "calc(".$size." * 2)";
                    $left = unit_value($control["view"]["custom"]["left"]);
                    $right = unit_value($control["view"]["custom"]["right"]);
                    $top = unit_value($control["view"]["custom"]["top"]);
                    $bottom = unit_value($control["view"]["custom"]["bottom"]);

                    $js = $control["view"]["custom"]["js"];
                    if(!empty($js)){
                        $func = str_replace("#", "", $id)."_swiper_custom";
                        $js = " ".$func." = " .$js.";";
                        $attrs["render-bullet"] = $func;
                    }

                    if($position_x == "center"){
                        $left = "50%";
                    }
                    if($position_y == "center"){
                        $top = "50%";
                    }

                    $pagination = $id." .swiper-pagination-custom";

                    switch($position_x."-".$position_y){
                        
                        //sol ust
                        case "start-start" :

                            if($placement == "outside"){
                                $css[] = $space_obj."{" .
                                    $space_prefix."top: calc( calc(var(--swiper-pagination-top) * 2) + var(--swiper-pagination-fraction-size));" .
                                    "width: auto;" .
                                "}";
                            }
                            $css[] = $pagination."{" .
                                "top: var(--swiper-pagination-top);" .
                                "bottom: auto;" .
                                "left: var(--swiper-pagination-left);" .
                                "right: auto;" .
                                "width: auto;" .
                                "height: auto;" .
                            "}";
                        break;

                        // sol orta
                        case "start-center" :

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."left: calc( calc(var(--swiper-pagination-left) * 2) + var(--swiper-pagination-fraction-width));" .
                                        "width: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "width: auto;" .
                                    "height: auto;" .
                                    "transform: translateY(-50%);" .
                                "}";
                           
                        break;

                        //sol alt
                        case "start-end" :
                         
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."bottom: calc( calc(var(--swiper-pagination-bottom) * 2) + var(--swiper-pagination-fraction-size));" .
                                        "height: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: auto;" .
                                    "bottom: var(--swiper-pagination-bottom);" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "width: auto;" .
                                    "height: auto;" .
                                    "text-align: left;" .
                                "}";
                           
                        break;

                        

                        //orta üst
                        case "center-start" : //ok
                          
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."top: calc( calc(var(--swiper-pagination-top) * 2) + var(--swiper-pagination-fraction-size));" .
                                        "height: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "width:auto;" .
                                    "height: auto;" .
                                    "transform:translateX(-50%);" .
                                    "text-align: center;" .
                                "}";

                        break;

                        //orta - orta alt
                        case "center-center" : //ok
                        case "center-end" :
                        
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."bottom: calc( calc(var(--swiper-pagination-bottom) * 2) + var(--swiper-pagination-fraction-size));" .
                                        "height: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: auto;" .
                                    "bottom: var(--swiper-pagination-bottom);" .
                                    "left: var(--swiper-pagination-left);" .
                                    "right: auto;" .
                                    "width:auto;" .
                                    "height: auto;" .
                                    "text-align: center;" .
                                "}";

                        break;



                        //sag ust
                        case "end-start" :
                           
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."top: calc( calc(var(--swiper-pagination-top) * 2) + var(--swiper-pagination-fraction-size));" .
                                        "height: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: auto;" .
                                    "right: var(--swiper-pagination-right);" .
                                    "width: auto;" .
                                    "height: auto;" .
                                    "text-align: right;" .
                                "}";
                            
                        break;

                        //sag orta
                        case "end-center" :

                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."right: calc( calc(var(--swiper-pagination-right) * 2) + var(--swiper-pagination-fraction-width));" .
                                        "width: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: var(--swiper-pagination-top);" .
                                    "bottom: auto;" .
                                    "left: auto;" .
                                    "right: var(--swiper-pagination-right);" .
                                    "width: auto;" .
                                    "height: auto;" .
                                    "transform: translateY(-50%);" .
                                "}";                                  

                        break;

                        //sag alt
                        case "end-end" :
                    
                                if($placement == "outside"){
                                    $css[] = $space_obj."{" .
                                        $space_prefix."bottom: calc( calc(var(--swiper-pagination-right) * 2) + var(--swiper-pagination-fraction-size));" .
                                        "height: auto;" .
                                    "}";
                                }

                                $css[] = $pagination."{" .
                                    "top: auto;" .
                                    "bottom: var(--swiper-pagination-bottom);" .
                                    "left: auto;" .
                                    "right: var(--swiper-pagination-right);" .
                                    "height: auto;" .
                                    "text-align: right;" .
                                "}";                                  

                          
                        break;
                    }

                    if($placement == "outside"){
                        $css[] = $id.">.card{" .
                            "min-height: inherit;" .
                        "}";                     
                    }

                    $css[] = $pagination."{" .
                        "font-size: var(--swiper-pagination-fraction-size);" .
                        "z-index: 3;" .
                    "}";

                    $css[] = $pagination."{" .
                        "font-size: var(--swiper-pagination-custom-size);" .
                        "color: var(--swiper-pagination-custom-color);" .
                    "}";

                    $css[] = $id." .swiper.slide-dark .swiper-pagination-custom{" .
                         "color: var(--swiper-pagination-custom-color-dark);" .
                    "}";

                    $values["--swiper-pagination-custom-color"] = $color_custom;
                    $values["--swiper-pagination-custom-color-dark"] = $color_dark;
                    $values["--swiper-pagination-custom-size"] = $size;
                    $values["--swiper-pagination-left"] = $left;
                    $values["--swiper-pagination-right"] = $right;
                    $values["--swiper-pagination-top"] = $top;
                    $values["--swiper-pagination-bottom"] = $bottom;

                    if(isset($color)){
                        $values["--swiper-pagination-color"] = $color;
                    }
                    $css[] = $id."{" .
                        implode("; ", array_map(fn($k, $v) => "$k: $v", array_keys($values), $values)) .
                    "}";
                }
            break;

            case "pagination_thumbs" :
                $attrs[$type] = 1;

                $values = [];
            break;

            case "scrollbar" :
                $attrs[$type] = 1;

                $values = [];

                $attrs[$type."-draggable"] = $control["draggable"]?true:false;
                $attrs[$type."-snap"] = $control["snap"]?true:false;

                $position = $control["position_".$direction];
                $bg_color = $control["view"]["bg_color"];
                $drag_color = $control["view"]["drag_color"];
                $sides_offset = unit_value($control["view"]["sides_offset"]);
                $size = unit_value($control["view"]["size"]);
                $border_radius = unit_value($control["view"]["border_radius"]);
                
                $top = $bottom = $left = $right = "auto";
                
                if($direction == "vertical"){
                    $left  = $position == "left" ? $sides_offset : $left;
                    $right = $position == "right" ? $sides_offset : $right;
                }
                if($direction == "horizontal"){
                    $top    = $position == "top" ? $sides_offset : $top;
                    $bottom = $position == "bottom" ? $sides_offset : $bottom;
                }

                $pagination = $id." .swiper-scrollbar";
                
                if($placement == "outside"){
                    switch($position){
                        
                        case "top" :
                            $css[] = $space_obj."{" .
                                $space_prefix."top: calc( calc(var(--swiper-scrollbar-sides-offset) * 2) + var(--swiper-scrollbar-size));" .
                                "height: auto;" .
                            "}";
                        break;

                        case "left" :
                            $css[] = $space_obj."{" .
                                $space_prefix."left: calc( calc(var(--swiper-scrollbar-sides-offset) * 2) + var(--swiper-scrollbar-size));" .
                                "width: auto;" .
                             "}";
                        break;

                        case "right" :
                            $css[] = $space_obj."{" .
                                $space_prefix."right: calc( calc(var(--swiper-scrollbar-sides-offset) * 2) + var(--swiper-scrollbar-size));" .
                                "width: auto;" .
                            "}";
                        break;

                        case "bottom" :
                            $css[] = $space_obj."{" .
                                $space_prefix."bottom: calc( calc(var(--swiper-scrollbar-sides-offset) * 2) + var(--swiper-scrollbar-size));" .
                                "height: auto;" .
                            "}";
                        break;

                    }
                }

                $values["--swiper-scrollbar-bg-color"] = $bg_color;
                $values["--swiper-scrollbar-drag-bg-color"] = $drag_color;
                $values["--swiper-scrollbar-sides-offset"] = $sides_offset;
                $values["--swiper-scrollbar-size"] = $size;
                $values["--swiper-scrollbar-border-radius"] = $border_radius;
                $values["--swiper-scrollbar-top"] = $top;
                $values["--swiper-scrollbar-bottom"] = $bottom;
                $values["--swiper-scrollbar-left"] = $left;
                $values["--swiper-scrollbar-right"] = $right;

                $css[] = $id."{" .
                    implode("; ", array_map(fn($k, $v) => "$k: $v", array_keys($values), $values)) .
                "}";
            break;
        }

        if(isset($control["view"]["css"]) && !empty($control["view"]["css"])){
            $css[] = $id. " ".$control["view"]["css"];
        }

        $css = implode("\n", array_filter($css));
                
        $tools[$type] = array(
            "placement" => $control["placement"],
            "css" => $css,
            "js"  => $js
        );
    }
    
    $css = "";
    if($continuous_scroll){
        $css = $id." .swiper[data-slider-continuous-scroll] > .swiper-wrapper{" .
             "transition-timing-function:linear!important; " .
        "}";
        $attrs["freeMode"] = true;
        $attrs["loop"] = true;
        $attrs["allowTouchMove"] = false;
        $attrs["autoplay"] = true;
        $attrs["delay"] = 0;
        $attrs["slidesPerView"] = 5.5;
        $attrs["spaceBetween"] = 0;
        //$attrs["speed"] = 10;
    }

    return array(
       "attrs" => $attrs,
       "controls" => $tools,
       "css" => $css
    );
}

function block_columns($args=array(), $block = []){

    $classes = [];
    $attrs = [];
    $css = "";
    //$code = "";
    $sizes = array_reverse(array_keys($GLOBALS["breakpoints"]));//array("xxxl", "xxl","xl","lg","md","sm","xs");
    $gap_sizes = array(
        "0" => 0,
        "1" => 4,
        "2" => 8,
        "3" => 16,
        "4" => 24,
        "5" => 48
    );

    $slider_controls = [];

    if($args){
        if(!isset($args["slider"])){
            $args["slider"] = 0;  
        }

        $slide_half = 0;

        if(isset($args["slider"]) || isset($args["column_breakpoints"]) ) {
            if((isset($args["slider"]) && $args["slider"]) || (isset($block["name"]) && in_array($block["name"], ["acf/archive"])) ){
                
                if(isset($args["slider_settings"]) && $args["slider_settings"]){

                    foreach($args["slider_settings"] as $key => $item){
                        if(!is_array($item)){
                            $attrs["data-slider-".$key] = $item;
                        }else{
                            if($key == "controls" && $item && isset($block["id"])){
                                $slider_controls = block_slider_controls("#".$block["id"], $item, $args["slider_settings"]["direction"], $args["slider_settings"]["autoheight"], $args["slider_settings"]["continuous-scroll"]);
                            }
                        }
                        if(isset($slider_controls["attrs"])){
                            foreach($slider_controls["attrs"] as $key => $item){
                                $attrs["data-slider-".$key] = $item;
                            }
                        }
                    }
                    if($attrs["data-slider-autoheight"] && $attrs["data-slider-direction"] == "vertical"){
                       $attrs["data-slider-autoheight"] = false; 
                    }
                    $slide_half = $args["slider_settings"]["half_view"]?0.5:0;
                }

                if(isset($args["column_breakpoints"])){
                    $breakpoints = new ArrayObject();
                    $gaps = new ArrayObject();
                    $gap_direction = $args["slider_settings"]["direction"] == "horizontal" ? "gx" : "gy";
                    foreach($args["column_breakpoints"] as $key => $item){
                        if(in_array($key, $sizes)){
                            if(isset($item["columns"])){
                                $breakpoints[$key] = intval($item["columns"]) + $slide_half;
                            }
                            if(isset($item[$gap_direction])){
                                $gaps[$key] = $gap_sizes[$item[$gap_direction]];
                            }
                        }
                    }

                    $attrs["data-slider-breakpoints"] = json_encode($breakpoints);
                    $attrs["data-slider-gaps"] = json_encode($gaps);                
                }

                if(isset($args["block_settings"]) && $args["block_settings"]["height"] == "ratio"){
                    $attrs["data-slider-autoheight"] = false;
                }
                
            }else if(isset($args["column_breakpoints"])){

                $classes = array_merge($classes, block_responsive_column_classes($args["column_breakpoints"], "row-cols-", "columns"));
                $classes = array_merge($classes, block_responsive_column_classes($args["column_breakpoints"], "gx-", "gx"));
                $classes = array_merge($classes, block_responsive_column_classes($args["column_breakpoints"], "gy-", "gy"));

                if(isset($args["column_breakpoints"]["masonry"]) && $args["column_breakpoints"]["masonry"]){

                    $attrs["data-masonry"] = '{"percentPosition": true }';

                }else{

                    $classes[] = block_align($args["block_settings"]["align"]);

                    /*if(isset($args["block_settings"]) && isset($args["block_settings"]["horizontal_align"]) && $args["block_settings"]["horizontal_align"]){
                        $classes = array_merge($classes, block_responsive_classes($args["block_settings"]["horizontal_align"], "justify-content-"));
                    }*/
                    
                }
            }
            

            if(isset($args["column_breakpoints"]) && isset($args["slider_settings"]) && isset($block["id"])){
                    $breakpoints = new ArrayObject();
                    $gaps = new ArrayObject();
                    $gap_direction = $args["slider_settings"]["direction"] == "horizontal" ? "gx" : "gy";
                    $css_temp = "#".$block["id"]." .swiper-slider{";
                    foreach($args["column_breakpoints"] as $key => $item){
                        if(in_array($key, $sizes)){
                            if(isset($item["columns"])){
                                $breakpoints[$key] = intval($item["columns"]) + $slide_half;
                                $css_temp .= "--col-".$key.":".$breakpoints[$key].";";
                            }
                            if(isset($item[$gap_direction])){
                                $gaps[$key] = $gap_sizes[$item[$gap_direction]];
                                $css_temp .= "--gap-".$key.":".$gaps[$key].";";
                            }
                        }
                    }
                    $css_temp .= "}";
                    $css .= $css_temp;
            }

                       
        }
    }

    $css .= isset($slider_controls["css"]) ? $slider_controls["css"] : "";

    return array(
        "class" => implode(" ", $classes),
        "attrs" => array2Attrs($attrs),
        "controls" => isset($slider_controls["controls"]) ? $slider_controls["controls"] : [],
        "css" => $css
    );
}
function block_aos($args=array()){
    $attrs = [];
    if($args["animation"] != "no"){
        $attrs["data-aos"] = $args["animation"];
        $attrs["data-aos-easing"] = $args["easing"];
        $attrs["data-aos-delay"] = $args["delay"];
        $attrs["data-aos-duration"] = $args["duration"];
        //$attrs["data-aos-anchor-placement"] = $args["anchor"];
    }
    return array2Attrs($attrs);
}
function block_aos_delay($str="", $delay=0) {
    if (is_string($str) && preg_match('/data-aos-delay="(\d+)"/', $str, $matches)) {
        $new_str = preg_replace('/data-aos-delay="\d+"/', 'data-aos-delay="' . $delay . '"', $str);
        return $new_str;
    }
    return $str;
}
function block_aos_duration($str="", $duration=0) {
    if (is_string($str) && preg_match('/data-aos-duration="(\d+)"/', $str, $matches)) {
        $new_str = preg_replace('/data-aos-duration="\d+"/', 'data-aos-duration="' . $duration . '"', $str);
        return $new_str;
    }
    return $str;
}
function block_aos_animation($str="", $animation="none") {
    if (is_string($str) && preg_match('/data-aos="[^"]*"/', $str)) {
        $new_str = preg_replace('/data-aos="[^"]*"/', 'data-aos="' . $animation . '"', $str);
        return $new_str;
    }
    return $str;
}

function block_bs_columns_col_classes($args){
    $classes = [];
    if($args){
        if(isset($args["breakpoints"])){
            $classes = array_merge($classes, block_responsive_column_classes($args["breakpoints"]));
        }
    }
    return implode(" ", $classes);
}
function block_bs_columns_rowcols_classes($args){
    $classes = [];
    if($args){
        if(isset($args["row_cols"]) && $args["row_cols"]){
           $classes = array_merge($classes, block_responsive_column_classes($args["column_breakpoints"], "row-cols-"));
        }
    }
    return implode(" ", $classes);
}


function block_bg_position_val($pos=""){
    if(!empty($pos)){
        switch($pos){
            case "center" :
                $pos = "50%";
            break;
            case "left" :
            case "top" :
                $pos = "0%";
            break;
            case "right" :
            case "bottom" :
                $pos = "100%";
            break;
        }
    }else{
        $pos = "50%";
    }
    return $pos;
}

function block_object_position($vr, $hr){
    $pos = "center";
    if(!empty($vr) && !empty($hr)){
        if($vr == "center" && $hr == "center"){
            $pos = "center";
        }else{
            $pos = $vr."-".$hr;
        }
    }
    return "object-position-".$pos;
}
function block_bg_image($block, $fields, $block_column){
    $image = "";
    //$image_class = " w-100 h-100 ";
    $image_class = "";
    $image_style = [];
    $image_bg_style = [];
    if(isset($fields["block_settings"])){
        $background = $fields["block_settings"]["background"];
        //$background_color = $fields["block_settings"]["background"]["color"];

        if(isset($background["background"]) && !empty($background["background"]["color"])){
            $background_color = $background["background"]["color"];
        }

        if(isset($background["background"]) && (!empty($background["background"]["gradient_color"]) && $background["background"]["gradient"])){
            $background_color = $background["background"]["gradient_color"];
        }

        $ignore_padding = $background["ignore_padding"];

        if(!empty($background["image"]) || !empty($background["image_responsive"])){// && (!empty($background["image_filter"]) || !empty($background["image_blend_mode"]))){
            if(!empty($background["image_filter"])){
                if(is_array($background["image_filter_amount"])){
                   $image_filter_amount = unit_value($background["image_filter_amount"]);
                }else{
                   $image_filter_amount = $background["image_filter_amount"]."%";
                }
                if($background["image_filter"] == "opacity"){
                    $image_style[] = "opacity:" . $image_filter_amount;
                }else{
                    $image_style[] = "filter:" . $background["image_filter"] . "(" . $image_filter_amount . ")";
                }
            }
            if(!empty($background["image_blend_mode"])){
                $image_style[] = "mix-blend-mode:" . $background["image_blend_mode"];
            }

            if($background["size"] == "fixed"){
                $image_bg_style[] = "background-size: cover";
            }else{
                $image_bg_style[] = "background-size:" . $background["size"];
            }
            $image_bg_style[] = "background-position:" . $background["position_hr"] ." " .$background["position_vr"];

            if($background["repeat"] != "no-repeat" || $background["size"] == "fixed"){
                $image_bg_style[] = "background-image:url(" . $background["image"] . ");background-repeat:" . $background["repeat"] . ";";
                if($background["size"] == "fixed"){
                    $image_bg_style[] = "background-attachment:" . $background["size"] . ";";
                }
            }
            
            if($image_style){
               $image_style = implode(";", $image_style); 
            }else{
                $image_style = "";
            }

            if($image_bg_style){
               $image_bg_style = implode(";", $image_bg_style); 
            }else{
                $image_bg_style = "";
            }

            $classes = !empty($background["image_mask"])?block_spacing(["margin" => $background["margin_mask"]]):"";

            //if($fields["block_settings"]["height"] != "auto"){
               $classes .= " position-absolute-fill ";
               if($ignore_padding){
                  $classes .= " ignore-padding ";
               }
            //}

            $image = '<div class="bg-cover overflow-hidden '.$classes.' '.($background["parallax"]?"jarallax overflow-hidden":"").'" ';
            if($background["repeat"] != "no-repeat" || $background["size"] == "fixed"){
                $image .= 'style="'.$image_style.$image_bg_style.'"';
            }

            if($background["parallax"]){
                $image_class = " jarallax-img ";
                $image .= ' data-jarallax data-speed="'.$background["parallax_settings"]["speed"].'" data-type="'.$background["parallax_settings"]["type"].'" data-img-size="'. $background["size"] .'" data-img-repeat="'. $background["repeat"] .'" data-img-position="'. block_bg_position_val($background["position_vr"]) ." " . block_bg_position_val($background["position_hr"]) .'" ';
                if(!empty($background_color)){
                   $image .= "style='background:".$background_color.";'";
                }
            }

            /*$image_class .= " a ".block_object_position($background["position_vr"], $background["position_hr"]) . " b ";

            $image .= '>';
            if($background["repeat"] == "no-repeat" && $background["size"] != "fixed"){
                if(in_array($background["size"], ["cover", "fill", "contain", "scale", "none"])){
                    $image_class .= " w-100 h-100 ";
                }
                $args = [
                    "class" => 'hoben object-fit-'.$background["size"].' '.$image_class . " x ",
                    "preview" => is_admin(),
                    "attrs" => []
                ];
                if($image_style){
                    $args["attrs"]["style"] = $image_style;
                }
                if(!empty($background["image"])){
                    $args["src"] = $background["image"];
                }
                if(!empty($background["image_responsive"])){
                    $args["src"] = $background["image_responsive"];
                }
                if(isset($args["src"])){
                    $image .= get_image_set($args);
                }
            }
            $image .= '</div>';*/

            $image .= '>';

            $size = $background["size"] ?? 'auto';
            $position_class = block_object_position($background["position_vr"], $background["position_hr"]);

            $image_class .= " a {$position_class} b ";

            if ($background["repeat"] == "no-repeat" && $size != "fixed") {
                if (!in_array($size, ["auto"]) && !$background["parallax"]) {
                    $image_class .= " w-100 h-100 ";
                }

                // object-fit uygun mu kontrolü
                $fit_classes = ["cover", "fill", "contain", "scale", "none"];
                $fit_class = in_array($size, $fit_classes) ? "object-fit-{$size}" : "";

                // scale -> cover map
                if ($size === "scale") {
                    $fit_class = "object-fit-cover";
                }

                $image_class .= " ".$fit_class." ";

                $args = [
                    "class" => $image_class,
                    "preview" => is_admin(),
                    "attrs" => []
                ];

                if ($image_style) {
                    $args["attrs"]["style"] = $image_style;
                }

                if (!empty($background["image_responsive"])) {
                    $args["src"] = $background["image_responsive"];
                } elseif (!empty($background["image"])) {
                    $args["src"] = $background["image"];
                }

                if (!empty($args["src"])) {
                    $image .= get_image_set($args);
                }
            }
            $image .= '</div>';
        }
    }
    return $image;
}
function block_bg_video($block, $fields, $block_column){
    $image = "";
    $image_class = " w-100 h-100 ";
    $image_style = [];
    if(isset($fields["block_settings"])){
        $background = $fields["block_settings"]["background"];
        //$background_color = $fields["block_settings"]["background"]["color"];

        if(isset($background["background"]) && !empty($background["background"]["color"])){
            $background_color = $background["background"]["color"];
        }

        if(isset($background["background"]) && (!empty($background["background"]["gradient_color"]) && $background["background"]["gradient"])){
            $background_color = $background["background"]["gradient_color"];
        }

        if(!empty($background["video_file"]) || !empty($background["video_url"])){
            $video = !empty($background["video_file"])?$background["video_file"]:$background["video_url"];
            if(!empty($background["image_filter"])){
                if(is_array($background["image_filter_amount"])){
                   $image_filter_amount = unit_value($background["image_filter_amount"]);
                }else{
                   $image_filter_amount = $background["image_filter_amount"]."%";
                }
                if($background["image_filter"] == "opacity"){
                    $image_style[] = "opacity:" . $image_filter_amount;
                }else{
                    $image_style[] = "filter:" . $background["image_filter"] . "(" . $image_filter_amount . ")";
                }
                
            }
            if(!empty($background["image_blend_mode"])){
                $image_style[] = "mix-blend-mode:" . $background["image_blend_mode"];
            }
            
            $container_attr = "";
            $container_class = "";
            $video_class = "";
            if(!empty($background["parallax"])){
                $container_attr = "data-jarallax data-speed='".$background["parallax_settings"]["speed"]."' data-type='".$background["parallax_settings"]["type"]."' ";
                $container_class = "jarallax";
                if($background["type"] == "file"){
                    $video_class = "jarallax-img";
                }
                if($background_color){
                    $image_style[] = "background:".$background_color;
                }
                //$image = '<div class="jarallax" data-jarallax data-video-src="' . $video . '" data-jarallax data-speed="'.$background["parallax_settings"]["speed"].'" data-type="'.$background["parallax_settings"]["type"].'" ></div>';
            }
            $args = [];
            $args["video_type"] = $background["type"];

            //$video_class .= " object-fit-cover h-100 w-100 ";
            
            $args["video_settings"] = array(
                "videoBg"  => true,
                "autoplay" => true,
                "loop"     => true,
                "muted"    => true,
                //"ratio"    => "16:9",
                "controls" => true,
                "controls_options" => ["settings"],
                "controls_options_settings" => ["quality"],
                "controls_hide" => true,
            );
            if($background["type"] == "file"){
                $args["video_file"] = $background["video_file"];
            }else{
                $args["video_url"] = extract_url($background["video_url"]);
            }
            if($image_style){
                $image_style = implode(";", $image_style); 
            }else{
                $image_style = "";
            }

            $classes = !empty($background["image_mask"])?block_spacing(["margin" => $background["margin_mask"]]):"";

            $ignore_padding = $background["ignore_padding"];
            if($ignore_padding){
                $classes .= " ignore-padding ";
            }

            $image = '<div '.$container_attr.' class="'.$container_class.' bg-cover 2 '.$classes.' position-absolute-fill hide-controls overflow-hidden" style="'.$image_style.'">';
            if($background["type"] == "embed" && !empty($background["parallax"])){
                $image .= '<div class="jarallax-img">';
            }
            $image .= get_video(["src" => $args, "class" => $video_class, "init" => true]);

            if($background["type"] == "embed" && !empty($background["parallax"])){
                $image .= '</div>';
            }
            $image .= "</div>";
        }
    }
    return $image;
}
function block_bg_slider($block, $fields, $block_column){
    $image = "";
    $image_class = " w-100 h-100 ";
    $image_style = [];
    $slider_style = [];
    if(isset($fields["block_settings"])){
        $background = $fields["block_settings"]["background"];

        if(isset($background["background"]) && !empty($background["background"]["color"])){
            $background_color = $background["background"]["color"];
        }

        if(isset($background["background"]) && (!empty($background["background"]["gradient_color"]) && $background["background"]["gradient"])){
            $background_color = $background["background"]["gradient_color"];
        }

        if(!empty($background["slider"])){
            if(!empty($background["image_filter"])){
                if(is_array($background["image_filter_amount"])){
                   $image_filter_amount = unit_value($background["image_filter_amount"]);
                }else{
                   $image_filter_amount = $background["image_filter_amount"]."%";
                }
                if($background["image_filter"] == "opacity"){
                    $slider_style[] = "opacity:" . $image_filter_amount;
                }else{
                    $slider_style[] = "filter:" . $background["image_filter"] . "(" . $image_filter_amount . ")";
                }
            }
            if(!empty($background["image_blend_mode"])){
                $slider_style[] = "mix-blend-mode:" . $background["image_blend_mode"];
            }
            
            if($slider_style){
               $slider_style = implode(";", $slider_style); 
            }else{
                $slider_style = "";
            }
            
            $classes = !empty($background["image_mask"])?block_spacing(["margin" => $background["margin_mask"]]):"";

            $ignore_padding = $background["ignore_padding"];
            if($ignore_padding){
                $classes .= " ignore-padding ";
            }

            $image = '<div class="bg-cover position-absolute-fill overflow-hidden '. $classes .' '.($background["parallax"]?"jarallax":"").'" ';
            //$image .= 'style="'.$image_style.'"';

            if($background["parallax"]){
                $image_class = " jarallax-img "; 
                $image .= ' data-jarallax data-speed="'.$background["parallax_settings"]["speed"].'" data-type="'.$background["parallax_settings"]["type"].'" data-img-size="'. $background["size"] .'" data-img-position="'. block_bg_position_val($background["position_hr"]) ." " . block_bg_position_val($background["position_vr"]) .'" ';
                if(!empty($background_color)){
                   $image .= "style='background:".$background_color.";'";
                }
            }

            $image .= '>';
            if($background["slider"]){
                $context = Timber::context();
                $context["slider_position"]  = "background"; 
                $context["block"]  = $block; 
                $context["fields"] = $fields; 
                $context["slider"] = $background["slider"];
                $row = block_columns($background, $block);
                if(!empty($slider_style)){
                    $row["attrs"] = $row["attrs"]." style='".$slider_style."' ";
                }
                $context["row"] = $row;
                $image .= Timber::compile("blocks/_slider.twig", $context);
            }
            $image .= '</div>';
        }
    }
    return $image;
}
function block_bg_media($block, $fields, $block_column){
    $result = "";
    if(isset($fields["block_settings"])){
        $background = $fields["block_settings"]["background"];
        switch($background["type"]){
            case "image" :
            case "image_responsive" :
                $result = block_bg_image($block, $fields, $block_column);
            break;

            case "slider" :
                $result = block_bg_slider($block, $fields, $block_column);
            break;

            case "file":
            case "embed":
                $result = block_bg_video($block, $fields, $block_column);
            break;

            default:
                if(!empty($background["image_mask"])){
                    $ignore_padding = $background["ignore_padding"];
                    $result = "<div class='bg-cover 1 ".$background["type"]." position-absolute-fill ".($ignore_padding?"ignore-padding":"")."'></div>";
                }
            break;
        }

        $css = "";
        $selector = $block["id"];
        if($block_column){
            $selector = $block_column["id"];
        }

        /*if(isset($background["background"]) && !empty($background["background"]["color"])){
            $background_color = $background["background"]["color"];
        }
        if(isset($background["background"]) && (!empty($background["background"]["gradient_color"]) && $background["background"]["gradient"])){
            $background_color = $background["background"]["gradient_color"];
        }

        if(!empty($background_color)){
            $css .= "#".$selector."{background-color:".$background_color.";}";
        }*/

        /*if(isset($background["overlay"]) && (!empty($background["overlay"]["gradient_color"]) && $background["overlay"]["gradient"]) || !empty($background["overlay"]["color"])){
            $overlay_color =($background["overlay"]["gradient"])?$background["overlay"]["gradient_color"]:$background["overlay"]["color"];
            $css .= "#".$selector." > .bg-cover:before{content:'';position:absolute;top:0;bottom:0;left:0;right:0;background-color:".$overlay_color.";z-index:2;}";
        }*/
        if(isset($background["overlay"]) && (!empty($background["overlay"]["gradient_color"]) || !empty($background["overlay"]["color"]))){
            $overlay_css_prop = "background-color"; // Varsayılan olarak background-color
            $overlay_css_value = "";
            $overlay_zindex = 2; // Varsayılan z-index

            if(!empty($background["overlay"]["gradient"]) && !empty($background["overlay"]["gradient_color"])){
                // Gradient varsa
                $overlay_css_prop = "background-image";
                $overlay_css_value = $background["overlay"]["gradient_color"];
            } elseif(!empty($background["overlay"]["color"])) {
                // Düz renk varsa
                $overlay_css_value = $background["overlay"]["color"];
            }

            if (!empty($overlay_css_value)) {
                $css .= "#".$selector." > .bg-cover:before{content:'';position:absolute;top:0;bottom:0;left:0;right:0;".$overlay_css_prop.":".$overlay_css_value.";z-index:".$overlay_zindex.";}";
            }
        }

        if(!empty($css)){
            $result .= "<style>".$css."</style>";
        }
    }
    return $result;
}
function block_css($block, $fields, $block_column){
    $code = "";
    $code_inner = "";
    $code_bg = "";
    $code_bg_color = "";
    $code_height = "";
    $code_bg_parallax = "";
    $code_svg = "";
    $media_query = [];

    $background = isset($fields["block_settings"]["background"])?$fields["block_settings"]["background"]:[];

    $selector = $block["id"];
    if($block_column){
        $selector = $block_column["id"];
    }
    $height = isset($fields["block_settings"]["height"])?($fields["block_settings"]["height"] == "auto" ? false : $fields["block_settings"]["height"]) : false;


    if($height){

        if($height == "ratio"){
            $ratio = block_ratio_padding($fields["block_settings"]["ratio"]);
            $code_height .= "#".$selector." > * {
                position: absolute!important;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
            }";
            if(!empty(block_container($fields["block_settings"]["container"]))){
                $code_inner .= "display:flex;
                    justify-content:center!important;";
                $code_height .= "#".$selector." > .container{
                    left:auto!important;
                    right:auto!important;
                }\n";
            }
            $code_height .= "#".$selector.":before{
                content: '';
                display: block;
                padding-top: ".$ratio."%;
            }";

        }elseif ($height == "responsive"){

            $height_responsive = isset($fields["block_settings"]["height_responsive"]) ? $fields["block_settings"]["height_responsive"] : [];
            //$height_responsive = array_reverse($height_responsive);
            $media_query = [];
            $last_css = "";
            $css = "";
            foreach($height_responsive as $breakpoint => $value){
                $code_height_responsive = "";
                $container = block_container_class($fields["block_settings"]["container"], false);
                //$container = block_container($fields["block_settings"]["container"]);

                if($value["height"] == "ratio"){
                    $ratio = block_ratio_padding($value["ratio"]);
                    $css = "#".$selector.(!empty($container)?" > .".$container:"")." > * {
                         position: absolute!important;
                         top: 0;
                         left: 0;
                         width: 100%;
                         height: 100%;
                    }";
                    if(!empty($container)){
                        $code_inner .= "display:flex;
                            justify-content:center!important;";
                        $css .= "#".$selector." > .container{
                            left:auto!important;
                            right:auto!important;
                        }";
                    }
                    $padding_top = $ratio."%";
                    /*if($fields["block_settings"]["hero"]){
                        $padding_top = "calc(".$ratio."% - var(--header-height-{breakpoint}))";
                        $css .= ".affix #".$selector.":before{
                            padding-top: calc(".$ratio."% - var(--header-height-{breakpoint}-affix));
                        }";
                    }*/

                    $css .= "#".$selector.(!empty($container)?" > .".$container:"").":before{
                        content: '';
                        display: block;
                        padding-top: ".$padding_top.";
                    }";
                    
                    $last_css = $css;
                    $code_height_responsive .= $css;

                }else{
                    if($value["height"] != "auto"){
                        /* hata olabilir
                        $css = "#".$selector."{
                            ".($value["height"]=="full" || $block["name"] == "acf/video"?"":"min-")."height: var(--hero-height-".$value["height"].");
                        }";
                        */
                        $css = "#".$selector."{
                            min-height: var(--hero-height-".$value["height"].");
                            max-height: var(--hero-height-".$value["height"].");
                            height: var(--hero-height-".$value["height"]."-min);
                        }";
                        if($block["name"] == "acf/slider" || $block["name"] == "acf/slider-advanced" || $block["name"] == "acf/archive"){
                            $css .= "#".$selector."{
                                .swiper{
                                    min-height:inherit;
                                }
                                .swiper-wrapper{
                                     min-height:100%!important;
                                }
                                .swiper-slide{
                                    min-height: inherit!important;
                                    height:auto;
                                }
                            }";
                        }
                        $last_css = $css; 
                        $code_height_responsive .= $css;
                    }else{
                        if(!empty($last_css)){
                            $css = $last_css;
                            //$last_css = "";
                        }else{
                            $css .= "#".$selector."{
                                height: auto;
                            }";                            
                        }
                        $code_height_responsive .= $css;
                    }
                }
                $code_height_responsive = str_replace("{breakpoint}", $breakpoint, $code_height_responsive);
                if(!empty($code_height_responsive)){
                    $media_query[$breakpoint] = $code_height_responsive;
                }
            }
            if($media_query){
                $code_height = block_css_media_query($media_query);
            }

        }elseif ($height == "100%"){
            $css .= "#".$selector."{
                        height:100%;
                    }";

        }elseif ($height != "auto"){
            /* hata olabilir
            $code_inner .= ($height=="full" || $block["name"] == "acf/video"?"":"min-")."height: var(--hero-height-".$height.");";
            */
            $code_inner .= "min-height: var(--hero-height-".$height.");max-height: var(--hero-height-".$height.");height: var(--hero-height-".$height."-min);";
            
            /*$code_height .= "#".$selector." {
                min-height: var(--hero-height-".$height.");
            }";*/
            
        }
    }

    $gradient = "";
    if( 
        (!$block_column || (isset($block_column["index"]) && ($block_column["index"] == -1 || $block_column["index"] == "-1")) || $block_column["block"] == "bootstrap-columns")
    ){

        if(isset($block["style"]["color"]["text"]) && !empty($block["style"]["color"]["text"])){
            $code_inner .= "color:".$block["style"]["color"]["text"].";";
        }

        /*if(isset($block["backgroundColor"]) && !empty($block["backgroundColor"])){
            $code_inner .= "background-color:var(".$block["backgroundColor"].");";
        }elseif(isset($block["style"]["color"]["background"]) && !empty($block["style"]["color"]["background"])){
            $code_inner .= "background-color:".$block["style"]["color"]["background"].";";
        }

        if(isset($block["style"]["color"]["background"]) && !empty($block["style"]["color"]["background"])){
            $code_inner .= "background-color:".$block["style"]["color"]["background"].";";
        }*/
        
        
        /*if(isset($block["gradient"]) && !empty($block["gradient"])){
            $gradient .= "background:var(".$block["gradient"].");";
        }elseif(isset($block["style"]["color"]["gradient"]) && !empty($block["style"]["color"]["gradient"])){
            $gradient .= "background:".$block["style"]["color"]["gradient"].";";
        }*/


        if(isset($background["background"]) && !empty($background["background"]["color"])){
            $code_inner .=  "background-color: ".$background["background"]["color"].";";
            $code_bg_parallax .= "background-color: ".$background["background"]["color"].";";
        }

        if(isset($background["background"]) && (!empty($background["background"]["gradient_color"]) && $background["background"]["gradient"])){
            $gradient .= "background: ".$background["background"]["gradient_color"].";";
            $code_inner .=  $gradient;
            $code_bg_parallax .= $gradient;
        }

        if(!empty($gradient) && !$background["gradient_mask"] && !$block_column){
            $code_inner .= $gradient;
            $code_bg_parallax .= $gradient;
        }   

    }

    $color = isset($fields["block_settings"]["text_color"])?$fields["block_settings"]["text_color"]:"";
    if($color){
        $code_inner .= "color: ".$color.";";
    }
    
    if($background){
        if(isset($background["background"]) && !empty($background["background"]["color"])){
            $code_inner .=  "background-color: ".$background["background"]["color"].";";
        }
        if(isset($background["background"]) && (!empty($background["background"]["gradient_color"]) && $background["background"]["gradient"])){
            $gradient .= "background: ".$background["background"]["gradient_color"].";";
            $code_inner .=  $gradient;
        }
        if($block["name"] != "acf/hero"){
            if(!empty($background["image"])){
                if(!$background["image_filter"] && !$background["image_blend_mode"]){
                /*    $code_inner .= "background-image:url(".$background["image"].");";
                    if(!empty($background["position_hr"]) && !empty($background["position_vr"])){
                        $code_inner .="background-position:".$background["position_hr"]." ".$background["position_vr"].";";
                    }
                    if(!empty($background["repeat"])){
                        $code_inner .="background-repeat:".$background["repeat"].";";
                    }
                    if(!empty($background["size"])){
                        $code_inner .="background-size:".$background["size"].";";
                    }*/
                }else{
                    $code_inner .="position:relative;";
                }
            }            
        }
        if(!empty($background["image_mask"])){
            $mask_position = "";
            if(!empty($background["position_hr_mask"]) && !empty($background["position_vr_mask"])){
                $mask_position .="mask-position:".$background["position_hr_mask"]." ".$background["position_vr_mask"].";";
                $mask_position .="-webkit-mask-position:".$background["position_hr_mask"]." ".$background["position_vr_mask"].";";
            }
            if(!empty($background["repeat_mask"])){
                $mask_position .="mask-repeat:".$background["repeat_mask"].";";
                $mask_position .="-webkit-mask-repeat:".$background["repeat_mask"].";";
            }
            if(!empty($background["size_mask"])){
                $mask_position .="mask-size:".$background["size_mask"].";";
                $mask_position .="-webkit-mask-size:".$background["size_mask"].";";
            }

            if(empty($background["color_mask"]) && !$background["gradient_mask"]){

                $code_bg .= "mask: url(".$background["image_mask"].");-webkit-mask: url(".$background["image_mask"].")";
                $code_bg .= $mask_position;
                
            }else{

                $code_bg_color .= "mask: url(".$background["image_mask"].");-webkit-mask: url(".$background["image_mask"].");";
                $code_bg_color .= $mask_position;
                if(!empty($background["position_hr_mask"]) && !empty($background["position_vr_mask"])){
                    $code_bg_color .="mask-position:".$background["position_hr_mask"]." ".$background["position_vr_mask"].";";
                    $code_bg_color .="-webkit-mask-position:".$background["position_hr_mask"]." ".$background["position_vr_mask"].";";
                }
                if(!empty($background["repeat_mask"])){
                    $code_bg_color .="mask-repeat:".$background["repeat_mask"].";";
                    $code_bg_color .="-webkit-mask-repeat:".$background["repeat_mask"].";";
                }
                if(!empty($background["size_mask"])){
                    $code_bg_color .="mask-size:".$background["size_mask"].";";
                    $code_bg_color .="-webkit-mask-size:".$background["size_mask"].";";
                }

                if(!empty($gradient) && $background["gradient_mask"]){
                    $code_bg_color .= $gradient;
                }elseif(!empty($background["color_mask"])){
                    $code_bg_color .= "background-color:".$background["color_mask"].";";
                }

            }
        }

    }

    $slide_css_code = "";
    if(isset($fields["slider"]) && $fields["slider"] && is_array($fields["slider"])){ /// bilmiyom bu aktf olsunmu
        if(count($fields["slider"]) > 0){
            foreach($fields["slider"] as $key => $slide){

                $slide_css = [];

                //filters
                if(isset($slide["filters"])){
                    $filters = $slide["filters"];
                    if(!empty($filters["filter"]) || !empty($filters["blend_mode"])){
                        if(!empty($filters["filter"])){
                            if(is_array($filters["amount"])){
                               $image_filter_amount = unit_value($filters["amount"]);
                            }else{
                               $image_filter_amount = $filters["amount"]."%";
                            }
                            if($filters["filter"] == "opacity"){
                                $slide_css[] = "opacity:" . $image_filter_amount;
                            }else{
                                $slide_css[] = "filter:" . $filters["filter"] . "(" . $image_filter_amount . ")";
                            }
                            
                        }
                        if(!empty($filters["blend_mode"])){
                            $slide_css[] = "mix-blend-mode:" . $filters["blend_mode"];
                        }
                    }elseif(isset($slide["media_type"])){
                        if($slide["media_type"] == "image" && isset($slide["image"]["id"]) && empty($background["color"])){
                           $image = Timber::get_post($slide["image"]["id"]);
                           if($image){
                              $slide_css[] = "color:".$image->meta("contrast_color");
                              $slide_css[] = "background-color:".$image->meta("average_color");
                           }
                        }
                    }                    
                }elseif(isset($slide["media_type"])){
                    if($slide["media_type"] == "image" && isset($slide["image"]["id"]) && empty($background["color"])){
                        $image = Timber::get_post($slide["image"]["id"]);
                        if($image){
                            $slide_css[] = "color:".$image->meta("contrast_color");
                            $slide_css[] = "background-color:".$image->meta("average_color");
                        }else{
                            
                        }
                    }
                }

                //overlay
                $slide_overlay_css = [];
                if(isset($slide["overlay"]) && (!empty($slide["overlay"]["gradient_color"]) && $slide["overlay"]["gradient"]) || !empty($slide["overlay"]["color"])){
                    $overlay_color =($slide["overlay"]["gradient"])?$slide["overlay"]["gradient_color"]:$slide["overlay"]["color"];
                    $slide_overlay_css[] = "background: ".$overlay_color;
                }

                if($slide_overlay_css){
                    $slide_overlay_css = implode(";", $slide_overlay_css);
                    $slide_css_code .= "#".$selector." .slide-".($key + 1).":before{";
                       $slide_css_code .= $slide_overlay_css;
                    $slide_css_code .= "}";
                }

                $slide_bg_css = [];
                if(isset($slide["background"]) && (!empty($slide["background"]["gradient_color"]) && $slide["background"]["gradient"]) || !empty($slide["background"]["color"])){
                    $background_color = $slide["background"]["gradient"]?$slide["background"]["gradient_color"]:$slide["background"]["color"];
                    $slide_bg_css[] = "background: ".$background_color;
                }else{
                    /*if($background){
                        if(!empty($background["color"])){
                            $slide_bg_css[] = "background: ".$background["color"];
                        }
                    }*/
                }

                if($slide_bg_css){
                    $slide_bg_css = implode(";", $slide_bg_css);
                    $slide_css_code .= "#".$selector." .slide-".($key + 1)."{";
                       $slide_css_code .= $slide_bg_css;
                    $slide_css_code .= "}";
                }

                if($slide_css){
                    $slide_css = implode(";", $slide_css);
                    $slide_css_code .= "#".$selector." .slide-".($key + 1)." .swiper-bg > *{";
                       $slide_css_code .= $slide_css;
                    $slide_css_code .= "}";
                }
            }
        }
    }
    //error_log($block["name"]);
    //error_log(print_r($block_column, true));

    if(isset($block["name"]) && in_array($block["name"], ["acf/icons"]) || ($block_column && $block_column["block"] == "icons")){
        foreach($fields["icons"] as $icon_index => $icon){
            //error_log(print_r($icon["icon"], true));
            if(!empty($icon["icon"]["color"])){
                $code .= block_svg_color("#".$selector." .icon-".$icon_index." .image", $icon["icon"]["color"]);
            }
            if(!empty($icon["icon"]["styles"]["height"])){
                $icon_height = acf_units_field_value($icon["icon"]["styles"]["height"]);
                //error_log("icon height:".$icon_height);
                if(!empty($icon_height) && $icon_height){
                    $code .= "#".$selector." .icon-".$icon_index." .icon{max-height:".$icon_height.";}";
                    $code .= "#".$selector." .icon-".$icon_index." .icon svg{height:100%;width:auto;}";
                    $code .= "#".$selector." .icon-".$icon_index." .icon img{height:100%;width:auto;}";
                }
            }
        }
    }

    if(!empty($code_height)){
        $code .= $code_height;
    }
    if(!empty($code_inner)){
        $code .= "#".$selector." {".$code_inner."}";
    }

    if(!empty($code_bg)){
        $code .= "#".$selector." > .bg-cover{".$code_bg."}";
    }

    if($background["parallax"] && !empty($code_bg_parallax)){
        $code .= "#".$selector." > .bg-cover .jarallax-container{".$code_bg_parallax."}";
    }
    
    if(!empty($code_bg_color)){
       $code .= "#".$selector." > .bg-cover:before{content:'';position:absolute;top:0;bottom:0;left:0;right:0;z-index:1;".$code_bg_color."}";
    }

    if(!empty($slide_css_code)){
        $code .= $slide_css_code;
    }

    if(!empty($code)){
        $code = "<style type='text/css'>".$code."</style>";
    }

    return $code;
}
function block_css_media_query($query = []) {
    $css = "";
    if ($query) {
        $breakpoints = $GLOBALS["breakpoints"]; // mevcut breakpoints dizisi
        $keys = array_keys($breakpoints); // breakpoint anahtarları
        $existing_breakpoints = array_keys($query); // mevcut tanımlı query breakpoints

        // Eksik breakpointleri bulalım
        $missing_breakpoints = array_diff($keys, $existing_breakpoints);

        // Eksik breakpointler için CSS kopyalama işlemi
        foreach ($missing_breakpoints as $breakpoint) {
            // Önceki ve sonraki breakpointe göre aralık hesapla
            $index = array_search($breakpoint, $keys);
            if ($index > 0 && $index < count($breakpoints) - 1) {
                $min = $breakpoints[$keys[$index - 1]] + 1;
                $max = $breakpoints[$keys[$index + 1]] - 1;
            } elseif ($index == 0) {
                $min = 0;
                $max = $breakpoints[$keys[$index + 1]] - 1;
            } else {
                $min = $breakpoints[$keys[$index - 1]] + 1;
                $max = PHP_INT_MAX;
            }

            // Boş olmayan breakpointleri bul
            foreach ($query as $key => $code) {
                if (!empty($code)) {
                    // Uygun olan dolu breakpointi bulup kopyalayalım
                    $css .= "@media (min-width: " . $min . "px) and (max-width: " . $max . "px) {";
                    $css .= $code;  // Kopyalanacak CSS
                    $css .= "}\n";
                    break;  // Sadece bir dolu breakpoint üzerinden kopyalama yapılacak
                }
            }
        }

        // Mevcut query'lere göre CSS medya sorguları oluştur
        foreach ($query as $breakpoint => $code) {
            if (in_array($breakpoint, $keys)) {
                $index = array_search($breakpoint, $keys);
                if (empty($code)) {
                    if ($index > 0) {
                        $code = $query[$keys[$index - 1]];
                        $query[$breakpoint] = $code;
                    }
                }
                if (!empty($code)) {
                    if ($index == 0) {
                        $css .= "@media (max-width: " . ($breakpoints[$breakpoint]) . "px){";
                    } elseif ($index == count($breakpoints) - 1) {
                        $css .= "@media (min-width: " . ($breakpoints[$breakpoint]) . "px){";
                    } elseif ($index < count($breakpoints) - 1) {
                        if ($index == 1) {
                            $min = $breakpoints[$keys[$index - 1]] + 1;
                            $max = $breakpoints[$keys[$index + 1]] - 1;
                        } else {
                            $min = $breakpoints[$breakpoint];
                            $max = $breakpoints[$keys[$index + 1]] - 1;
                        }
                        $css .= "@media (min-width: " . $min . "px) and (max-width: " . $max . "px) {";
                    }
                    $css .= $code;
                    $css .= "}\n";
                }
            }
        }
    }
    return $css;
}

function block_svg_color($selector = "", $color = "") {
    if (empty($color) || empty($selector)) {
        return;
    }

    return "{$selector} path[fill]:not([stroke]),
        {$selector} rect[fill]:not([stroke]),
        {$selector} polygon[fill]:not([stroke]) {
            fill: {$color} !important;
        }

        {$selector} line[stroke],
        {$selector} path[stroke],
        {$selector} rect[stroke],
        {$selector} polygon[stroke] {
            stroke: {$color} !important;
        }";
}

function block_meta($block_data=array(), $fields = array(), $extras = array(), $block_column = "", $block_column_index = -1){
    $meta = array(
        "index"     => 0,
        "id"        => "",
        "settings"  => array(),
        "classes"   => "",
        "attrs"     => "",
        "container" => "",
        "row"       => "",
        "css"       => ""
    );
    if($block_data){

        if(isset($block_data["data"]["block_settings_custom_id"]) && !empty($block_data["data"]["block_settings_custom_id"])){
            $block_id = $block_data["data"]["block_settings_custom_id"];
        }else{
            $block_id = $block_data["id"];
        }

        $id = $block_id;

        if( $block_data["name"] == "acf/bootstrap-columns" && empty($block_column) &&
            isset($fields["acf_block_columns"][0]["acf_fc_layout"]) &&
            $fields["acf_block_columns"][0]["acf_fc_layout"] != "block-bootstrap-columns" && 
            $block_column_index > 0
        ){
            $block_column = $fields["acf_block_columns"][0]["acf_fc_layout"];
            $block_column = str_replace("block-", "", $block_column)."-sed";
        }

        if($block_column || $block_data["name"] == "acf/bootstrap-columns"){
           $id .= $block_column?"-".$fields["block_settings"]["column_id"]:"";//unique_code(5):"";
           $block_column = array(
                "block"  => $block_column?$block_column:"bootstrap-columns",
                "id"     => $id,
                "index"  => $block_column_index,
                "parent" => $block_id//$block_data["id"]
           );
        }
        $block_data["id"] = $id;

        $meta["lock"]             = isset($block_data["lock"]) && $block_data["lock"];
        $meta["index"]            = isset($block_data["index"])?$block_data["index"]:0;
        $meta["parent"]           = $block_column?$block_column["parent"]:0;
        $meta["id"]               = $id;//$block_data["id"].(!empty($block_column)?"-".unique_code(5):"");
        $meta["settings"]         = isset($fields["block_settings"])?$fields["block_settings"]:[];
        $meta["classes"]          = block_classes($block_data, $fields, $block_column);
        $meta["attrs"]            = block_attrs($block_data, $fields, $block_column);
        $meta["container"]        = isset($meta["settings"]["container"])?block_container($meta["settings"]["container"], $fields["block_settings"]["stretch_height"]):"default";
        $meta["bg_image"]         = block_bg_media($block_data, $fields, $block_column);
        $meta["data"]             = array(
            "align"      => $block_data["align"],
            "fullHeight" => $block_data["fullHeight"]
        );
       $meta["css"]               = block_css($block_data, $fields, $block_column);

       if(isset($fields["aos_settings"])){
            $meta["aos"] = block_aos($fields["aos_settings"]);
       }
       if(isset($fields["column_breakpoints"]) || isset($fields["slider_settings"])){
            $meta["row"] = block_columns($fields, $block_data);

            if(isset($fields["slider_settings"])){
                if(isset($meta["row"]["controls"])){
                    $css_tmp = $meta["css"];
                    $css = "";
                    foreach($meta["row"]["controls"] as $control){
                        if(isset($control["css"]) && !empty($control["css"])){
                            $css .= $control["css"];
                        }
                    }
                    if(!empty($meta["row"]["css"])){
                        $css .= $meta["row"]["css"];
                    }
                    if(strpos($css_tmp, "</style>") !== false){
                       $css_tmp = str_replace("</style>", $css."</style>", $css_tmp);
                    }else{
                        if(empty($css_tmp)){
                            $css_tmp = "<style type='text/css'>".$css."</style>";
                        }else{
                            $css_tmp .= $css; 
                        }
                    }
                    $meta["css"] = $css_tmp;
                }
            }
       }
       if(isset($fields["slider_settings"])){
            $meta["container_slider"] = block_container($fields["slider_settings"]["container"]);
       }
       if(isset($block_data["_acf_context"]["post_id"])){
            $meta["post_id"] = $block_data["_acf_context"]["post_id"];
       }
       if(isset($block_data["data"]["title"])){
            $meta["title"] = $block_data["data"]["title"];
       }
       if(isset($block_data["breadcrumb"])){
            $meta["breadcrumb"] = $block_data["breadcrumb"];
       }
       if($extras){
           foreach($extras as $key => $item){
                if(in_array($key, array_keys($meta))){
                    if(in_array($key, ["classes", "container", "row", "container_slider"])){
                        $value = $meta[$key];
                        if(!empty($value)){
                            $meta[$key] = $value." ".$item;
                        }
                    }
                }
            }
        }
    }

    $meta["_date"] = date("Y-m-d H:i:s");
    
    if (isset($_GET['fetch'])) {
        // save meta as option for fast response (saves 0.04 sn.)
        error_log("fetch saving ------  option ---- meta -> ". $id." block:". $block_data["name"] );
        update_option($block_data["id"], $meta);
    }

    return $meta;
}


function post_has_block($slug="", $block_name=""){
    $found = false;
     if (filter_var($slug, FILTER_VALIDATE_URL)) {
        $post_id = url_to_postid($slug);
    } else {
        if(is_numeric($slug)){
            $post_id = $slug;
        }else{
            $post_id = slug2Id($slug);//get_page_by_path($page)->ID;            
        }
    }
    if($post_id){
        $post_blocks = get_blocks($post_id);
        if($post_blocks){
            foreach ( $post_blocks as $key => $block ) {
                if($block["blockName"] == $block_name){
                    $found = true;
                    break;
                }
            }
        }        
    }
    return $found;
}

function post_has_core_block($slug="") {
    $found = false;
    if (filter_var($slug, FILTER_VALIDATE_URL)) {
        $post_id = url_to_postid($slug);
    } else {
        if(is_numeric($slug)){
            $post_id = $slug;
        } else {
            $post_id = slug2Id($slug);
        }
    }
    if($post_id){
        $post_blocks = get_blocks($post_id);
        if($post_blocks){
            foreach ( $post_blocks as $key => $block ) {
                if (isset($block['blockName']) && !empty($block['blockName'])) {
                    error_log($block['blockName']);
                    if (strpos($block['blockName'], 'core/') !== false && $block['blockName'] != 'core/paragraph') {
                        $found = true;
                        break;
                    }
                }
            }
        }        
    }
    return $found;
}


function generate_unique_column_id( $value, $post_id, $field ) {
    if ( empty( $value ) ) {
        $value = unique_code(5);
    }
    return $value;
}
add_filter( 'acf/load_value/name=column_id', 'generate_unique_column_id', 10, 3 );
add_filter( 'acf/update_value/name=column_id', 'generate_unique_column_id', 10, 3 );







function acf_get_block_data_item($array = [], $end = "") {
    $filtered = [];
    foreach ($array as $key => $value) {
        if ((!str_starts_with($key, '_')) && (str_ends_with($key, "_".$end) || $key === $end)) {
            $filtered[$key] = $value;
        }
    }
    return $filtered;
}
function acf_block_id_fields($post_id){

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if(defined("WP_ROCKET_VERSION") && function_exists("is_wp_rocket_crawling") && is_wp_rocket_crawling()) return;

    static $processed_posts = [];
    if (in_array($post_id, $processed_posts)) return;
    $processed_posts[] = $post_id;


    $content = get_post_field('post_content', $post_id);
    if (empty($content)) {
        return;
    }
    error_log("blocks parsing for id set");
    $blocks = parse_blocks($content);
    $updated = false;
    foreach ($blocks as &$block) {
        if (isset($block['blockName']) && strpos($block['blockName'], 'acf/') === 0) {

            $data = $block['attrs']['data'];

            $block_settings_field_id = $data['_block_settings'];//explode("_field", $data['_block_settings_hero'])[0];

            if (!isset($data['block_settings_custom_id']) || empty($data['block_settings_custom_id'])){
                $block['attrs']['data']['_block_settings_custom_id'] = $block_settings_field_id."_field_674d65b2e1dd0";
                $block['attrs']['data']['block_settings_custom_id'] = 'block_' . md5(uniqid('', true));
                //error_log("block : block_settings_custom_id added -> ".$block['attrs']['data']['block_settings_custom_id']);
                $updated = true;
            }
            if (!isset($data['block_settings_column_id']) || empty($data['block_settings_column_id'])){
                $block['attrs']['data']['_block_settings_column_id'] = $block_settings_field_id."_field_67213addcfaf3";
                $block['attrs']['data']['block_settings_column_id'] = unique_code(5);
                //error_log("block : block_settings_column_id added");
                $updated = true;
            }
            if($block['blockName'] == "acf/bootstrap-columns"){
                foreach ($data as $key => $value) {
                    if (str_ends_with($key, '_block_settings_column_id')) {
                        if (!preg_match('/^(_|block_settings|_block_settings)/', $key)) {
                            if (empty($data[$key])) {
                                $id = unique_code(5);
                                //error_log("column : ".$key."=".$id);
                                $block['attrs']['data'][$key] = $id;
                                $updated = true;
                            }
                        }
                    }
                }                  
            }

            $video_urls = acf_get_block_data_item($block['attrs']['data'] ,"video_url");
            if($video_urls){
                foreach($video_urls as $key => $video_url){
                    $title_key = str_replace("video_url", "video_title", $key);
                    if(isset($block['attrs']['data'][$title_key]) && empty($block['attrs']['data'][$title_key]) && !empty($video_url)){
                        $block['attrs']['data'][$title_key] = get_embed_video_title($video_url);
                        $updated = true;
                    }
                }
            }
        }
    }
    if ($updated) {
        //remove_action('save_post_product', [Saltbase::get_instance(), 'on_post_published'], 100);

        $new_content = wp_slash(serialize_blocks($blocks));
        wp_update_post([
            'ID'           => $post_id,
            'post_content' => $new_content,
        ]);
    }
}


add_action('acf/input/admin_footer', function() {
?>
<script>
(function($) { // jQuery'yi kullanmak için $ kullanıldı.
    
    // WordPress Data Store'u (Redux) kullanmak için gerekli yapılar
    const { select, dispatch } = wp.data;
    
    // custom_id'yi yenileyecek yardımcı fonksiyon
    function generateNewCustomId() {
        const newIdSuffix = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        return 'block_' + newIdSuffix;
    }

    // Blokları kontrol eden ve Duplike ID'leri yenileyen ana fonksiyon
    function checkAndFixDuplicateIds() {
        
        const blocks = select('core/block-editor').getBlocks();
        const { updateBlockAttributes } = dispatch('core/block-editor');

        const customIdKey = 'block_settings_custom_id';
        const idMap = {};
        
        // 1. Adım: Tüm custom_id'leri topla ve tekrar edenleri bul
        blocks.forEach(block => {
            if (block.attributes.data && block.attributes.data[customIdKey]) {
                const id = block.attributes.data[customIdKey];
                if (id.startsWith('block_')) {
                    idMap[id] = (idMap[id] || 0) + 1;
                }
            }
        });

        // 2. Adım: Haritayı kullanarak duplike ID'leri bul ve yenile
        blocks.forEach(block => {
            const attributes = block.attributes;
            
            if (attributes.data && attributes.data[customIdKey]) {
                const currentId = attributes.data[customIdKey];

                // Eğer ID 'block_' ile başlıyorsa VE idMap'te 1'den fazla kullanılmışsa (Duplike edilmişse)
                // VEYA ID boşsa (Yeni blok eklenmiş olabilir)
                if (currentId.startsWith('block_') && idMap[currentId] > 1 || !currentId) {
                    
                    const newId = generateNewCustomId();
                    const currentData = attributes.data; // Mevcut data nesnesini al
                    
                    // ----------------------------------------------------------------------
                    // DÜZELTME 1: Gutenberg Data Store'u Güncelle (Diğer verileri koruyarak)
                    // ----------------------------------------------------------------------
                    const newData = { 
                        ...currentData, 
                        [customIdKey]: newId 
                    };
                    
                    updateBlockAttributes(block.clientId, { 
                        data: newData 
                    });
                    
                    // ----------------------------------------------------------------------
                    // DÜZELTME 2: ACF'in DOM Input Alanını Güncelle
                    // ----------------------------------------------------------------------
                    const blockEditor = select('core/block-editor').getBlock(block.clientId);
                    if (blockEditor) {
                        // Bloğun DOM elementini bulmaya çalış
                        const blockDOMElement = $(`#block-${block.clientId}`);
                        
                        // Input alanını bul ve değerini set et
                        const $customIdInput = blockDOMElement.find(`[data-name="${customIdKey}"] input`);
                        
                        if ($customIdInput.length) {
                            $customIdInput.val(newId).trigger('change');
                            // console.log('ACF Input Değeri Güncellendi:', newId);
                        }
                    }
                }
            }
        });
    }

    // 3. Adım: WordPress store'a abone ol
    wp.domReady(function() {
        if (typeof wp.data !== 'undefined' && typeof wp.data.subscribe === 'function') {
            // Abone ol ve blok listesi değiştiğinde çalıştır
            wp.data.subscribe(checkAndFixDuplicateIds);
        }
    });

})(jQuery);
</script>
<?php
});