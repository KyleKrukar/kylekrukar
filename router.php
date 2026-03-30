<?php
// Local dev router: serves static files and proxies /api/now-playing to Spotify
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// --- DPLY endpoints ---
if (str_starts_with($uri, '/dply/')) {
    header('Content-Type: application/json');
    $dplyAction = substr($uri, 5);

    if ($dplyAction === '/status') {
        $branch = trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null') ?: '');
        $commit = trim(shell_exec('git rev-parse --short HEAD 2>/dev/null') ?: '');
        $log = shell_exec('git log --oneline -20 --format="%h|%s|%an|%ai" 2>/dev/null') ?: '';
        $changelog = [];
        foreach (array_filter(explode("\n", trim($log))) as $line) {
            $parts = explode('|', $line, 4);
            if (count($parts) === 4) {
                $changelog[] = ['hash' => $parts[0], 'message' => $parts[1], 'author' => $parts[2], 'date' => $parts[3]];
            }
        }
        echo json_encode(['branch' => $branch, 'commit' => $commit, 'changelog' => $changelog]);
        exit;
    }

    if ($dplyAction === '/pull' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $output = shell_exec('git pull 2>&1');
        echo json_encode(['success' => str_contains($output ?: '', 'Already up to date') || str_contains($output ?: '', 'Fast-forward'), 'output' => $output]);
        exit;
    }

    if ($dplyAction === '/deploy' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = getenv('DPLY_GITHUB_TOKEN');
        $owner = getenv('DPLY_GITHUB_OWNER') ?: 'KyleKrukar';
        $repo = getenv('DPLY_GITHUB_REPO') ?: 'kylekrukar';
        $workflow = getenv('DPLY_GITHUB_WORKFLOW') ?: 'deploy.yml';
        $branch = trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null') ?: 'main');

        if (!$token) {
            echo json_encode(['success' => false, 'message' => 'DPLY_GITHUB_TOKEN not set']);
            exit;
        }

        $ch = curl_init("https://api.github.com/repos/$owner/$repo/actions/workflows/$workflow/dispatches");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $token",
                'Accept: application/vnd.github.v3+json',
                'Content-Type: application/json',
                'User-Agent: dply-bar',
            ],
            CURLOPT_POSTFIELDS => json_encode(['ref' => $branch]),
        ]);
        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo json_encode(['success' => $status >= 200 && $status < 300, 'message' => $status >= 200 && $status < 300 ? 'Deploy triggered' : 'Deploy failed (HTTP ' . $status . ')']);
        exit;
    }

    echo json_encode(['error' => 'Unknown dply endpoint']);
    exit;
}

if ($uri === '/api/now-playing') {
    error_reporting(0);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');

    $client_id = getenv('SPOTIFY_CLIENT_ID');
    $client_secret = getenv('SPOTIFY_CLIENT_SECRET');
    $refresh_token = getenv('SPOTIFY_REFRESH_TOKEN');

    if (!$client_id || !$client_secret || !$refresh_token) {
        echo json_encode(['is_playing' => false, 'error' => 'missing_env_vars']);
        exit;
    }

    // Get access token
    $ch = curl_init('https://accounts.spotify.com/api/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode("$client_id:$client_secret"),
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
        ]),
    ]);
    $tokenData = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (empty($tokenData['access_token'])) {
        echo json_encode(['is_playing' => false, 'error' => 'token_failed']);
        exit;
    }

    // Get currently playing
    $ch = curl_init('https://api.spotify.com/v1/me/player/currently-playing');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $tokenData['access_token'],
        ],
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 204 || $status === 202 || empty($body)) {
        echo json_encode(['is_playing' => false]);
        exit;
    }

    $data = json_decode($body, true);
    $item = $data['item'] ?? [];
    $album = $item['album'] ?? [];
    $images = $album['images'] ?? [];

    echo json_encode([
        'is_playing' => $data['is_playing'] ?? false,
        'track' => $item['name'] ?? '',
        'artist' => implode(', ', array_map(fn($a) => $a['name'], $item['artists'] ?? [])),
        'album' => $album['name'] ?? '',
        'album_art' => $images[0]['url'] ?? '',
        'album_art_small' => end($images)['url'] ?? '',
        'progress_ms' => $data['progress_ms'] ?? 0,
        'duration_ms' => $item['duration_ms'] ?? 0,
    ]);
    exit;
}

// Inject dply bar into HTML pages in local dev
if ($uri === '/' || $uri === '/index.html') {
    $html = file_get_contents(__DIR__ . '/index.html');
    $barScript = '<script src="/dply-bar.js" data-dply-api="/dply"></script>';
    $html = str_replace('</body>', $barScript . "\n</body>", $html);
    header('Content-Type: text/html');
    echo $html;
    exit;
}

// Serve dply-bar.js from the dply package
if ($uri === '/dply-bar.js') {
    $paths = [
        __DIR__ . '/../dply/dist/dply-bar.js',           // sibling project
        __DIR__ . '/vendor/bythepixel/dply/dist/dply-bar.js', // if composer installed
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            header('Content-Type: application/javascript');
            readfile($path);
            exit;
        }
    }
}

// Serve static files
return false;
