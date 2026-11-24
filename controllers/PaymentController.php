<?php
class PaymentController extends Controller
{
    private $db;
    private $apiUrl = 'https://api.codexpay.app/api';
    private $logFile = __DIR__ . '/../log.log';
    public function __construct($twig)
    {
        parent::__construct($twig);
        $this->db = Database::getInstance()->getConnection();
    }
    private function log($message, $data = null)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}";
        if ($data !== null) {
            $logMessage .= "\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        $logMessage .= "\n" . str_repeat('-', 80) . "\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
    private function getGatewayCredentials()
    {
        $this->log('CODEXPAY: Buscando credenciais do gateway');
        $stmt = $this->db->query("
            SELECT config_key, config_value 
            FROM cfx 
            WHERE config_key IN ('gateway_client_id', 'gateway_client_secret')
        ");
        $configs = $stmt->fetchAll();
        $credentials = [
            'client_id' => '',
            'client_secret' => ''
        ];
        foreach ($configs as $row) {
            if ($row['config_key'] === 'gateway_client_id') {
                $credentials['client_id'] = $row['config_value'];
            } elseif ($row['config_key'] === 'gateway_client_secret') {
                $credentials['client_secret'] = $row['config_value'];
            }
        }
        $this->log('CODEXPAY: Credenciais obtidas', [
            'client_id' => $credentials['client_id'] ? substr($credentials['client_id'], 0, 10) . '...' : 'vazio',
            'client_secret' => $credentials['client_secret'] ? '***' : 'vazio'
        ]);
        return $credentials;
    }
    private function authenticate()
    {
        $this->log('CODEXPAY: Iniciando autenticação');
        $credentials = $this->getGatewayCredentials();
        if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
            $this->log('CODEXPAY: ERRO - Credenciais não configuradas');
            throw new Exception('Credenciais do gateway não configuradas');
        }
        $url = $this->apiUrl . '/auth/login';
        $payload = [
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret']
        ];
        $this->log('CODEXPAY: Fazendo requisição de autenticação', [
            'url' => $url,
            'client_id' => substr($credentials['client_id'], 0, 10) . '...'
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $this->log('CODEXPAY: Resposta da autenticação', [
            'http_code' => $httpCode,
            'response' => $response
        ]);
        if ($httpCode !== 200) {
            $this->log('CODEXPAY: ERRO - Falha na autenticação', [
                'http_code' => $httpCode,
                'response' => $response
            ]);
            throw new Exception('Falha na autenticação com o gateway');
        }
        $data = json_decode($response, true);
        $token = $data['token'] ?? null;
        if ($token) {
            $this->log('CODEXPAY: Autenticação bem-sucedida', [
                'token' => substr($token, 0, 20) . '...'
            ]);
        } else {
            $this->log('CODEXPAY: ERRO - Token não retornado na resposta');
        }
        return $token;
    }
    public function createDeposit()
        {
            header('Content-Type: application/json');
            $this->log('CODEXPAY: Nova requisição de criação de depósito');
            try {
                $input = json_decode(file_get_contents('php://input'), true);
                $this->log('CODEXPAY: Dados recebidos do cliente', [
                    'name' => $input['name'] ?? 'não informado',
                    'email' => $input['email'] ?? 'não informado',
                    'cpf' => isset($input['cpf']) ? substr($input['cpf'], 0, 3) . '...' : 'não informado',
                    'phone' => $input['phone'] ?? 'não informado',
                    'amount' => $input['amount'] ?? 'não informado'
                ]);
                $required = ['name', 'email', 'cpf', 'phone', 'amount'];
                foreach ($required as $field) {
                    if (empty($input[$field])) {
                        $this->log('CODEXPAY: ERRO - Campo obrigatório faltando: ' . $field);
                        throw new Exception("Campo obrigatório: {$field}");
                    }
                }
                $token = $this->authenticate();
                if (!$token) {
                    $this->log('CODEXPAY: ERRO - Token de autenticação não obtido');
                    throw new Exception('Token de autenticação não obtido');
                }
                $externalId = 'TXN_' . time() . '_' . bin2hex(random_bytes(8));
                $this->log('CODEXPAY: External ID gerado: ' . $externalId);
                $paymentData = [
                    'amount' => floatval($input['amount']),
                    'external_id' => $externalId,
                    'clientCallbackUrl' => 'https://webhook.site/cb986156-b103-4e7b-b246-ced441aade11',
                    'payer' => [
                        'name' => $input['name'],
                        'email' => $input['email'],
                        'document' => preg_replace('/\D/', '', $input['cpf'])
                    ]
                ];
                $this->log('CODEXPAY: Dados do pagamento preparados', [
                    'amount' => $paymentData['amount'],
                    'external_id' => $externalId,
                    'callback_url' => $paymentData['clientCallbackUrl'],
                    'payer_name' => $paymentData['payer']['name'],
                    'payer_email' => $paymentData['payer']['email']
                ]);
                $url = $this->apiUrl . '/payments/deposit';
                $this->log('CODEXPAY: Fazendo requisição de depósito', ['url' => $url]);
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $token
                    ],
                    CURLOPT_POSTFIELDS => json_encode($paymentData)
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $this->log('CODEXPAY: Resposta da criação de depósito', [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
                if ($httpCode !== 200 && $httpCode !== 201) {
                    $this->log('CODEXPAY: ERRO - Falha ao criar pagamento', [
                        'http_code' => $httpCode,
                        'response' => $response
                    ]);
                    throw new Exception('Erro ao criar pagamento: ' . $response);
                }
                $result = json_decode($response, true);
                $transactionId = $result['qrCodeResponse']['transactionId'] ?? 
                                $result['transactionId'] ?? 
                                $externalId;
                $copiaECola = $result['qrCodeResponse']['qrcode'] ?? 
                             $result['qrcode'] ?? 
                             $result['qrCodeResponse']['pixCode'] ?? 
                             $result['pixCode'] ?? 
                             $result['qrCodeResponse']['copia_cola'] ?? 
                             $result['copia_cola'] ?? '';
                $qrCode = '';
                $this->log('CODEXPAY: Salvando transação no banco', [
                    'transaction_id' => $transactionId,
                    'has_copia_cola' => !empty($copiaECola),
                    'copia_cola_length' => strlen($copiaECola),
                    'amount' => $input['amount']
                ]);
                $stmt = $this->db->prepare("
                    INSERT INTO pagamentos (
                        transaction_id, 
                        status, 
                        copia_cola, 
                        qr_code, 
                        amount,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $transactionId,
                    'pending',
                    $copiaECola,
                    $qrCode,
                    $input['amount']
                ]);
                $this->log('CODEXPAY: Transação salva com sucesso no banco');
                $responseData = [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'qr_code' => $copiaECola, // Enviando o código Pix como qr_code para gerar no frontend
                    'copia_cola' => $copiaECola,
                    'amount' => $input['amount'],
                    'debug' => $result
                ];
                $this->log('CODEXPAY: Resposta enviada ao cliente', [
                    'transaction_id' => $transactionId,
                    'has_copia_cola' => !empty($copiaECola)
                ]);
                echo json_encode($responseData);
            } catch (Exception $e) {
                $this->log('CODEXPAY: EXCEÇÃO CAPTURADA', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            exit;
        }
    public function paymentCallback()
    {
        header('Content-Type: application/json');
        $this->log('CODEXPAY: Callback recebido');
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $this->log('CODEXPAY: Dados do callback', $input);
            if (!isset($input['transaction_id']) || !isset($input['status'])) {
                $this->log('CODEXPAY: ERRO - Dados inválidos no callback');
                throw new Exception('Dados inválidos no callback');
            }
            $status = strtolower($input['status']) === 'completed' ? 'paid' : 'pending';
            $this->log('CODEXPAY: Atualizando status no banco', [
                'transaction_id' => $input['transaction_id'],
                'status_recebido' => $input['status'],
                'status_banco' => $status
            ]);
            $stmt = $this->db->prepare("
                UPDATE pagamentos 
                SET status = ?, 
                    paid_at = IF(? = 'paid', NOW(), NULL),
                    updated_at = NOW()
                WHERE transaction_id = ?
            ");
            $stmt->execute([
                $status,
                $status,
                $input['transaction_id']
            ]);
            $this->log('CODEXPAY: Status atualizado com sucesso');
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $this->log('CODEXPAY: ERRO no callback', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
    public function checkStatus()
    {
        header('Content-Type: application/json');
        $this->log('CODEXPAY: Verificação de status solicitada');
        try {
            $transactionId = $_GET['transaction_id'] ?? null;
            $this->log('CODEXPAY: Transaction ID recebido: ' . ($transactionId ?? 'null'));
            if (!$transactionId) {
                $this->log('CODEXPAY: ERRO - Transaction ID não fornecido');
                throw new Exception('Transaction ID não fornecido');
            }
            $stmt = $this->db->prepare("
                SELECT status, amount, paid_at 
                FROM pagamentos 
                WHERE transaction_id = ?
            ");
            $stmt->execute([$transactionId]);
            $payment = $stmt->fetch();
            if (!$payment) {
                $this->log('CODEXPAY: ERRO - Pagamento não encontrado', [
                    'transaction_id' => $transactionId
                ]);
                throw new Exception('Pagamento não encontrado');
            }
            $this->log('CODEXPAY: Status do pagamento', [
                'transaction_id' => $transactionId,
                'status' => $payment['status'],
                'amount' => $payment['amount'],
                'paid_at' => $payment['paid_at']
            ]);
            echo json_encode([
                'success' => true,
                'status' => $payment['status'],
                'amount' => $payment['amount'],
                'paid_at' => $payment['paid_at']
            ]);
        } catch (Exception $e) {
            $this->log('CODEXPAY: ERRO ao verificar status', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
}
