<?php
/**
 * Pi Network SDK Demo - Testnet Only
 * One-file implementation with login, auth, cashout, and webhook
 * WARNING: For educational/demo use only on TESTNET
 */

// ============================================
// 1. CONFIGURATION & SDK INCLUSION
// ============================================

// Pi SDK をローカルから読み込む前提（本番想定）
// PiSDK.php を同じディレクトリに配置しておくこと
try {
    if (file_exists('PiSDK.php')) {
        require_once 'PiSDK.php';
        // 本番用：公式 SDK クラス名に合わせてください
        // 例: $pi = new PiSDK(PI_API_KEY, true);
    } else {
        // SDK が無い場合の簡易 Mock（デモ用）
        class MockPiSDK {
            private $apiKey;
            private $testnet = true;

            public function __construct($apiKey) {
                $this->apiKey = $apiKey;
            }

            public function authenticateUser($uid) {
                return [
                    'authenticated' => true,
                    'uid'          => $uid,
                    'testnet'      => true,
                ];
            }

            public function createPayment($amount, $uid, $memo = '') {
                return [
                    'payment_id' => 'test_' . uniqid(),
                    'amount'     => $amount,
                    'status'     => 'pending',
                    'testnet'    => true,
                ];
            }

            public function completePayment($paymentId) {
                return [
                    'completed'   => true,
                    'payment_id'  => $paymentId,
                    'txid'        => 'test_tx_' . rand(1000, 9999),
                    'testnet'     => true,
                ];
            }
        }

        // デモ用 Mock インスタンス
        $pi = new MockPiSDK('test_api_key_demo');
    }
} catch (Exception $e) {
    die('SDK Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

// アプリ設定
define('APP_NAME',        'Pi Freebie Demo');
define('TESTNET_MODE',    true);
define('SESSION_TIMEOUT', 3600); // 1 hour

// 本番では固定値を設定（環境変数などから読み込むのが望ましい）
define('PI_APP_ID',   'YOUR_PI_APP_ID_HERE');
define('PI_API_KEY',  'YOUR_PI_API_KEY_HERE');
define('PI_TEST_MODE', true);

// Testnet API endpoints (example)
define('PI_TESTNET_API',      'https://api.testnet.minepi.com');
define('PI_TESTNET_AUTH',     PI_TESTNET_API . '/auth');
define('PI_TESTNET_PAYMENTS', PI_TESTNET_API . '/payments');

// セッション開始
session_start();

// ============================================
// 2. HELPER FUNCTIONS
// ============================================

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id'], $_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

/**
 * Generate CSRF token
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log action for debugging
 */
function logAction(string $action, array $data = []): void {
    $log = date('Y-m-d H:i:s') . " | $action | " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
    @file_put_contents('pi_demo.log', $log, FILE_APPEND);
}

// ============================================
// 3. SESSION TIMEOUT & CLEANUP (前処理)
// ============================================

// セッションタイムアウト（HTML 出力前にチェック）
if (isLoggedIn() && isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_TIMEOUT) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 古い last_payment をクリーンアップ（24時間以上前）
if (isset($_SESSION['last_payment']['time']) && (time() - $_SESSION['last_payment']['time']) > 86400) {
    unset($_SESSION['last_payment']);
}

// ============================================
// 4. AUTHENTICATION & ACTION HANDLER
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action    = $_POST['action'];
    $csrfToken = $_POST['csrf_token'] ?? '';

    // CSRF チェック
    if (!validateCsrfToken($csrfToken)) {
    //    die('CSRF validation failed');
    }

    switch ($action) {
        case 'login':
            // Simulate Pi Network authentication
            $piUsername = trim($_POST['pi_username'] ?? '');
            $piUid      = trim($_POST['pi_uid'] ?? '');

            if ($piUid === '') {
                $piUid = uniqid('test_uid_');
            }

            if ($piUsername !== '') {
                // Mock authentication - in real app, use Pi SDK
                $_SESSION['user_id']      = $piUid;
                $_SESSION['username']     = $piUsername;
                $_SESSION['authenticated'] = true;
                $_SESSION['login_time']   = time();
                $_SESSION['testnet']      = TESTNET_MODE;

                logAction('login', [
                    'uid'      => $piUid,
                    'username' => $piUsername,
                ]);

                header('Location: ' . $_SERVER['PHP_SELF'] . '?page=dashboard');
                exit;
            }
            break;

        case 'logout':
            session_destroy();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;

        case 'cashout':
            if (!isLoggedIn()) {
                die('Not authenticated');
            }

            $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0.0;
            $uid    = $_SESSION['user_id'];

            if ($amount > 0 && $amount <= 100) { // Testnet limit
                try {
                    $memo = trim($_POST['memo'] ?? '');
                    $paymentData = [
                        'amount'    => $amount,
                        'uid'       => $uid,
                        'memo'      => $memo !== '' ? $memo : ('Testnet cashout from ' . APP_NAME),
                        'timestamp' => time(),
                    ];

                    // 実際には $pi->createPayment(...) を呼ぶ
                    $paymentId = 'test_pay_' . uniqid();

                    $_SESSION['last_payment'] = [
                        'id'     => $paymentId,
                        'amount' => $amount,
                        'status' => 'pending',
                        'time'   => time(),
                    ];

                    logAction('cashout_initiated', $paymentData);

                    $_SESSION['cashout_message'] = "✅ Testnet cashout initiated! Payment ID: {$paymentId}";
                } catch (Exception $e) {
                    $_SESSION['cashout_message'] = '❌ Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                }
            } else {
                $_SESSION['cashout_message'] = '❌ Invalid amount. Testnet limit: 0-100 π';
            }

            header('Location: ' . $_SERVER['PHP_SELF'] . '?page=cashout');
            exit;
    }
}

// ============================================
// 5. WEBHOOK HANDLER (for SDK callbacks)
// ============================================

// このファイルを webhook エンドポイントとしても使う（デモ用）
if (isset($_GET['webhook']) && $_GET['webhook'] === 'pi_callback') {
    $raw = file_get_contents('php://input');
    $webhookData = json_decode($raw, true);

    if (is_array($webhookData)) {
        logAction('webhook_received', $webhookData);

        $type = $webhookData['type'] ?? 'unknown';

        switch ($type) {
            case 'payment_approved':
                $paymentId = $webhookData['payment_id'] ?? '';
                $amount    = $webhookData['amount'] ?? 0;
                logAction('payment_approved', [
                    'payment_id'   => $paymentId,
                    'amount'       => $amount,
                    'processed_at' => time(),
                ]);
                break;

            case 'payment_completed':
                $txid = $webhookData['txid'] ?? '';
                logAction('payment_completed', ['txid' => $txid]);
                break;

            case 'test':
                header('Content-Type: application/json');
                echo json_encode([
                    'status'  => 'ok',
                    'message' => 'Webhook working',
                ]);
                exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['status' => 'received']);
        exit;
    }

    // JSON でない場合も最低限レスポンス
    header('Content-Type: application/json');
    echo json_encode(['status' => 'invalid_payload']);
    exit;
}

// ============================================
// 6. HTML INTERFACE
// ============================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo APP_NAME; ?> - Pi Network Testnet Demo</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 20px;
}
.container {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    overflow: hidden;
}
.header {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: white;
    padding: 30px;
    text-align: center;
}
.header h1 {
    font-size: 2.5rem;
    margin-bottom: 10px;
}
.header .testnet-badge {
    display: inline-block;
    background: #f59e0b;
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
    margin-top: 10px;
}
.content { padding: 30px; }
.tab-nav {
    display: flex;
    border-bottom: 2px solid #e5e7eb;
    margin-bottom: 30px;
}
.tab-btn {
    padding: 15px 30px;
    background: none;
    border: none;
    font-size: 1rem;
    cursor: pointer;
    color: #6b7280;
    transition: all 0.3s;
}
.tab-btn.active {
    color: #4f46e5;
    border-bottom: 3px solid #4f46e5;
    font-weight: 600;
}
.tab-content { display: none; }
.tab-content.active { display: block; }
.form-group { margin-bottom: 20px; }
label {
    display: block;
    margin-bottom: 8px;
    color: #374151;
    font-weight: 500;
}
input, select, textarea {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #d1d5db;
    border-radius: 10px;
    font-size: 1rem;
    transition: border-color 0.3s;
}
input:focus {
    outline: none;
    border-color: #4f46e5;
}
.btn {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    width: 100%;
}
.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(79, 70, 229, 0.4);
}
.btn-secondary { background: #6b7280; }
.alert {
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}
.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}
.alert-warning {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}
.alert-info {
    background: #dbeafe;
    color: #1e40af;
    border: 1px solid #bfdbfe;
}
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.stat-card {
    background: #f9fafb;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    border: 1px solid #e5e7eb;
}
.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #4f46e5;
    margin: 10px 0;
}
.webhook-test {
    background: #f8fafc;
    padding: 20px;
    border-radius: 10px;
    margin-top: 30px;
    border: 2px dashed #cbd5e1;
}
.code-block {
    background: #1e293b;
    color: #e2e8f0;
    padding: 15px;
    border-radius: 10px;
    font-family: 'Courier New', monospace;
    margin: 15px 0;
    overflow-x: auto;
}
.footer {
    text-align: center;
    padding: 20px;
    color: #6b7280;
    font-size: 0.9rem;
    border-top: 1px solid #e5e7eb;
}
@media (max-width: 600px) {
    .container { margin: 10px; }
    .header { padding: 20px; }
    .content { padding: 20px; }
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🪙 <?php echo APP_NAME; ?></h1>
        <p>Pi Network SDK Integration Demo</p>
        <div class="testnet-badge">TESTNET MODE ONLY</div>
    </div>

    <div class="content">
        <?php if (!isLoggedIn()): ?>
            <!-- LOGIN TAB -->
            <div class="tab-content active" id="login">
                <h2>🔐 Pi Network Login</h2>
                <p class="alert alert-info">
                    <strong>Demo Mode:</strong> This simulates Pi Network authentication. In production, use the actual Pi SDK with Pi Browser.
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="login">

                    <div class="form-group">
                        <label for="pi_username">Pi Username (Demo)</label>
                        <input type="text" id="pi_username" name="pi_username" placeholder="Enter demo username" required>
                    </div>

                    <div class="form-group">
                        <label for="pi_uid">Pi UID (Auto-generated if empty)</label>
                        <input type="text" id="pi_uid" name="pi_uid"
                               placeholder="test_uid_<?php echo rand(1000, 9999); ?>">
                    </div>

                    <button type="submit" class="btn">
                        🚀 Login with Pi Network (Demo)
                    </button>
                </form>

                <div class="webhook-test">
                    <h3>🔧 SDK Webhook Test</h3>
                    <p>Test the webhook endpoint:</p>
                    <div class="code-block">
                        curl -X POST <?php echo $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']; ?>?webhook=pi_callback \<br>
                        -H "Content-Type: application/json" \<br>
                        -d '{"type":"test","message":"webhook test"}'
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- USER IS LOGGED IN -->
            <div class="tab-nav">
                <button class="tab-btn active" onclick="switchTab('dashboard')">📊 Dashboard</button>
                <button class="tab-btn" onclick="switchTab('cashout')">💰 Cashout</button>
                <button class="tab-btn" onclick="switchTab('webhook')">🔗 Webhook</button>
                <button class="tab-btn" onclick="switchTab('sdk')">⚙️ SDK Info</button>
            </div>

            <!-- DASHBOARD TAB -->
            <div class="tab-content active" id="dashboard">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?>!</h2>

                <div class="dashboard-stats">
                    <div class="stat-card">
                        <div>👤 User ID</div>
                        <div class="stat-value">
                            <?php echo htmlspecialchars(substr($_SESSION['user_id'], 0, 12) . '...', ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <small>Testnet Account</small>
                    </div>

                    <div class="stat-card">
                        <div>🕒 Session</div>
                        <div class="stat-value">
                            <?php echo floor((time() - ($_SESSION['login_time'] ?? time())) / 60); ?>m
                        </div>
                        <small>Active time</small>
                    </div>

                    <div class="stat-card">
                        <div>🌐 Network</div>
                        <div class="stat-value">Testnet</div>
                        <small>Pi Network</small>
                    </div>

                    <div class="stat-card">
                        <div>🪙 Balance</div>
                        <div class="stat-value">50.0 π</div>
                        <small>Demo Balance</small>
                    </div>
                </div>

                <?php if (isset($_SESSION['last_payment'])): ?>
                    <div class="alert alert-info">
                        <strong>Last Payment:</strong><br>
                        ID: <?php echo htmlspecialchars($_SESSION['last_payment']['id'], ENT_QUOTES, 'UTF-8'); ?><br>
                        Amount: <?php echo (float)$_SESSION['last_payment']['amount']; ?> π<br>
                        Status: <?php echo htmlspecialchars($_SESSION['last_payment']['status'], ENT_QUOTES, 'UTF-8'); ?><br>
                        Time: <?php echo date('H:i:s', $_SESSION['last_payment']['time']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-secondary">🚪 Logout</button>
                </form>
            </div>

            <!-- CASHOUT TAB -->
            <div class="tab-content" id="cashout">
                <h2>💰 Testnet Cashout</h2>

                <?php if (isset($_SESSION['cashout_message'])): ?>
                    <div class="alert alert-success">
                        <?php
                        echo htmlspecialchars($_SESSION['cashout_message'], ENT_QUOTES, 'UTF-8');
                        unset($_SESSION['cashout_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <div class="alert alert-warning">
                    <strong>⚠️ IMPORTANT:</strong> This is TESTNET only. No real π will be transferred. Testnet π has no real value.
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="action" value="cashout">

                    <div class="form-group">
                        <label for="amount">Amount to Cashout (π)</label>
                        <input type="number" id="amount" name="amount"
                               min="0.1" max="100" step="0.1" value="5.0" required>
                        <small>Testnet limit: 0.1 - 100 π</small>
                    </div>

                    <div class="form-group">
                        <label for="memo">Payment Memo (Optional)</label>
                        <input type="text" id="memo" name="memo" placeholder="e.g., Testnet cashout demo">
                    </div>

                    <div class="form-group">
                        <label for="recipient">Recipient UID</label>
                        <input type="text" id="recipient" name="recipient"
                               value="<?php echo htmlspecialchars($_SESSION['user_id'], ENT_QUOTES, 'UTF-8'); ?>"
                               readonly style="background:#f3f4f6;">
                        <small>Currently set to your own UID for testing</small>
                    </div>

                    <button type="submit" class="btn">
                        🚀 Initiate Testnet Cashout
                    </button>
                </form>

                <div class="alert alert-info" style="margin-top: 20px;">
                    <strong>💰 Cashout Flow:</strong><br>
                    1. User initiates cashout → 2. SDK creates payment → 3. Pi App approves
                    → 4. Webhook receives callback → 5. Payment completed
                </div>
            </div>

            <!-- WEBHOOK TAB -->
            <div class="tab-content" id="webhook">
                <h2>🔗 Webhook Configuration</h2>

                <div class="alert alert-info">
                    <strong>Webhook URL for Pi SDK:</strong><br>
                    <div class="code-block">
                        <?php
                        $webhookUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . '?webhook=pi_callback';
                        echo htmlspecialchars($webhookUrl, ENT_QUOTES, 'UTF-8');
                        ?>
                    </div>
                </div>

                <h3>Test Webhook</h3>
                <button class="btn" onclick="testWebhook()">Test Webhook Endpoint</button>
                <div id="webhookResult" style="margin-top: 15px;"></div>

                <h3 style="margin-top: 30px;">Webhook Events</h3>
                <div class="code-block">
                    // Example webhook payload from Pi SDK:
                    {
                        "type": "payment_approved",
                        "payment_id": "pay_123456789",
                        "amount": 5.0,
                        "uid": "<?php echo htmlspecialchars($_SESSION['user_id'], ENT_QUOTES, 'UTF-8'); ?>",
                        "memo": "Test payment",
                        "timestamp": <?php echo time(); ?>

                    }
                </div>

                <h3>Recent Webhook Logs</h3>
                <?php
                if (file_exists('pi_demo.log')) {
                    $logs = array_slice(array_reverse(file('pi_demo.log')), 0, 5);
                    echo '<div class="code-block">';
                    foreach ($logs as $log) {
                        echo htmlspecialchars($log, ENT_QUOTES, 'UTF-8') . '<br>';
                    }
                    echo '</div>';
                }
                ?>
            </div>

            <!-- SDK INFO TAB -->
            <div class="tab-content" id="sdk">
                <h2>⚙️ Pi SDK Integration</h2>

                <div class="alert alert-warning">
                    <strong>Demo Implementation:</strong> This uses a mock SDK for demonstration. For production, use the official Pi SDK.
                </div>

                <h3>SDK Initialization</h3>
                <div class="code-block">
                    // Initialize Pi SDK
                    $pi = new PiSDK([
                        'api_key' => 'YOUR_API_KEY',
                        'testnet' => true, // Set to false for mainnet
                        'app_id'  => 'YOUR_APP_ID',
                    ]);
                </div>

                <h3>Authentication Flow</h3>
                <div class="code-block">
                    // 1. User authenticates via Pi Browser
                    // 2. Pi SDK returns auth code
                    // 3. Exchange code for access token
                    $authData = $pi->authenticateUser($authCode);
                    $uid      = $authData['uid']; // User's Pi Network UID
                </div>

                <h3>Payment Creation</h3>
                <div class="code-block">
                    // Create a payment
                    $payment = $pi->createPayment([
                        'amount'   => 5.0,
                        'memo'     => 'Test payment',
                        'metadata' => ['order_id' => '123'],
                        'uid'      => $userUid,
                    ]);

                    // Get payment ID for tracking
                    $paymentId = $payment['payment_id'];
                </div>

                <h3>Complete Payment</h3>
                <div class="code-block">
                    // After user approves in Pi App
                    $result = $pi->completePayment($paymentId, $txid);

                    if ($result['success']) {
                        // Payment completed on blockchain
                        logTransaction($result['txid']);
                    }
                </div>

                <h3>Required API Endpoints</h3>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li><strong>Authentication:</strong> <?php echo PI_TESTNET_AUTH; ?></li>
                    <li><strong>Payments:</strong> <?php echo PI_TESTNET_PAYMENTS; ?></li>
                    <li><strong>Webhooks:</strong> Your server endpoint</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>🪙 Pi Network Testnet Demo | For educational purposes only</p>
        <p>⚠️ This is NOT a production application. Use only on TESTNET.</p>
        <p>📚 <a href="https://developers.minepi.com" target="_blank">Pi Developer Documentation</a></p>
    </div>
</div>

<script>
function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(function (tab) {
        tab.classList.remove('active');
    });

    // Remove active class from all buttons
    document.querySelectorAll('.tab-btn').forEach(function (btn) {
        btn.classList.remove('active');
    });

    // Show selected tab
    var target = document.getElementById(tabName);
    if (target) {
        target.classList.add('active');
    }

    // Activate clicked button
    if (event && event.target) {
        event.target.classList.add('active');
    }
}

function testWebhook() {
    const resultDiv = document.getElementById('webhookResult');
    resultDiv.innerHTML = '<div class="alert alert-info">Testing webhook...</div>';

    fetch('<?php echo $_SERVER['PHP_SELF']; ?>?webhook=pi_callback', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            type: 'test',
            message: 'Webhook test from frontend',
            timestamp: new Date().toISOString()
        })
    })
        .then(response => response.json())
        .then(data => {
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <strong>✅ Webhook test successful!</strong><br>
                    Response: ${JSON.stringify(data)}
                </div>
            `;
        })
        .catch(error => {
            resultDiv.innerHTML = `
                <div class="alert alert-warning">
                    <strong>❌ Webhook test failed</strong><br>
                    Error: ${error.message}
                </div>
            `;
        });
}

// Auto-switch to cashout/dashboard tab based on ?page=
<?php if (isset($_GET['page']) && $_GET['page'] === 'cashout'): ?>
setTimeout(() => switchTab('cashout'), 100);
<?php endif; ?>

<?php if (isset($_GET['page']) && $_GET['page'] === 'dashboard'): ?>
setTimeout(() => switchTab('dashboard'), 100);
<?php endif; ?>
</script>
</body>
</html>
