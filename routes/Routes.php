<?php

class Routes
{
    private $routes = [];
    private $twig;

    public function __construct($twig)
    {
        $this->twig = $twig;
        $this->initRoutes();
    }

    private function initRoutes()
    {
        // Rota principal
        $this->routes['/'] = ['controller' => 'Controller', 'action' => 'index', 'method' => 'GET'];
        
        // Rota white-page
        $this->routes['/white-page'] = ['controller' => 'Controller', 'action' => 'whitePage', 'method' => 'GET'];
        
        // Rota checkout
        $this->routes['/checkout'] = ['controller' => 'Controller', 'action' => 'checkout', 'method' => 'GET'];
        
        // Rota obrigado
        $this->routes['/obrigado'] = ['controller' => 'Controller', 'action' => 'obrigado', 'method' => 'GET'];
        
        // Rota feed (SPA React)
        $this->routes['/feed'] = ['controller' => 'Controller', 'action' => 'index', 'method' => 'GET'];
        $this->routes['/direct'] = ['controller' => 'Controller', 'action' => 'index', 'method' => 'GET'];
        $this->routes['/notifications'] = ['controller' => 'Controller', 'action' => 'index', 'method' => 'GET'];
        $this->routes['/inicio1'] = ['controller' => 'Controller', 'action' => 'index', 'method' => 'GET'];
        $this->routes['/inicio2'] = ['controller' => 'Controller', 'action' => 'index', 'method' => 'GET'];
        $this->routes['/inicio3'] = ['controller' => 'Controller', 'action' => 'index', 'method' => 'GET'];
        $this->routes['/inicio4'] = ['controller' => 'Controller', 'action' => 'index', 'method' => 'GET'];
        $this->routes['/in-ghostspy'] = ['controller' => 'Controller', 'action' => 'index', 'method' => 'GET'];
        $this->routes['/in-ghostspy/results'] = ['controller' => 'Controller', 'action' => 'index', 'method' => 'GET'];
        $this->routes['/ghostspy-v2'] = ['controller' => 'Controller', 'action' => 'index', 'method' => 'GET'];
        $this->routes['/ghostspy-v2/results'] = ['controller' => 'Controller', 'action' => 'index', 'method' => 'GET'];
        $this->routes['/ghostspy-v2/backredirect'] = ['controller' => 'Controller', 'action' => 'index', 'method' => 'GET'];
        $this->routes['/favicon.png'] = ['controller' => 'Controller', 'action' => 'serveFavicon', 'method' => 'GET'];
         
        // Rota cta (SPA React)
        $this->routes['/cta'] = ['controller' => 'Controller', 'action' => 'index', 'method' => 'GET'];
        
        // Rotas de Autenticação Admin
        $this->routes['/admin'] = ['controller' => 'SecurityController', 'action' => 'loginPage', 'method' => 'GET'];
        $this->routes['/admin/login'] = ['controller' => 'SecurityController', 'action' => 'loginPage', 'method' => 'GET'];
        $this->routes['/admin/logout'] = ['controller' => 'SecurityController', 'action' => 'logout', 'method' => 'GET'];
        
        // Rotas Admin (protegidas)
        $this->routes['/admin/dashboard'] = ['controller' => 'AdminController', 'action' => 'dashboard', 'method' => 'GET'];
        $this->routes['/admin/usuarios'] = ['controller' => 'AdminController', 'action' => 'usuarios', 'method' => 'GET'];
        $this->routes['/admin/transacoes'] = ['controller' => 'AdminController', 'action' => 'transacoes', 'method' => 'GET'];
        $this->routes['/admin/logs'] = ['controller' => 'AdminController', 'action' => 'logs', 'method' => 'GET'];
        $this->routes['/admin/configuracoes'] = ['controller' => 'AdminController', 'action' => 'configuracoes', 'method' => 'GET'];
        $this->routes['/admin/configuracoes/produto'] = ['controller' => 'AdminController', 'action' => 'atualizarProduto', 'method' => 'POST'];
        $this->routes['/admin/configuracoes/gateway'] = ['controller' => 'AdminController', 'action' => 'atualizarGateway', 'method' => 'POST'];
        
        // API - Check status by IP
        $this->routes['/api/leads.php'] = ['controller' => 'LeadsController', 'action' => 'handleRequest', 'method' => 'GET'];
        $this->routes['/api/leads'] = ['controller' => 'LeadsController', 'action' => 'handleRequest', 'method' => 'GET'];
        $this->routes['/api/proxy/leads.php'] = ['controller' => 'LeadsController', 'action' => 'handleRequest', 'method' => 'GET'];
        $this->routes['/api/proxy/leads'] = ['controller' => 'LeadsController', 'action' => 'handleRequest', 'method' => 'GET'];
        $this->routes['/cdn-cgi/rum'] = ['controller' => 'Controller', 'action' => 'cdnCgiRum', 'method' => 'POST'];
        
        // API - Instagram
        $this->routes['/api/instagram.php'] = ['controller' => 'InstagramController', 'action' => 'getProfile', 'method' => 'GET'];
        $this->routes['/api/instagram'] = ['controller' => 'InstagramController', 'action' => 'getProfile', 'method' => 'GET'];
        $this->routes['/api/proxy/instagram.php'] = ['controller' => 'InstagramController', 'action' => 'getProfile', 'method' => 'GET'];
        $this->routes['/api/proxy/instagram'] = ['controller' => 'InstagramController', 'action' => 'getProfile', 'method' => 'GET'];
        $this->routes['/api/proxy/hikerapi.php'] = ['controller' => 'InstagramController', 'action' => 'proxyHiker', 'method' => 'GET'];
        $this->routes['/api/proxy/hikerapi'] = ['controller' => 'InstagramController', 'action' => 'proxyHiker', 'method' => 'GET'];
        $this->routes['/api/proxy/image-proxy.php'] = ['controller' => 'Controller', 'action' => 'proxyImage', 'method' => 'GET'];
        $this->routes['/api/proxy/image-proxy'] = ['controller' => 'Controller', 'action' => 'proxyImage', 'method' => 'GET'];
        $this->routes['/image-proxy.php'] = ['controller' => 'Controller', 'action' => 'proxyImage', 'method' => 'GET'];
        
        // API - Checkout Config
        $this->routes['/api/checkout-config.php'] = ['controller' => 'Controller', 'action' => 'getCheckoutConfig', 'method' => 'GET'];
        $this->routes['/api/checkout-config'] = ['controller' => 'Controller', 'action' => 'getCheckoutConfig', 'method' => 'GET'];
        
        // API - Product Config
        $this->routes['/api/product-config.php'] = ['controller' => 'Controller', 'action' => 'getProductConfig', 'method' => 'GET'];
        $this->routes['/api/product-config'] = ['controller' => 'Controller', 'action' => 'getProductConfig', 'method' => 'GET'];
        
        // API - Payment
        $this->routes['/api/payment/create'] = ['controller' => 'PaymentController', 'action' => 'createDeposit', 'method' => 'POST'];
        $this->routes['/api/payment/status'] = ['controller' => 'PaymentController', 'action' => 'checkStatus', 'method' => 'GET'];
        $this->routes['/api/payment-callback'] = ['controller' => 'PaymentController', 'action' => 'paymentCallback', 'method' => 'POST'];
        
        // Exemplo de outras rotas
        // $this->routes['/login'] = ['controller' => 'AuthController', 'action' => 'login', 'method' => 'GET'];
    }

    public function run()
    {
        $t0 = microtime(true);
        $url = $this->getUrl();
        $method = $_SERVER['REQUEST_METHOD'];
        $logLine = '[' . date('Y-m-d H:i:s') . '] [ROUTE] ' . $method . ' ' . $url . ' | query=' . ($_SERVER['QUERY_STRING'] ?? '');
        @file_put_contents(__DIR__ . '/../log.log', $logLine . "\n", FILE_APPEND);

        // Verifica se é uma requisição de imagem
        if (preg_match('#^/images/(.+)$#', $url)) {
            $controller = new Controller($this->twig);
            $controller->serveImage();
            return;
        }

        if (preg_match('#^/chat/[^/]+$#', $url) && $method === 'GET') {
            $controller = new Controller($this->twig);
            $controller->index();
            return;
        }

        // Tratamento especial para POST de login
        if ($url === '/admin/login' && $method === 'POST') {
            $controller = new SecurityController($this->twig);
            $controller->login();
            return;
        }

        $found = false;
        foreach ($this->routes as $routeUrl => $routeConfig) {
            // Permite GET e POST para rotas de API
            $allowedMethods = [$routeConfig['method']];
            if (strpos($routeUrl, '/api/') === 0 || $routeUrl === '/cdn-cgi/rum') {
                $allowedMethods = ['GET', 'POST'];
            }
            
            if ($url === $routeUrl && in_array($method, $allowedMethods)) {
                $controllerName = $routeConfig['controller'];
                $actionName = $routeConfig['action'];

                if (class_exists($controllerName)) {
                    $controller = new $controllerName($this->twig);
                    if (method_exists($controller, $actionName)) {
                        $controller->$actionName();
                        $found = true;
                        break;
                    }
                }
            }
        }

        if (!$found) {
            $this->handle404();
        }
    }

    private function getUrl()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        $scriptName = str_replace('\\', '/', $scriptName);
        
        // Remove script directory from URI if running in subdirectory
        if ($scriptName !== '/' && $scriptName !== '' && strpos($uri, $scriptName) === 0) {
            $uri = substr($uri, strlen($scriptName));
        }

        if ($uri === '' || $uri === false) {
            $uri = '/';
        } elseif ($uri[0] !== '/') {
            $uri = '/' . $uri;
        }
        
        // Remove trailing slash, exceto para a raiz
        if ($uri !== '/' && substr($uri, -1) === '/') {
            $uri = rtrim($uri, '/');
        }
        
        return $uri;
    }

    private function handle404()
    {
        header("HTTP/1.0 404 Not Found");
        echo $this->twig->render('404.twig');
        exit;
    }
}
