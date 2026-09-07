<?php
/**
 * Ceylon Energy Services — Admin: gallery API client
 *
 * The photos themselves live in Cloudinary and the gallery structure
 * lives in MongoDB Atlas, so the admin panel can no longer just move
 * files around on disk. Every change (add, upload, delete) is sent to
 * the Node backend in server/, which owns both.
 *
 * Reads are deliberately NOT sent to the API. The admin screen renders
 * from assets/data/projects.json, which this file refreshes after every
 * successful write. That way the panel still shows the current gallery
 * even when the backend happens to be stopped — only changing things
 * needs it running.
 */
require_once __DIR__ . '/config.php';

/**
 * Read a value out of the project's .env file.
 *
 * PHP has no built-in .env support, so this parses the same file the
 * Node backend uses. Keeping one file for both means there is only ever
 * one place to update a credential.
 */
function ce_env($key, $default = null) {
    static $vars = null;

    if ($vars === null) {
        $vars = [];
        $path = SITE_ROOT . '/.env';
        if (is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') continue;
                $eq = strpos($line, '=');
                if ($eq === false) continue;

                $name = trim(substr($line, 0, $eq));
                $val  = trim(substr($line, $eq + 1));

                // Strip surrounding quotes if present.
                $len = strlen($val);
                if ($len >= 2 && (($val[0] === '"' && $val[$len - 1] === '"') ||
                                  ($val[0] === "'" && $val[$len - 1] === "'"))) {
                    $val = substr($val, 1, -1);
                }
                $vars[$name] = $val;
            }
        }
    }

    return array_key_exists($key, $vars) && $vars[$key] !== '' ? $vars[$key] : $default;
}

/** Base URL of the gallery API, without a trailing slash. */
function ce_api_base() {
    return rtrim(ce_env('GALLERY_API_BASE', 'http://localhost:5050'), '/');
}

/**
 * Say WHERE ce_api_base() got its answer.
 *
 * "localhost:5050" reaching the screen has three completely different
 * causes — no .env file, an .env without the key, or an .env that
 * genuinely says localhost — and they need three different fixes. Left
 * undistinguished they produce one identical banner, so someone who has
 * already written the file correctly re-reads it over and over looking
 * for a mistake that is not there.
 *
 * Returns 'env' | 'env-missing-key' | 'no-env-file'.
 */
function ce_api_base_source() {
    if (ce_env('GALLERY_API_BASE') !== null) return 'env';
    return is_readable(SITE_ROOT . '/.env') ? 'env-missing-key' : 'no-env-file';
}

/**
 * Attach a header collector to a cURL handle so we can see WHO answered,
 * not just what they said.
 *
 * This matters more than it sounds. Our API only ever refuses a request
 * with 401 (bad token) — it has no 403 anywhere. So a 403 arriving here
 * proves the reply came from some other program that happens to hold the
 * port. Its "Server:" header names it, which turns an unexplainable
 * status code into an obvious cause.
 */
function ce_capture_server_header($ch, &$serverHeader) {
    $serverHeader = '';
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $line) use (&$serverHeader) {
        if (stripos($line, 'Server:') === 0) {
            $serverHeader = trim(substr($line, 7));
        }
        return strlen($line); // cURL requires the byte count back
    });
}

/**
 * Turn a failed response into a sentence that says what to actually do.
 *
 * $data is the decoded JSON body, or null when the reply was not JSON —
 * which is itself a strong hint, because every error our API produces is
 * JSON with an "error" key.
 */
function ce_api_explain_failure($status, $serverHeader, $data) {
    $base = ce_api_base();

    // macOS ships AirPlay Receiver listening on port 5000, and it answers
    // every request with "403 Forbidden". Because it can share the port,
    // "npm start" still prints "listening on 5000" — so the server looks
    // fine while nothing ever reaches it. This is the single most likely
    // reason anyone sees a 403 here.
    if (stripos($serverHeader, 'AirTunes') !== false || stripos($serverHeader, 'AirPlay') !== false) {
        return 'macOS AirPlay Receiver is answering at ' . $base . ', not your gallery API. '
             . 'It quietly shares port ' . ce_api_port() . ' and rejects everything with 403, so your requests '
             . 'never reach the backend even though "npm start" says it is running. '
             . 'Fix it by moving the API to a free port: set PORT=5050 and '
             . 'GALLERY_API_BASE=http://localhost:5050 in .env, then restart "npm start" and reload. '
             . '(Turning off System Settings > General > AirDrop & Handoff > AirPlay Receiver also works.)';
    }

    // A JSON body with an "error" key means this really was our API, so
    // its own message is the most accurate thing we can show.
    if (is_array($data) && !empty($data['error'])) {
        return $data['error'];
    }

    // Answered, but not in our API's language — some other service owns
    // this address.
    $who = $serverHeader !== '' ? ' It identifies itself as "' . $serverHeader . '".' : '';
    return 'Something answered at ' . $base . ' with HTTP ' . $status
         . ', but it is not the gallery API.' . $who
         . ' Either another program is using that port, or GALLERY_API_BASE in .env points '
         . 'at the wrong address. Check what is on the port with:  lsof -i :' . ce_api_port();
}

/** The port number out of GALLERY_API_BASE, for use in help messages. */
function ce_api_port() {
    $port = parse_url(ce_api_base(), PHP_URL_PORT);
    if ($port) return (string)$port;
    return parse_url(ce_api_base(), PHP_URL_SCHEME) === 'https' ? '443' : '80';
}

/**
 * Send a request to the API.
 *
 * $files is an array of ['field' => path] for uploads. Returns
 * ['ok' => bool, 'status' => int, 'data' => array|null, 'error' => string|null].
 */
function ce_api_request($method, $path, array $fields = [], array $files = []) {
    $token = ce_env('ADMIN_API_TOKEN');
    if (!$token) {
        return [
            'ok' => false, 'status' => 0, 'data' => null,
            'error' => 'ADMIN_API_TOKEN is not set in .env — copy .env.example to .env and fill it in.',
        ];
    }

    $ch = curl_init(ce_api_base() . $path);
    $headers = ['Authorization: Bearer ' . $token, 'Accept: application/json'];

    if ($files) {
        // Uploads go as multipart so the file streams through rather
        // than being base64'd into a JSON body.
        $post = $fields;
        foreach ($files as $field => $file) {
            $post[$field] = new CURLFile($file['tmp'], $file['type'], $file['name']);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    } elseif ($fields) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        $headers[] = 'Content-Type: application/json';
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 5,
        // Uploading several MB to Cloudinary through the API can take a
        // while on a slow connection, so this is generous on purpose.
        CURLOPT_TIMEOUT        => 120,
    ]);
    ce_capture_server_header($ch, $serverHeader);

    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    // No curl_close() on purpose: since PHP 8.0 a handle is an object
    // that frees itself when it goes out of scope, and calling it is
    // deprecated as of PHP 8.5 — which prints a notice mid-page.
    unset($ch);

    if ($body === false) {
        return [
            'ok' => false, 'status' => 0, 'data' => null,
            'error' => 'Could not reach the gallery API at ' . ce_api_base()
                     . ' (' . $err . '). Is it running? Start it with "npm start".',
        ];
    }

    $data = json_decode($body, true);

    if ($status < 200 || $status >= 300) {
        return [
            'ok' => false, 'status' => $status, 'data' => $data,
            'error' => ce_api_explain_failure($status, $serverHeader, $data),
        ];
    }

    return ['ok' => true, 'status' => $status, 'data' => $data, 'error' => null];
}

/**
 * Pull the current gallery from the API and write it to
 * assets/data/projects.json in the same shape "npm run export"
 * produces.
 *
 * Called after every successful write so the public site and this admin
 * screen both reflect the change immediately, with no separate export
 * step to remember.
 */
function ce_refresh_static_json() {
    $res = ce_api_request('GET', '/api/projects');
    if (!$res['ok'] || !isset($res['data']['locations'])) {
        return false;
    }

    $out = [];
    foreach ($res['data']['locations'] as $loc) {
        $projects = [];
        foreach ($loc['projects'] ?? [] as $proj) {
            $photos = [];
            foreach ($proj['photos'] ?? [] as $photo) {
                $photos[] = [
                    'id'      => $photo['id'] ?? '',
                    'caption' => $photo['caption'] ?? '',
                    'thumb'   => $photo['thumb'] ?? '',
                    'medium'  => $photo['medium'] ?? '',
                    'large'   => $photo['large'] ?? '',
                ];
            }
            $projects[] = [
                'id'     => $proj['slug'] ?? '',
                'name'   => $proj['name'] ?? '',
                'photos' => $photos,
            ];
        }
        $out[] = [
            'id'       => $loc['slug'] ?? '',
            'name'     => $loc['name'] ?? '',
            'projects' => $projects,
        ];
    }

    $dir = dirname(PROJECTS_JSON);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Written to a temp file and renamed, so the live site never reads a
    // half-written file.
    $tmp = PROJECTS_JSON . '.tmp';
    file_put_contents($tmp, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    rename($tmp, PROJECTS_JSON);
    return true;
}

/**
 * Check the backend before the admin screen lets anyone try to use it.
 *
 * The previous version returned null for every kind of failure, so
 * "nothing is listening" and "something is listening but it is not us"
 * produced the identical message: "the backend is not responding, start
 * it with npm start". When the backend WAS running that advice was not
 * just unhelpful, it pointed away from the real problem. This tells the
 * three cases apart.
 *
 * Returns:
 *   ['ok' => true,  'health' => [...], 'problem' => null]
 *   ['ok' => false, 'health' => null,  'problem' => 'sentence to show']
 */
function ce_api_probe() {
    $ch = curl_init(ce_api_base() . '/api/health');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    ce_capture_server_header($ch, $serverHeader);

    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    unset($ch); // see the note above — no curl_close() on PHP 8.5+

    // Case 1: nothing accepted the connection at all. This is the only
    // case where "it is not running" is the right thing to say.
    if ($body === false) {
        return [
            'ok' => false, 'health' => null,
            'problem' => 'Nothing is listening at ' . ce_api_base() . ' (' . $err . '). '
                       . 'Start the backend from the project folder with "npm start", then reload this page.',
        ];
    }

    $data = json_decode($body, true);

    // Case 2: our API answered and named itself. The only good outcome.
    if ($status === 200 && is_array($data) && ($data['service'] ?? '') === 'ceylon-energy-api') {
        return ['ok' => true, 'health' => $data, 'problem' => null];
    }

    // Case 3: someone answered, but it was not us.
    return [
        'ok' => false, 'health' => null,
        'problem' => ce_api_explain_failure($status, $serverHeader, $data),
    ];
}
