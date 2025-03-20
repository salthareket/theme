<?php
class OembedVideo {

    private $url;
    private $image_size;
    private $attrs;

    public function __construct($url = "", $image_size = 0, $attrs = []) {
        $this->url = $url;
        $this->image_size = $image_size;
        $this->attrs = $attrs;
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
            $arr["embed_url"] = "https://geo.dailymotion.com/player.html?video=" . $arr["id"];
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
            $video_id = ltrim($parse['path'], '/');

            if (strpos($video_id, '/') !== false) {
                $video_id = explode('/', $video_id)[0];
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
        preg_match('/<iframe[^>]+title=["\']([^"\']+)["\']/i', $this->url, $matches);
        return $matches[1] ?? null;
    }

    private function poster($video_uri = "", $image_size = 0) {
        if (empty($video_uri)) {
            return '';
        }

        $thumbnail_uri = '';
        $video = $this->parse($video_uri);

        if ($video['type'] == 'youtube') {
            $thumbnail_uri = 'https://img.youtube.com/vi/' . $video['id'] . '/maxresdefault.jpg';
        }

        if ($video['type'] == 'vimeo') {
            $thumbnail_uri = $this->getVimeoThumbnailUri($video['id'], $image_size);
        }

        if ($video['type'] == 'dailymotion') {
            $thumbnail_uri = $this->getDailymotionThumbnailUri($video['id']);
        }

        if (empty($thumbnail_uri)) {
            $thumbnail_uri = '';
        }

        return $thumbnail_uri;
    }

    private function getVimeoThumbnailUri($clip_id = "") {
        $vimeo_api_uri = 'https://vimeo.com/api/oembed.json?url=https%3A//vimeo.com/' . $clip_id;
        $vimeo_response = @file_get_contents($vimeo_api_uri);

        if ($vimeo_response === false) {
            return ''; // API erişim hatası durumunda boş döndür
        } else {
            $vimeo_response = json_decode($vimeo_response);
            if (empty($vimeo_response->thumbnail_url)) {
                return ''; // Thumbnail URL'si yoksa boş döndür
            }

            $url = $vimeo_response->thumbnail_url;

            // Boyut kısımlarını temizle ve orijinal büyük görüntüyü al
            $high_res_url = preg_replace('/-[d_]*\\d+x\\d+$/', '', $url);

            // Büyük görüntü URL'sinin geçerli olup olmadığını kontrol et
            $headers = @get_headers($high_res_url);
            if ($headers && strpos($headers[0], '200') !== false) {
                return $high_res_url; // Geçerliyse büyük çözünürlüklü URL döndür
            }

            return $url; // Büyük URL geçersizse orijinal URL döndür
        }
    }

    private function getDailymotionThumbnailUri($video_id = "") {
        $dailymotion_api = "https://api.dailymotion.com/video/$video_id?fields=thumbnail_720_url,thumbnail_1080_url";
        $response = @file_get_contents($dailymotion_api);

        if ($response === false) {
            return '';
        }

        $data = json_decode($response, true);
        if (isset($data['thumbnail_1080_url'])) {
            return $data['thumbnail_1080_url'];
        } elseif (isset($data['thumbnail_720_url'])) {
            return $data['thumbnail_720_url'];
        }

        return '';
    }
}