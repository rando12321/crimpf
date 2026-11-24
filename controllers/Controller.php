<?php
class Controller
{
    protected $twig;
    public function __construct($twig)
    {
        $this->twig = $twig;
    }
    public function index()
    {
        $this->view('index', ['title' => 'GhostSpy | Acesse Agora']);
    }
    public function whitePage()
    {
        $this->view('white-page', ['title' => 'GhostSpy | White Page']);
    }
    public function checkout()
    {
        $this->view('checkout', ['title' => 'Checkout Seguro']);
    }
    public function obrigado()
    {
        $this->view('obrigado', ['title' => 'Pagamento Confirmado']);
    }
    public function getCheckoutConfig()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT config_key, config_value FROM cfx WHERE config_key IN ('checkout_type', 'checkout_external_url')");
            $configs = $stmt->fetchAll();
            $config = [
                'checkout_type' => 'external',
                'checkout_external_url' => 'https://pay.stalkeia-ai.cloud/checkout?product=c462ce2a-9210-11f1-a1eb-46da4690ad53'
            ];
            foreach ($configs as $row) {
                $config[$row['config_key']] = $row['config_value'];
            }
            echo json_encode($config);
        } catch (Exception $e) {
            echo json_encode([
                'checkout_type' => 'external',
                'checkout_external_url' => 'https://pay.stalkeia-ai.cloud/checkout?product=c462ce2a-9210-11f1-a1eb-46da4690ad53'
            ]);
        }
        exit;
    }
    public function getProductConfig()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("
                SELECT config_key, config_value 
                FROM cfx 
                WHERE config_key IN ('product_name', 'product_price_original', 'product_price_final', 'product_discount_percent')
            ");
            $configs = $stmt->fetchAll();
            $config = [
                'product_name' => 'Método Completo de Marketing Digital',
                'product_price_original' => '497.00',
                'product_price_final' => '197.00',
                'product_discount_percent' => '60'
            ];
            foreach ($configs as $row) {
                $config[$row['config_key']] = $row['config_value'];
            }
            echo json_encode($config);
        } catch (Exception $e) {
            echo json_encode([
                'product_name' => 'Método Completo de Marketing Digital',
                'product_price_original' => '497.00',
                'product_price_final' => '197.00',
                'product_discount_percent' => '60'
            ]);
        }
        exit;
    }
    protected function view($template, $data = [])
    {
        echo $this->twig->render($template . '.twig', $data);
    }
    public function serveImage()
    {
        $requestUri = $_SERVER['REQUEST_URI'];
        $path = parse_url($requestUri, PHP_URL_PATH);
        $relativePath = preg_replace('#^/images/#', '', $path);
        $relativePath = ltrim(str_replace(['..', "\0"], '', $relativePath), '/');
        if ($relativePath === '') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid image path']);
            return;
        }
        $publicImagesDir = __DIR__ . '/../public/images';
        $localPath = $publicImagesDir . '/' . $relativePath;
        $localDir = dirname($localPath);
        if (file_exists($localPath) && is_file($localPath)) {
            $this->outputImage($localPath);
            return;
        }
        $mirrorPath = __DIR__ . '/../stalkeia.ai/images/' . $relativePath;
        if (file_exists($mirrorPath) && is_file($mirrorPath)) {
            if (!is_dir($localDir)) {
                mkdir($localDir, 0755, true);
            }
            copy($mirrorPath, $localPath);
            $this->outputImage($mirrorPath);
            return;
        }
        $remoteUrls = [
            'https://stalkeia.ai/images/' . $relativePath,
            'https://ghostspy.website/images/' . $relativePath
        ];
        foreach ($remoteUrls as $remoteUrl) {
            $imageData = $this->downloadImage($remoteUrl);
            if ($imageData) {
                if (!is_dir($localDir)) {
                    mkdir($localDir, 0755, true);
                }
                if (file_put_contents($localPath, $imageData)) {
                    $this->outputImage($localPath);
                    return;
                }
            }
        }
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Image not found']);
    }
    public function serveFavicon()
    {
        $candidates = [
            __DIR__ . '/../public/favicon.png',
            __DIR__ . '/../stalkeia.ai/favicon.png'
        ];
        foreach ($candidates as $filePath) {
            if (file_exists($filePath) && is_file($filePath)) {
                $this->outputImage($filePath);
                return;
            }
        }
        http_response_code(404);
        exit;
    }
    public function proxyImage()
    {
        require __DIR__ . '/../public/image-proxy.php';
        exit;
    }
    public function cdnCgiRum()
    {
        http_response_code(204);
        exit;
    }
    private function downloadImage($url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
                'Referer: https://stalkeia.ai/',
                'Sec-Fetch-Dest: image',
                'Sec-Fetch-Mode: no-cors',
                'Sec-Fetch-Site: same-origin'
            ]
        ]);
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($httpCode === 200 && $imageData && strpos($contentType, 'image/') === 0) {
            return $imageData;
        }
        return false;
    }
    private function outputImage($filePath)
    {
        $mimeType = mime_content_type($filePath);
        $fileSize = filesize($filePath);
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: public, max-age=31536000'); // Cache por 1 ano
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        readfile($filePath);
        exit;
    }
}
