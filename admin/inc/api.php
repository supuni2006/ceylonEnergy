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
    return rtrim(ce_env('GALLERY_API_BASE', 'http://localhost:5000'), '/');
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

    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return [
            'ok' => false, 'status' => 0, 'data' => null,
            'error' => 'Could not reach the gallery API at ' . ce_api_base()
                     . ' (' . $err . '). Is it running? Start it with "npm start".',
        ];
    }

    $data = json_decode($body, true);

    if ($status < 200 || $status >= 300) {
        $msg = is_array($data) && !empty($data['error'])
            ? $data['error']
            : 'The gallery API returned HTTP ' . $status . '.';
        return ['ok' => false, 'status' => $status, 'data' => $data, 'error' => $msg];
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

/** Quick check used by the admin screen to warn when the backend is down. */
function ce_api_health() {
    $ch = curl_init(ce_api_base() . '/api/health');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 5,
    ]);
    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $status !== 200) return null;
    return json_decode($body, true);
}
