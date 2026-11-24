<?php
class AdminController extends Controller
{
    private $db;
    private $security;
    public function __construct($twig)
    {
        parent::__construct($twig);
        $this->db = Database::getInstance()->getConnection();
        $this->security = new SecurityController($twig);
        $this->security->requireAuth();
    }
    public function dashboard()
    {
        $stats = $this->getStats();
        $recentLeads = $this->getRecentLeads(5);
        $this->view('admin/dashboard', [
            'pageTitle' => 'Dashboard',
            'stats' => $stats,
            'recent_leads' => $recentLeads,
            'config' => $this->getConfig(),
            'current_admin' => $this->security->getCurrentAdmin()
        ]);
    }
    public function usuarios()
    {
        $stmt = $this->db->query("
            SELECT 
                lead_id,
                username,
                full_name,
                profile_pic_url,
                follower_count,
                following_count,
                is_private,
                created_at,
                COUNT(*) as search_count
            FROM leads
            GROUP BY username
            ORDER BY created_at DESC
        ");
        $users = $stmt->fetchAll();
        $this->view('admin/usuarios', [
            'pageTitle' => 'Leads',
            'users' => $users,
            'config' => $this->getConfig(),
            'current_admin' => $this->security->getCurrentAdmin()
        ]);
    }
    public function transacoes()
    {
        $stmt = $this->db->query("
            SELECT 
                p.id,
                p.transaction_id,
                p.status,
                p.amount,
                p.created_at,
                p.paid_at,
                l.username,
                l.full_name,
                l.profile_pic_url
            FROM pagamentos p
            LEFT JOIN leads l ON p.lead_id = l.lead_id
            ORDER BY p.created_at DESC
        ");
        $transacoes = $stmt->fetchAll();
        $this->view('admin/transacoes', [
            'pageTitle' => 'Transações',
            'transacoes' => $transacoes,
            'config' => $this->getConfig(),
            'current_admin' => $this->security->getCurrentAdmin()
        ]);
    }
    public function logs()
    {
        $stmt = $this->db->query("
            SELECT id, user_id, event, ip_address, user_agent, details, created_at
            FROM security_logs
            ORDER BY created_at DESC
            LIMIT 100
        ");
        $logs = $stmt->fetchAll();
        $this->view('admin/logs', [
            'pageTitle' => 'Logs',
            'logs' => $logs,
            'config' => $this->getConfig(),
            'current_admin' => $this->security->getCurrentAdmin()
        ]);
    }
    public function configuracoes()
    {
        $this->view('admin/configuracoes', [
            'pageTitle' => 'Configurações',
            'config' => $this->getConfig(),
            'current_admin' => $this->security->getCurrentAdmin()
        ]);
    }
    public function atualizarGateway()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/configuracoes');
            exit;
        }
        $clientId = $_POST['gateway_client_id'] ?? '';
        $clientSecret = $_POST['gateway_client_secret'] ?? '';
        $checkoutType = $_POST['checkout_type'] ?? 'external';
        $checkoutExternalUrl = $_POST['checkout_external_url'] ?? '';
        try {
            $stmt = $this->db->prepare("
                INSERT INTO cfx (config_key, config_value, config_type, description) 
                VALUES ('gateway_client_id', ?, 'text', 'Client ID do Gateway de Pagamento')
                ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW()
            ");
            $stmt->execute([$clientId, $clientId]);
            $stmt = $this->db->prepare("
                INSERT INTO cfx (config_key, config_value, config_type, description) 
                VALUES ('gateway_client_secret', ?, 'text', 'Client Secret do Gateway de Pagamento')
                ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW()
            ");
            $stmt->execute([$clientSecret, $clientSecret]);
            $stmt = $this->db->prepare("
                INSERT INTO cfx (config_key, config_value, config_type, description) 
                VALUES ('checkout_type', ?, 'text', 'Tipo de checkout: external ou internal')
                ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW()
            ");
            $stmt->execute([$checkoutType, $checkoutType]);
            $stmt = $this->db->prepare("
                INSERT INTO cfx (config_key, config_value, config_type, description) 
                VALUES ('checkout_external_url', ?, 'text', 'URL do checkout externo')
                ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW()
            ");
            $stmt->execute([$checkoutExternalUrl, $checkoutExternalUrl]);
            header('Location: /admin/configuracoes?success=1');
        } catch (Exception $e) {
            error_log("Erro ao salvar configurações do gateway: " . $e->getMessage());
            header('Location: /admin/configuracoes?error=1');
        }
        exit;
    }
    public function atualizarProduto()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/configuracoes');
            exit;
        }
        $productName = $_POST['product_name'] ?? '';
        $priceOriginal = $_POST['product_price_original'] ?? '497.00';
        $priceFinal = $_POST['product_price_final'] ?? '197.00';
        $discountPercent = $_POST['product_discount_percent'] ?? '60';
        try {
            $stmt = $this->db->prepare("
                INSERT INTO cfx (config_key, config_value, config_type, description) 
                VALUES ('product_name', ?, 'text', 'Nome do produto')
                ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW()
            ");
            $stmt->execute([$productName, $productName]);
            $stmt = $this->db->prepare("
                INSERT INTO cfx (config_key, config_value, config_type, description) 
                VALUES ('product_price_original', ?, 'number', 'Preço original do produto')
                ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW()
            ");
            $stmt->execute([$priceOriginal, $priceOriginal]);
            $stmt = $this->db->prepare("
                INSERT INTO cfx (config_key, config_value, config_type, description) 
                VALUES ('product_price_final', ?, 'number', 'Preço final do produto')
                ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW()
            ");
            $stmt->execute([$priceFinal, $priceFinal]);
            $stmt = $this->db->prepare("
                INSERT INTO cfx (config_key, config_value, config_type, description) 
                VALUES ('product_discount_percent', ?, 'number', 'Porcentagem de desconto')
                ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW()
            ");
            $stmt->execute([$discountPercent, $discountPercent]);
            header('Location: /admin/configuracoes?success=1');
        } catch (Exception $e) {
            error_log("Erro ao salvar configurações do produto: " . $e->getMessage());
            header('Location: /admin/configuracoes?error=1');
        }
        exit;
    }
    private function getStats()
    {
        $stats = [
            'leads' => 0,
            'qrcodes_gerados' => 0,
            'qrcodes_pagos' => 0,
            'logs' => 0
        ];
        $stmt = $this->db->query("SELECT COUNT(DISTINCT username) as count FROM leads");
        $stats['leads'] = $stmt->fetch()['count'];
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM pagamentos");
        $stats['qrcodes_gerados'] = $stmt->fetch()['count'];
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM pagamentos WHERE status = 'paid'");
        $stats['qrcodes_pagos'] = $stmt->fetch()['count'];
        $stmt = $this->db->query("
            SELECT COUNT(*) as count 
            FROM security_logs 
            WHERE DATE(created_at) = CURDATE()
        ");
        $stats['logs'] = $stmt->fetch()['count'];
        return $stats;
    }
    private function getRecentLeads($limit = 5)
    {
        $stmt = $this->db->prepare("
            SELECT 
                lead_id,
                username,
                full_name,
                profile_pic_url,
                follower_count,
                is_private,
                created_at
            FROM leads
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    private function getConfig()
    {
        $config = [
            'site_name' => 'Dashboard',
            'primary_color' => '#A8D8EA',
            'logo' => null,
            'favicon' => null,
            'allow_registration' => 'true',
            'maintenance_mode' => 'false',
            'email_verification' => 'false',
            'max_platforms' => 5,
            'gateway_client_id' => '',
            'gateway_client_secret' => ''
        ];
        try {
            $stmt = $this->db->query("SELECT config_key, config_value FROM cfx");
            $dbConfigs = $stmt->fetchAll();
            foreach ($dbConfigs as $row) {
                $config[$row['config_key']] = $row['config_value'];
            }
        } catch (Exception $e) {
            error_log("Erro ao buscar configurações: " . $e->getMessage());
        }
        return $config;
    }
}
