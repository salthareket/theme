<?php
class OembedVideo {

    private $url;
    private $image_size;
    private $attrs;
    private $api;
    private $type;
    private $id;
    private $prefix = "";

    public function __construct($url = "", $image_size = 0, $attrs = []) {
        $this->url = $url;
        $this->image_size = $image_size;
        $this->attrs = $attrs;
        $this->api = [];
        $this->type = "";
        $this->id = "";
        $this->prefix = "poster-";
    }

    public function get($attrs=[]) {
        if (empty($this->url)) {
            return false;
        }
        $url = extract_url($this->url);
        $data_parse = $this->parse($url);

        if (!$data_parse) {
            return false;
        }

        $this->type = $data_parse['type'];
        $this->id = $data_parse['id'];

        $autoplay = empty($attrs["autoplay"])?"0":"1";
        $muted = empty($attrs["muted"])?"0":"1";

        $arr = [
            'type' => $data_parse['type'],
            'id' => str_replace('video/', '', $data_parse['id']),
            'url' => $url . "&controls=0",
            'embed' => $this->url,
            'embed_url' => "https://www.youtube-nocookie.com/embed/" . $data_parse['id'] . "?rel=0&showinfo=0&autoplay=".$autoplay."&mute=".$muted,
            'watch' => "https://www." . ($data_parse['type'] == "vimeo" ? "vimeo.com/" : "youtube.com/watch?v=") . $data_parse['id'],
            'src' => $this->poster($url, $this->image_size),
            "title" => $this->title()
        ];

        if ($arr["type"] == "vimeo") {
            $arr["embed_url"] = "https://player.vimeo.com/video/" . $arr["id"] . "?autoplay=".$autoplay."&title=0&byline=0&portrait=0"."&mute=".$muted;
        }

        if ($arr["type"] == "dailymotion") {
            $arr["url"] = "https://geo.dailymotion.com/player.html?video=" . $arr["id"];
            $arr["embed_url"] ="https://geo.dailymotion.com/player.html?autoplay=".$autoplay."&ui-logo=0&ui-start-screen-info=0&startTime=0&mute=".$muted."&video=" . $arr["id"];
        }

        return $arr;
    }

    private function parse($url = "") {
        if (empty($url)) {
            return false;
        }

        $parse = parse_url($url);
        if (!isset($parse['host'])) {
            return false;
        }

        $host = str_replace('www.', '', $parse['host']);
        $video_type = '';
        $video_id = '';

        if ($host === 'youtu.be') {
            $video_type = 'youtube';
            $video_id = ltrim($parse['path'], '/');
        } elseif ($host === 'youtube.com' || $host === 'm.youtube.com') {
            $video_type = 'youtube';

            if (!empty($parse['query'])) {
                parse_str($parse['query'], $output);
                if (!empty($output['v'])) {
                    $video_id = $output['v'];
                }
            }

            if (strpos($parse['path'], '/embed/') !== false) {
                $video_id = explode('/', $parse['path']);
                $video_id = end($video_id);
            }

            if (strpos($parse['path'], '/shorts/') !== false) {
                $video_id = explode('/', $parse['path']);
                $video_id = end($video_id);
            }
        } elseif (in_array($host, ['vimeo.com', 'player.vimeo.com'])) {
            $video_type = 'vimeo';

            $pattern = '/(?:https?:\/\/)?(?:www\.)?(?:player\.)?vimeo\.com\/(?:video\/|channels\/[\w]+\/|groups\/[^\/]+\/videos\/|album\/\d+\/video\/|)(\d+)/i';
            if (preg_match($pattern, $url, $matches)) {
               $video_id = $matches[1];
            }

        } elseif ($host === 'dailymotion.com' || $host === 'www.dailymotion.com' || $host === 'geo.dailymotion.com') {
            $video_type = 'dailymotion';
            if (strpos($parse['path'], '/video/') !== false) {
                $video_id = explode('/video/', $parse['path'])[1];
            } elseif (strpos($parse['path'], '/player.html') !== false && isset($parse['query'])) {
                parse_str($parse['query'], $query_params);
                if (!empty($query_params['video'])) {
                    $video_id = $query_params['video'];
                }
            }
        }

        if (!empty($video_type) && !empty($video_id)) {
            return [
                'type' => $video_type,
                'id' => $video_id
            ];
        }

        return false;
    }

    private function title() {
        $this->getApi();
        return isset($this->api->title) ? $this->api->title : '';
    }

    private function poster($video_uri = "", $image_size = 0) {
        if (empty($video_uri)) {
            return '';
        }

        $video = $this->parse($video_uri);
        if (!$video || empty($video['type']) || empty($video['id'])) {
            return '';
        }

        $meta_key = "{$video["type"]}-{$video["id"]}";
        global $wpdb;

        $attachment_id = $wpdb->get_var( $wpdb->prepare("
            SELECT post_id FROM {$wpdb->postmeta}
            WHERE meta_key = %s
            LIMIT 1
        ", $meta_key));

        // Eğer daha önce yüklenmişse direkt döndür
        if (!empty($attachment_id) && get_post($attachment_id)) {
            return wp_get_attachment_url($attachment_id);
        }

        // İndirilip yüklenmemişse → indir
        switch ($video['type']) {
            case 'youtube':
                $thumbnail_uri = 'https://img.youtube.com/vi/' . $video['id'] . '/maxresdefault.jpg';
                break;
            case 'vimeo':
                $thumbnail_uri = $this->getVimeoThumbnailUri($video['id'], $image_size);
                break;
            case 'dailymotion':
                $thumbnail_uri = $this->getDailymotionThumbnailUri($video['id']);
                break;
            default:
                $thumbnail_uri = '';
        }

        if (!empty($thumbnail_uri)) {
            $attachment_id = featured_image_from_url($thumbnail_uri, 0, false, $meta_key);
            if ($attachment_id) {
                update_post_meta($attachment_id, $meta_key, $attachment_id);
                return wp_get_attachment_url($attachment_id);
            }
            return $thumbnail_uri; // yükleme başarısızsa fallback
        }

        return '';
    }
    private function getVimeoThumbnailUri($clip_id = "", $image_size = "") {
        $this->getApi();
        $url = "";

        if ($this->api) {
            if (empty($this->api->thumbnail_url)) {
                return '';
            }
            $url = $this->api->thumbnail_url;

            // Boyut kısımlarını temizle ve orijinal büyük görüntüyü al
            $high_res_url = preg_replace('/-[d_]*\\d+x\\d+$/', '', $url);

            if(!empty($image_size)){
                $high_res_url .= "-d_".$image_size;
            }

            // Büyük görüntü URL'sinin geçerli olup olmadığını kontrol et
            $headers = @get_headers($high_res_url);
            if ($headers && strpos($headers[0], '200') !== false) {
                return $high_res_url; // Geçerliyse büyük çözünürlüklü URL döndür
            }
        }
        return $url; // Büyük URL geçersizse orijinal URL döndür
    }
    private function getDailymotionThumbnailUri($video_id = "") {
        $this->getApi();
        $url = "";
        if ($this->api) {
            if(isset($this->api->thumbnail_720_url)) {
               $url = $this->api->thumbnail_720_url;
            }
        }
        return $url;
    }

    private function getApi(){
        if(!$this->api){
            if($this->type == "youtube"){
                $url = extract_url($this->url);
                $api_uri = "https://noembed.com/embed?url=" . urlencode($url);
            }elseif($this->type == "vimeo"){
                $api_uri = 'https://vimeo.com/api/oembed.json?url=https%3A//vimeo.com/' . $this->id;
            }elseif($this->type == "dailymotion"){
                $api_uri = "https://api.dailymotion.com/video/".$this->id."?fields=title,thumbnail_720_url";
            }
            $response = @file_get_contents($api_uri);
            if ($response === false) {
               $this->api =  []; // API erişim hatası durumunda boş döndür
            } else {
                $response = json_decode($response);
                $this->api = $response;
            }
        }
    }
}