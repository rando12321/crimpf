<?php
class SecurityController extends Controller
{
    private $db;
    public function __construct($twig)
    {
        parent::__construct($twig);
        $this->db = Database::getInstance()->getConnection();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    public function loginPage()
    {
        if ($this->isAuthenticated()) {
            header('Location: /admin/dashboard');
            exit;
        }
        $this->view('admin/login', [
            'pageTitle' => 'Login Admin',
            'error' => $_SESSION['login_error'] ?? null
        ]);
        unset($_SESSION['login_error']);
    }
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/login');
            exit;
        }
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        if (empty($username) || empty($password)) {
            $_SESSION['login_error'] = 'Usuário e senha são obrigatórios';
            header('Location: /admin/login');
            exit;
        }
        $stmt = $this->db->prepare("
            SELECT id, username, email, password, full_name, role, is_active 
            FROM admins 
            WHERE (username = ? OR email = ?) AND is_active = 1
        ");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify($password, $admin['password'])) {
            $this->logSecurityEvent(null, 'admin_login_failed', [
                'username' => $username,
                'reason' => 'invalid_credentials'
            ]);
            $_SESSION['login_error'] = 'Usuário ou senha incorretos';
            header('Location: /admin/login');
            exit;
        }
        $stmt = $this->db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$admin['id']]);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_name'] = $admin['full_name'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['admin_logged_in'] = true;
        if ($remember) {
            $this->createPersistentSession($admin['id']);
        }
        $this->logSecurityEvent($admin['id'], 'admin_login_success', [
            'username' => $admin['username']
        ]);
        header('Location: /admin/dashboard');
        exit;
    }
    public function logout()
    {
        $adminId = $_SESSION['admin_id'] ?? null;
        if (isset($_COOKIE['admin_session'])) {
            $this->removePersistentSession($_COOKIE['admin_session']);
            setcookie('admin_session', '', time() - 3600, '/', '', false, true);
        }
        if ($adminId) {
            $this->logSecurityEvent($adminId, 'admin_logout', []);
        }
        session_destroy();
        $_SESSION = [];
        header('Location: /admin/login');
        exit;
    }
    public function isAuthenticated()
    {
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            return true;
        }
        if (isset($_COOKIE['admin_session'])) {
            return $this->validatePersistentSession($_COOKIE['admin_session']);
        }
        return false;
    }
    public function requireAuth()
    {
        if (!$this->isAuthenticated()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: /admin/login');
            exit;
        }
    }
    private function createPersistentSession($adminId)
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        $stmt = $this->db->prepare("
            INSERT INTO admin_sessions (admin_id, session_token, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $adminId,
            $token,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expiresAt
        ]);
        setcookie('admin_session', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
    }
    private function validatePersistentSession($token)
    {
        $stmt = $this->db->prepare("
            SELECT s.admin_id, a.username, a.email, a.full_name, a.role
            FROM admin_sessions s
            JOIN admins a ON s.admin_id = a.id
            WHERE s.session_token = ? 
            AND s.expires_at > NOW()
            AND a.is_active = 1
        ");
        $stmt->execute([$token]);
        $session = $stmt->fetch();
        if ($session) {
            $_SESSION['admin_id'] = $session['admin_id'];
            $_SESSION['admin_username'] = $session['username'];
            $_SESSION['admin_email'] = $session['email'];
            $_SESSION['admin_name'] = $session['full_name'];
            $_SESSION['admin_role'] = $session['role'];
            $_SESSION['admin_logged_in'] = true;
            return true;
        }
        return false;
    }
    private function removePersistentSession($token)
    {
        $stmt = $this->db->prepare("DELETE FROM admin_sessions WHERE session_token = ?");
        $stmt->execute([$token]);
    }
    private function logSecurityEvent($adminId, $event, $details = [])
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO security_logs (user_id, event, ip_address, user_agent, details)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $adminId,
                $event,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                json_encode($details)
            ]);
        } catch (Exception $e) {
            error_log("Erro ao registrar log de segurança: " . $e->getMessage());
        }
    }
    public function getCurrentAdmin()
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        return [
            'id' => $_SESSION['admin_id'] ?? null,
            'username' => $_SESSION['admin_username'] ?? null,
            'email' => $_SESSION['admin_email'] ?? null,
            'name' => $_SESSION['admin_name'] ?? null,
            'role' => $_SESSION['admin_role'] ?? null
        ];
    }
}
