<?php
class LeadsController
{
    private $db = null;
    private $logFile = __DIR__ . '/../log.log';
    public function __construct($twig = null)
    {
    }
    private function log($message, $data = null)
    {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[{$timestamp}] [LEADS] {$message}";
        if ($data !== null) {
            $line .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        @file_put_contents($this->logFile, $line . "\n", FILE_APPEND);
    }
    private function getDb()
    {
        if ($this->db !== null) {
            return $this->db;
        }
        try {
            require_once __DIR__ . '/../database/db.php';
            $this->db = Database::getInstance()->getConnection();
        } catch (Throwable $e) {
            $this->log('DB_ERROR', ['error' => $e->getMessage()]);
            $this->db = null;
        }
        return $this->db;
    }
    private function proxyToStalkeia($queryParams, $method = 'GET', $body = null)
    {
        $t0 = microtime(true);
        $url = 'https://stalkeia.ai/api/proxy/leads.php?' . http_build_query($queryParams);
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                'Accept: application/json, text/plain, */*',
                'Content-Type: application/json',
                'Referer: https://stalkeia.ai/',
                'Origin: https://stalkeia.ai'
            ]
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $body ?? '';
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $ms = round((microtime(true) - $t0) * 1000);
        $this->log('PROXY_STALKEIA', [
            'url' => $url,
            'method' => $method,
            'http_code' => $httpCode,
            'duration_ms' => $ms,
            'curl_error' => $curlError ?: null,
            'response_preview' => $response ? substr($response, 0, 200) : null
        ]);
        if ($httpCode === 200 && $response) {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                return $response;
            }
        }
        return null;
    }
    public function handleRequest()
    {
        $action = $_GET['action'] ?? '';
        if ($action === 'save_search') {
            $this->saveSearch();
        } elseif ($action === 'check_status_by_ip') {
            $this->checkStatusByIp();
        } elseif ($action === 'check_status') {
            $this->checkStatus();
        } else {
            $this->sendError('Ação inválida');
        }
    }
    public function checkStatus()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        $remote = $this->proxyToStalkeia($_GET, 'GET');
        if ($remote !== null) {
            echo $remote;
            exit;
        }
        $leadId = $_GET['lead_id'] ?? '';
        if (empty($leadId) || !$this->getDb()) {
            echo json_encode([
                'success' => true,
                'exists' => false,
                'searchCount' => 0,
                'canSearch' => true
            ]);
            exit;
        }
        $lead = $this->getLeadByLeadId($leadId);
        echo json_encode([
            'success' => true,
            'exists' => $lead ? true : false,
            'searchCount' => $lead ? $lead['search_count'] : 0,
            'canSearch' => true,
            'leadData' => $lead
        ]);
        exit;
    }
    public function checkStatusByIp()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        $remote = $this->proxyToStalkeia($_GET, 'GET');
        if ($remote !== null) {
            echo $remote;
            exit;
        }
        $response = [
            'success' => true,
            'exists' => false,
            'searchCount' => 0,
            'canSearch' => true,
            'blockReason' => null
        ];
        echo json_encode($response);
        exit;
    }
    private function getClientIp()
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return $_SERVER[$key];
            }
        }
        return 'unknown';
    }
    public function saveSearch()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        $input = file_get_contents('php://input');
        $remote = $this->proxyToStalkeia($_GET, 'POST', $input);
        if ($remote !== null) {
            echo $remote;
            exit;
        }
        $data = json_decode($input, true);
        if (!$data) {
            $this->sendError('Payload inválido');
            return;
        }
        $leadId = $data['leadId'] ?? '';
        $fingerprint = $data['fingerprint'] ?? '';
        $ip = $data['ip'] ?? '';
        $profileData = $data['profileData'] ?? [];
        if (empty($leadId) || empty($fingerprint) || empty($ip) || empty($profileData)) {
            $this->sendError('Campos obrigatórios faltando');
            return;
        }
        $existingLead = $this->getLeadByLeadId($leadId);
        if ($existingLead) {
            $newSearchCount = $existingLead['search_count'] + 1;
            $this->updateSearchCount($leadId, $newSearchCount);
            echo json_encode([
                'success' => true,
                'message' => 'Lead atualizado',
                'searchCount' => $newSearchCount
            ]);
        } else {
            $searchCount = $this->createLead($leadId, $fingerprint, $ip, $profileData);
            echo json_encode([
                'success' => true,
                'message' => 'Novo lead criado',
                'searchCount' => $searchCount
            ]);
        }
        exit;
    }
    private function getLeadByLeadId($leadId)
    {
        $db = $this->getDb();
        if (!$db) {
            return null;
        }
        try {
            $stmt = $db->prepare("SELECT * FROM leads WHERE lead_id = ? LIMIT 1");
            $stmt->execute([$leadId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting lead: " . $e->getMessage());
            return null;
        }
    }
    private function updateSearchCount($leadId, $searchCount)
    {
        $db = $this->getDb();
        if (!$db) {
            return false;
        }
        try {
            $stmt = $db->prepare("UPDATE leads SET search_count = ?, updated_at = NOW() WHERE lead_id = ?");
            return $stmt->execute([$searchCount, $leadId]);
        } catch (PDOException $e) {
            error_log("Error updating search count: " . $e->getMessage());
            return false;
        }
    }
    private function createLead($leadId, $fingerprint, $ip, $profileData)
    {
        $db = $this->getDb();
        if (!$db) {
            return 1;
        }
        try {
            $username = $profileData['username'] ?? '';
            $fullName = $profileData['full_name'] ?? '';
            $profilePicUrl = $profileData['profile_pic_url'] ?? '';
            $followerCount = $profileData['follower_count'] ?? 0;
            $followingCount = $profileData['following_count'] ?? 0;
            $mediaCount = $profileData['media_count'] ?? 0;
            $isPrivate = isset($profileData['is_private']) ? (int)$profileData['is_private'] : 0;
            $biography = $profileData['biography'] ?? '';
            $profileDataJson = json_encode($profileData);
            $stmt = $db->prepare("
                INSERT INTO leads (
                    lead_id, fingerprint, ip_address, username, full_name, 
                    profile_pic_url, follower_count, following_count, media_count, 
                    is_private, biography, profile_data, search_count
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $leadId,
                $fingerprint,
                $ip,
                $username,
                $fullName,
                $profilePicUrl,
                $followerCount,
                $followingCount,
                $mediaCount,
                $isPrivate,
                $biography,
                $profileDataJson
            ]);
            return 1;
        } catch (PDOException $e) {
            error_log("Error creating lead: " . $e->getMessage());
            return 0;
        }
    }
    private function sendError($message)
    {
        echo json_encode([
            'success' => false,
            'error' => true,
            'message' => $message
        ]);
        exit;
    }
}
