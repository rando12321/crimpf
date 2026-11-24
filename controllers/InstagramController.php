<?php

class InstagramController
{
    private $twig;
    private $userAgent = 'Instagram 76.0.0.15.395 Android (24/7.0; 640dpi; 1440x2560; samsung; SM-G930F; herolte; samsungexynos8890; en_US; 138226743)';
    private $logFile = __DIR__ . '/../log.log';
    
    public function __construct($twig = null)
    {
        $this->twig = $twig;
    }

    private function log($message, $data = null)
    {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[{$timestamp}] [INSTAGRAM] {$message}";
        if ($data !== null) {
            $line .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        @file_put_contents($this->logFile, $line . "\n", FILE_APPEND);
    }

    public function getProfile()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        $tipo = $_GET['tipo'] ?? '';
        $username = $_GET['username'] ?? '';

        if ($tipo === 'busca_completa') {
            $this->getFullSearch();
            return;
        }

        if ($tipo === 'all') {
            $this->getAllData();
            return;
        }

        if ($tipo !== 'perfil' || empty($username)) {
            $this->sendError('Parâmetros inválidos. Use: ?tipo=perfil&username=USERNAME');
            return;
        }

        $username = preg_replace('/[^a-zA-Z0-9._]/', '', $username);
        
        if (empty($username)) {
            $this->sendError('Username inválido');
            return;
        }

        $profileData = $this->scrapeStalkeiaProxyEndpoint('instagram.php', [
            'tipo' => 'perfil',
            'username' => $username
        ]);

        if ($profileData && empty($profileData['error']) && !empty($profileData['username'])) {
            if (!empty($profileData['profile_pic_url'])) {
                $profileData['profile_pic_url'] = $this->proxyImageUrl($profileData['profile_pic_url']);
            }
            if (!empty($profileData['profile_pic_url_hd'])) {
                $profileData['profile_pic_url_hd'] = $this->proxyImageUrl($profileData['profile_pic_url_hd']);
            }
            echo json_encode($profileData);
            exit;
        }

        $profileData = $this->getInstagramProfile($username);

        if ($profileData && !empty($profileData['username'])) {
            echo json_encode($profileData);
        } else {
            echo json_encode($this->buildFallbackProfile($username));
        }

        exit;
    }

    public function proxyHiker()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        $apiPath = $_GET['path'] ?? '';
        $username = preg_replace('/[^a-zA-Z0-9._]/', '', $_GET['username'] ?? '');

        if (empty($apiPath)) {
            $this->sendError('Parâmetro path é obrigatório');
            return;
        }

        $remote = $this->scrapeStalkeiaProxyEndpoint('hikerapi.php', $_GET);
        if ($remote && empty($remote['error'])) {
            echo json_encode($remote);
            exit;
        }

        if (strpos($apiPath, 'user/by/username') !== false && !empty($username)) {
            $profile = $this->scrapeStalkeiaProxyEndpoint('instagram.php', [
                'tipo' => 'perfil',
                'username' => $username
            ]);
            if ($profile && empty($profile['error']) && !empty($profile['username'])) {
                $profile['profile_pic_url'] = $this->proxyImageUrl($profile['profile_pic_url'] ?? '');
                $profile['user_id'] = $profile['pk'] ?? $profile['user_id'] ?? (string) abs(crc32($username));
            } else {
                $profile = $this->getInstagramProfile($username);
            }
            if (!$profile) {
                $profile = $this->buildFallbackProfile($username);
            }
            echo json_encode([
                'user' => [
                    'pk' => $profile['user_id'] ?? $profile['pk'] ?? '',
                    'id' => $profile['user_id'] ?? $profile['pk'] ?? '',
                    'username' => $profile['username'],
                    'full_name' => $profile['full_name'] ?? '',
                    'biography' => $profile['biography'] ?? '',
                    'profile_pic_url' => $profile['profile_pic_url'] ?? '',
                    'profile_pic_url_hd' => $profile['profile_pic_url'] ?? '',
                    'follower_count' => $profile['follower_count'] ?? 0,
                    'following_count' => $profile['following_count'] ?? 0,
                    'media_count' => $profile['media_count'] ?? 0,
                    'is_private' => $profile['is_private'] ?? false,
                    'is_verified' => $profile['is_verified'] ?? false,
                    'external_url' => $profile['external_url'] ?? ''
                ]
            ]);
            exit;
        }

        if (strpos($apiPath, 'medias') !== false) {
            if (!empty($username)) {
                $raw = $this->fetchInstagramRaw($username);
                if ($raw) {
                    echo json_encode(['items' => $raw['posts']]);
                    exit;
                }
            }
            echo json_encode(['items' => []]);
            exit;
        }

        if (strpos($apiPath, 'following') !== false || strpos($apiPath, 'followers') !== false) {
            if (!empty($username)) {
                $raw = $this->fetchInstagramRaw($username);
                if ($raw) {
                    echo json_encode(['response' => ['users' => $raw['related_profiles']]]);
                    exit;
                }
            }
            echo json_encode(['response' => ['users' => []]]);
            exit;
        }

        $this->sendError('Endpoint HikerAPI indisponível localmente');
    }

    public function getAllData()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        $username = preg_replace('/[^a-zA-Z0-9._]/', '', $_GET['username'] ?? '');
        if (empty($username)) {
            $this->sendError('Username é obrigatório');
            return;
        }

        $stalkeiaAll = $this->scrapeStalkeiaProxyEndpoint('instagram.php', [
            'tipo' => 'all',
            'username' => $username
        ]);
        if ($stalkeiaAll && empty($stalkeiaAll['error'])) {
            echo json_encode($this->rewriteImageUrlsRecursive($stalkeiaAll));
            exit;
        }

        $rawData = $this->fetchInstagramRaw($username);
        if (!$rawData || empty($rawData['profile'])) {
            $rawData = [
                'profile' => $this->buildFallbackProfile($username),
                'posts' => [],
                'related_profiles' => []
            ];
        }

        $profile = $rawData['profile'];
        $profiles = $rawData['related_profiles'];
        $posts = $rawData['posts'];

        echo json_encode([
            'success' => true,
            'profile' => $profile,
            'lista_perfis_publicos' => $profiles,
            'followers' => $profiles,
            'chaining_results' => $profiles,
            'posts' => $posts,
            'followers_posts' => $posts,
            'feed_posts' => $posts,
            'error_count' => 0,
            'source' => 'stalkeia_scraper'
        ]);
        exit;
    }

    public function getFullSearch()
    {
        $startTime = microtime(true);
        
        $username = $_GET['username'] ?? '';
        $isPrivate = isset($_GET['is_private']) ? filter_var($_GET['is_private'], FILTER_VALIDATE_BOOLEAN) : false;

        if (empty($username)) {
            $this->sendError('Username é obrigatório');
            return;
        }

        $username = preg_replace('/[^a-zA-Z0-9._]/', '', $username);

        $stalkeiaData = $this->scrapeStalkeiaSite($username, $isPrivate);
        
        if ($stalkeiaData && $stalkeiaData['success']) {
            echo json_encode($this->rewriteImageUrlsRecursive($stalkeiaData['data']));
            exit;
        }

        $stalkeiaAll = $this->scrapeStalkeiaProxyEndpoint('instagram.php', [
            'tipo' => 'all',
            'username' => $username
        ]);
        if ($stalkeiaAll && empty($stalkeiaAll['error'])) {
            echo json_encode($this->rewriteImageUrlsRecursive($stalkeiaAll));
            exit;
        }

        $rawData = $this->fetchInstagramRaw($username);
        
        if (!$rawData || empty($rawData['profile'])) {
            $rawData = [
                'profile' => $this->buildFallbackProfile($username),
                'posts' => [],
                'related_profiles' => []
            ];
        }

        $perfilBuscado = $rawData['profile'];
        $perfisPublicos = $rawData['related_profiles'];
        $posts = $rawData['posts'];

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000);

        $response = [
            'perfil_buscado' => [
                'username' => $perfilBuscado['username'] ?? '',
                'full_name' => $perfilBuscado['full_name'] ?? '',
                'is_private' => $perfilBuscado['is_private'] ?? false,
                'profile_pic_url' => $perfilBuscado['profile_pic_url'] ?? ''
            ],
            'fonte' => 'seguindo_seguidores',
            'fonte_api' => 'stalkeia_scraper',
            'perfis_na_lista' => count($perfisPublicos) + ($isPrivate ? 1 : 0),
            'perfis_publicos' => count($perfisPublicos),
            'lista_perfis_publicos' => $perfisPublicos,
            'posts' => $posts,
            'total_posts' => count($posts),
            'duracao_ms' => $duration
        ];

        echo json_encode($response);
        exit;
    }

    private function rewriteImageUrlsRecursive($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($value) && strpos($value, 'http') === 0 && (strpos($value, 'cdninstagram.com') !== false || strpos($value, 'fbcdn.net') !== false)) {
                    $data[$key] = $this->proxyImageUrl($value);
                } elseif (is_array($value)) {
                    $data[$key] = $this->rewriteImageUrlsRecursive($value);
                }
            }
        }
        return $data;
    }

    private function scrapeStalkeiaProxyEndpoint($endpoint, $params)
    {
        $t0 = microtime(true);
        $url = 'https://stalkeia.ai/api/proxy/' . ltrim($endpoint, '/') . '?' . http_build_query($params);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept: application/json, text/javascript, */*; q=0.01',
                'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
                'Referer: https://stalkeia.ai/',
                'Origin: https://stalkeia.ai',
                'X-Requested-With: XMLHttpRequest'
            ]
        ]);
        $apiResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $ms = round((microtime(true) - $t0) * 1000);
        $this->log('PROXY_STALKEIA', [
            'url' => $url,
            'http_code' => $httpCode,
            'duration_ms' => $ms,
            'curl_error' => $curlError ?: null,
            'response_preview' => $apiResponse ? substr($apiResponse, 0, 200) : null
        ]);
        if ($httpCode === 200 && $apiResponse) {
            $data = json_decode($apiResponse, true);
            if (is_array($data)) {
                return $data;
            }
        }
        return null;
    }

    private function scrapeStalkeiaSite($username, $isPrivate)
    {
        $endpoints = [
            "https://stalkeia.ai/api/proxy/instagram.php?tipo=busca_completa&username=" . urlencode($username) . "&is_private=" . ($isPrivate ? 'true' : 'false'),
            "https://stalkeia.ai/api/instagram.php?tipo=busca_completa&username=" . urlencode($username) . "&is_private=" . ($isPrivate ? 'true' : 'false')
        ];

        foreach ($endpoints as $url) {
            try {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_ENCODING => 'gzip, deflate',
                    CURLOPT_HTTPHEADER => [
                        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                        'Accept: application/json, text/javascript, */*; q=0.01',
                        'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
                        'Referer: https://stalkeia.ai/',
                        'Origin: https://stalkeia.ai',
                        'X-Requested-With: XMLHttpRequest'
                    ]
                ]);

                $apiResponse = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);

                if (!$curlError && $httpCode === 200 && $apiResponse) {
                    $data = json_decode($apiResponse, true);
                    if (is_array($data) && !isset($data['error'])) {
                        if (isset($data['perfil_buscado']) || isset($data['posts']) || isset($data['lista_perfis_publicos'])) {
                            return [
                                'success' => true,
                                'data' => $data
                            ];
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Exceção no scraping stalkeia.ai: " . $e->getMessage());
            }
        }

        return ['success' => false];
    }

    private function fetchInstagramRaw($username)
    {
        $url = "https://www.instagram.com/api/v1/users/web_profile_info/?username=" . urlencode($username);
        $response = $this->makeInstagramRequest($url);

        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['data']['user'])) {
                $user = $data['data']['user'];
                $profilePic = $this->proxyImageUrl($user['profile_pic_url_hd'] ?? $user['profile_pic_url'] ?? '');
                $profile = [
                    'username' => $user['username'] ?? $username,
                    'full_name' => $user['full_name'] ?? '',
                    'biography' => $user['biography'] ?? '',
                    'profile_pic_url' => $profilePic,
                    'follower_count' => $user['edge_followed_by']['count'] ?? 0,
                    'following_count' => $user['edge_follow']['count'] ?? 0,
                    'media_count' => $user['edge_owner_to_timeline_media']['count'] ?? 0,
                    'is_private' => $user['is_private'] ?? false,
                    'is_verified' => $user['is_verified'] ?? false,
                    'user_id' => $user['id'] ?? '',
                    'external_url' => $user['external_url'] ?? '',
                    '_chaining_results' => []
                ];

                $posts = [];
                $mediaEdges = $user['edge_owner_to_timeline_media']['edges'] ?? [];
                foreach ($mediaEdges as $edge) {
                    $node = $edge['node'] ?? null;
                    if (!$node) {
                        continue;
                    }
                    $caption = $node['edge_media_to_caption']['edges'][0]['node']['text'] ?? '';
                    $posts[] = [
                        'id' => $node['id'] ?? '',
                        'shortcode' => $node['shortcode'] ?? '',
                        'image_url' => $this->proxyImageUrl($node['display_url'] ?? $node['thumbnail_src'] ?? ''),
                        'caption' => $caption,
                        'likes_count' => $node['edge_liked_by']['count'] ?? $node['edge_media_preview_like']['count'] ?? 0,
                        'comments_count' => $node['edge_media_to_comment']['count'] ?? 0,
                        'taken_at' => $node['taken_at_timestamp'] ?? time(),
                        'is_video' => $node['is_video'] ?? false,
                        'username' => $profile['username'],
                        'full_name' => $profile['full_name'],
                        'profile_pic_url' => $profilePic
                    ];
                }

                $relatedProfiles = [];
                $relatedEdges = $user['edge_related_profiles']['edges'] ?? [];
                foreach ($relatedEdges as $edge) {
                    $node = $edge['node'] ?? null;
                    if (!$node || empty($node['username'])) {
                        continue;
                    }
                    $relatedProfiles[] = [
                        'username' => $node['username'],
                        'full_name' => $node['full_name'] ?? '',
                        'is_private' => $node['is_private'] ?? false,
                        'is_verified' => $node['is_verified'] ?? false,
                        'profile_pic_url' => $this->proxyImageUrl($node['profile_pic_url'] ?? '')
                    ];
                }

                return [
                    'profile' => $profile,
                    'posts' => $posts,
                    'related_profiles' => $relatedProfiles
                ];
            }
        }

        $htmlProfile = $this->scrapeInstagramHtmlProfile($username);
        if ($htmlProfile) {
            return [
                'profile' => $htmlProfile,
                'posts' => [],
                'related_profiles' => []
            ];
        }

        return null;
    }

    private function scrapeInstagramHtmlProfile($username)
    {
        $url = "https://www.instagram.com/" . urlencode($username) . "/";
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7'
            ]
        ]);
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200 || empty($html)) {
            return null;
        }

        $fullName = '';
        if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $title = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            if (preg_match('/^(.+?)\s*\(@/u', $title, $nm)) {
                $fullName = trim($nm[1]);
            }
        }

        $profilePic = '';
        if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $profilePic = $this->proxyImageUrl(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }

        if (empty($fullName) && empty($profilePic)) {
            return null;
        }

        $isPrivate = (bool) preg_match('/"is_private"\s*:\s*true/i', $html);

        return [
            'username' => $username,
            'full_name' => $fullName,
            'biography' => '',
            'profile_pic_url' => $profilePic,
            'follower_count' => 0,
            'following_count' => 0,
            'media_count' => 0,
            'is_private' => $isPrivate,
            'is_verified' => false,
            'user_id' => '',
            'external_url' => '',
            '_chaining_results' => []
        ];
    }

    private function buildFallbackProfile($username)
    {
        return [
            'username' => $username,
            'full_name' => $username,
            'biography' => '',
            'profile_pic_url' => '/images/avatars/perfil-sem-foto.jpeg',
            'follower_count' => 0,
            'following_count' => 0,
            'media_count' => 0,
            'is_private' => false,
            'is_verified' => false,
            'user_id' => (string) abs(crc32($username)),
            'external_url' => '',
            '_chaining_results' => []
        ];
    }

    private function getInstagramProfile($username)
    {
        $raw = $this->fetchInstagramRaw($username);
        return $raw ? $raw['profile'] : null;
    }

    private function makeInstagramRequest($url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_HTTPHEADER => [
                'User-Agent: ' . $this->userAgent,
                'Accept: */*',
                'Accept-Language: en-US,en;q=0.9',
                'X-IG-App-ID: 936619743392459',
                'X-ASBD-ID: 129477',
                'X-IG-WWW-Claim: 0',
                'X-Requested-With: XMLHttpRequest',
                'Sec-Fetch-Dest: empty',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Site: same-origin'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode === 200 && $response) {
            return $response;
        }

        return null;
    }

    private function proxyImageUrl($url)
    {
        if (empty($url)) {
            return '';
        }

        $url = str_replace('\\/', '/', $url);
        
        // Limpar URLs que podem estar encapsuladas em proxies antigos
        if (preg_match('/proxt-insta\.projetinho-solo\.workers\.dev\/\?url=(.+)$/i', $url, $matches)) {
            $url = urldecode($matches[1]);
        }
        
        // Se ainda tem encoding duplo, decodificar
        if (strpos($url, '%3A%2F%2F') !== false) {
            $url = urldecode($url);
        }

        // Se já está usando o proxy local, retornar como está
        if (strpos($url, 'image-proxy.php') !== false) {
            return $url;
        }

        // Se é uma URL do Instagram, usar o proxy local
        if (strpos($url, 'http') === 0 && (strpos($url, 'cdninstagram.com') !== false || strpos($url, 'fbcdn.net') !== false)) {
            return "/image-proxy.php?url=" . urlencode($url);
        }

        return $url;
    }

    private function sendError($message)
    {
        echo json_encode([
            'error' => true,
            'message' => $message
        ]);
        exit;
    }
}
