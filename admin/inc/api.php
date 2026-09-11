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
 * Parse a .env file into a plain name => value array.
 *
 * Shared with admin/check-env.php so the setup page reports exactly what
 * the panel itself sees. Two readers that differ in any small way are
 * worse than none: the check page then swears the file is fine while the
 * panel keeps failing on it.
 */
function ce_env_parse($path) {
    $vars = [];
    if (!is_readable($path)) return $vars;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return $vars;

    // Notepad and cPanel's File Manager editor can save a file with an
    // invisible "byte order mark" on the front. Left in place those three
    // bytes become part of the first key's NAME, so the very first setting
    // in the file is never found — while the file looks perfect on screen.
    $lines[0] = preg_replace('/^\xEF\xBB\xBF/', '', $lines[0]);

    foreach ($lines as $line) {
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
        } else {
            // An unquoted value ends at a " #" note, which is the same rule
            // dotenv applies on the Node side. Without this, a line like
            //     GALLERY_API_BASE=https://example.com   # the backend
            // makes the panel request a URL with the note glued on the end,
            // and nothing in the resulting error mentions the comment. A
            // space before the # is required, so a "#" inside a token or a
            // password is left alone.
            $val = rtrim(preg_replace('/\s+#.*$/', '', $val));
        }

        $vars[$name] = $val;
    }

    return $vars;
}

/** Everything in .env, parsed once per request. */
function ce_env_all() {
    static $vars = null;
    if ($vars === null) {
        $vars = ce_env_parse(SITE_ROOT . '/.env');
    }
    return $vars;
}

/**
 * Read a value out of the project's .env file.
 *
 * PHP has no built-in .env support, so this parses the same file the
 * Node backend uses. Keeping one file for both means there is only ever
 * one place to update a credential.
 */
function ce_env($key, $default = null) {
    $vars = ce_env_all();
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
 * Is GALLERY_API_BASE pointing at a folder of THIS same website?
 *
 * "https://ceylonenergyservices.com/ceylon-api" is the shape cPanel's
 * "Setup Node.js App" produces when the Application URL keeps a folder
 * on the end. That case is special because the panel can then inspect
 * the mapping itself, on disk, instead of guessing from a status code.
 */
function ce_api_is_same_site() {
    $base = ce_api_base();
    $host = strtolower((string)parse_url($base, PHP_URL_HOST));
    $self = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || $self === '') return false;

    // A leading "www." is ignored on either side: the panel is usually
    // opened on one spelling and .env written with the other, and they
    // are the same website either way.
    $name = function ($h) {
        return preg_replace('/^www\./', '', preg_replace('/:\d+$/', '', $h));
    };
    if ($name($host) !== $name($self)) return false;

    // The port is NOT ignored. In local development the panel runs on
    // :8000 and the API on :5050 — the same machine, but two completely
    // separate servers, and the folder-on-disk reasoning below would be
    // nonsense there. Only a matching port means one web server is
    // serving both.
    $port = function ($url, $fallback) {
        $p = parse_url($url, PHP_URL_PORT);
        if ($p) return (string)$p;
        return preg_match('/:(\d+)$/', $fallback, $m) ? $m[1] : '';
    };
    return $port($base, '') === $port('', $self);
}

/** The folder part of GALLERY_API_BASE, with no slashes: "ceylon-api". */
function ce_api_mount_folder() {
    return trim((string)parse_url(ce_api_base(), PHP_URL_PATH), '/');
}

/**
 * Does this .htaccess contain the block cPanel writes to hand a folder
 * over to a Node app? Returns the app root it names, '' when the block
 * is there without one, or null when there is no block at all.
 */
function ce_passenger_app_root($htaccess) {
    if (!is_readable($htaccess)) return null;
    $text = (string)@file_get_contents($htaccess);
    if (stripos($text, 'PassengerAppRoot') === false) return null;
    if (preg_match('/PassengerAppRoot\s+"?([^"\r\n]+)"?/i', $text, $m)) {
        return trim($m[1]);
    }
    return '';
}

/**
 * Find every folder of this site that IS handed over to a Node app.
 *
 * Worth doing because the commonest version of this problem is not a
 * missing app at all — it is an app published at one folder while .env
 * names another ("/nodeapi" vs "/ceylon-api"). Both halves look right on
 * their own, and nothing on screen ever puts them side by side.
 */
function ce_passenger_mounts() {
    $found = [];

    // The whole document root handed over — the site-wide 503 case.
    if (ce_passenger_app_root(SITE_ROOT . '/.htaccess') !== null) {
        $found[] = '/';
    }

    foreach ((array)@scandir(SITE_ROOT) as $name) {
        if ($name === '.' || $name === '..' || $name[0] === '.') continue;
        $dir = SITE_ROOT . '/' . $name;
        if (!is_dir($dir)) continue;
        if (ce_passenger_app_root($dir . '/.htaccess') !== null) {
            $found[] = '/' . $name;
        }
    }

    return $found;
}

/**
 * Explain, in one sentence, what this server does or does not do with
 * the folder in GALLERY_API_BASE.
 *
 * This is the part a status code cannot tell you. A 404 says "no", it
 * never says which of the four "no"s it is, and the four have four
 * different fixes. Returns '' when there is nothing useful to add.
 */
function ce_api_mount_hint() {
    if (!ce_api_is_same_site()) return '';

    $folder = ce_api_mount_folder();
    $mounts = ce_passenger_mounts();
    $others = array_values(array_diff($mounts, ['/' . $folder]));

    // Elsewhere-mounted app: name it, because this is the one case where
    // the backend is running perfectly and only the address is wrong.
    $elsewhere = $others
        ? ' A Node app IS published at ' . implode(' and ', $others) . ' on this site'
          . ' — if that is this backend, set GALLERY_API_BASE to https://'
          . parse_url(ce_api_base(), PHP_URL_HOST) . $others[0] . ' instead.'
        : '';

    if ($folder === '') {
        return ' GALLERY_API_BASE has no folder on the end, so it points at the website itself'
             . ' rather than at the backend.' . $elsewhere;
    }

    $dir      = SITE_ROOT . '/' . $folder;
    $htaccess = $dir . '/.htaccess';
    $appRoot  = ce_passenger_app_root($htaccess);

    if ($appRoot !== null) {
        return ' The mapping itself is in place (' . $htaccess . ' hands /' . $folder
             . ' to the Node app' . ($appRoot !== '' ? ' at ' . $appRoot : '') . '), so the address is right'
             . ' and it is the app behind it that is not answering. Open cPanel → Setup Node.js App,'
             . ' read the stderr log link on the app, and click Restart.';
    }

    if (!is_dir($dir)) {
        return ' There is no "' . $folder . '" folder inside this website at all (' . $dir . '),'
             . ' and that folder is what cPanel creates when a Node app is published at that address.'
             . ' So nothing on this server connects /' . $folder . ' to the backend.'
             . ' In cPanel → Setup Node.js App, set the app\'s Application URL to '
             . parse_url(ce_api_base(), PHP_URL_HOST) . '/' . $folder . ' and click SAVE — saving is what'
             . ' writes the connection — then Restart.' . $elsewhere;
    }

    return ' The folder ' . $dir . ' exists, but it has no Passenger block'
         . ' (' . $htaccess . ' is missing, or does not mention PassengerAppRoot). That block IS the'
         . ' connection between this address and the Node app, and re-uploading the website over that'
         . ' folder deletes it. Re-save the Application URL in cPanel → Setup Node.js App to write it'
         . ' again, then Restart.' . $elsewhere;
}

/**
 * Attach a header collector to a cURL handle so we can see WHO answered
 * and WHERE they pointed us, not just what they said.
 *
 * This matters more than it sounds. Our API only ever refuses a request
 * with 401 (bad token) — it has no 403 anywhere. So a 403 arriving here
 * proves the reply came from some other program that happens to hold the
 * port. Its "Server:" header names it, which turns an unexplainable
 * status code into an obvious cause.
 */
function ce_capture_headers($ch, &$seen) {
    $seen = ['server' => '', 'location' => ''];
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $line) use (&$seen) {
        if (stripos($line, 'Server:') === 0) {
            $seen['server'] = trim(substr($line, 7));
        } elseif (stripos($line, 'Location:') === 0) {
            // Kept because a redirect is not a failure of ours at all:
            // it is the host saying "that is not the address, THIS is",
            // and the new address is usually the fix, spelled out.
            $seen['location'] = trim(substr($line, 9));
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
function ce_api_explain_failure($status, $serverHeader, $data, $locationHeader = '') {
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

    // A redirect is the host correcting the address — most often http ->
    // https, or a missing trailing part. Uploads are deliberately NOT
    // followed through a redirect (a redirected POST is turned into a GET
    // by every HTTP client, so the photo would quietly vanish), which
    // means this has to be fixed in .env rather than worked around here.
    if (in_array($status, [301, 302, 303, 307, 308], true) && $locationHeader !== '') {
        $target = preg_replace('#/api/health/?$#', '', $locationHeader);
        return 'The host redirected ' . $base . ' to ' . $locationHeader . ' instead of answering, '
             . 'and uploads are not sent through a redirect because that would turn them into empty '
             . 'requests. Put the address it is pointing you at into .env: '
             . 'GALLERY_API_BASE=' . rtrim($target, '/') . ' (no trailing slash).';
    }

    // Answered, but not in our API's language — so who did answer, and
    // is this a laptop or a real host? The advice differs completely,
    // and "check the port with lsof" is useless to someone on shared
    // hosting who has no shell.
    $host = parse_url($base, PHP_URL_HOST);
    $path = parse_url($base, PHP_URL_PATH);
    $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

    // Naming who answered is only useful if we also say what that name
    // MEANS. "LiteSpeed" or "Apache" is the hosting account's own web
    // server: on cPanel it answers every request for this domain, so its
    // name is normal and proves nothing was stolen — it means the request
    // stopped at the web server instead of being passed on to Node.
    // Saying only the name has sent people hunting for a port conflict
    // that does not exist on shared hosting.
    $isWebServer = preg_match('/litespeed|apache|nginx|cloudflare|openresty/i', $serverHeader) === 1;
    if ($serverHeader === '') {
        $who = '';
    } elseif ($isWebServer && !$isLocal) {
        $who = ' The reply came from "' . $serverHeader . '", which is this hosting account\'s own web '
             . 'server answering for the whole domain — not another program holding a port.';
    } else {
        $who = ' It identifies itself as "' . $serverHeader . '".';
    }

    // A 404 from a web server on a public host is the signature of a
    // request that never reached the app at all: the server looked for a
    // file of that name, found none, and answered by itself. Our own
    // 404s are JSON with an "error" key, and would have been caught
    // above — so this one is not ours.
    if ($status === 404 && !$isLocal) {
        return 'The web server at ' . $host . ' answered 404 for ' . $base . '/api/health, so the '
             . 'address "' . ($path !== '' ? $path : '/') . '" on this server is not connected to the '
             . 'Node backend at all.' . $who . ce_api_mount_hint()
             . ' Once it is connected, opening ' . $base . '/api/health in a browser gives you JSON '
             . 'rather than a web-server error page.';
    }

    if (!$isLocal) {
        return 'Something at ' . $host . ' answered HTTP ' . $status . ' for ' . $base
             . ', but it is not the gallery API.' . $who
             . ' Either GALLERY_API_BASE points at the wrong address, or the backend is not '
             . 'running there.' . ce_api_mount_hint()
             . ' Open ' . $base . '/api/health in a browser to see for yourself.';
    }

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
    // The same token is sent twice on purpose. Apache and LiteSpeed do
    // not always pass the Authorization header through to an application
    // — it is the header they use for their own password prompts — and
    // when it is dropped every upload comes back "401 Unauthorized" from
    // a backend that is running perfectly. The API accepts either header,
    // so sending both survives that without weakening anything.
    $headers = [
        'Authorization: Bearer ' . $token,
        'X-Admin-Token: ' . $token,
        'Accept: application/json',
    ];

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
    ce_capture_headers($ch, $seen);

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
            'error' => 'Could not reach the gallery API at ' . ce_api_base() . ' (' . $err . '). '
                     . (ce_api_is_same_site()
                         ? 'That address is this same website, so this is about how the address is '
                           . 'published, not about a program being stopped.' . ce_api_mount_hint()
                         : 'Is it running? Start it with "npm start".'),
        ];
    }

    $data = json_decode($body, true);

    if ($status < 200 || $status >= 300) {
        return [
            'ok' => false, 'status' => $status, 'data' => $data,
            'error' => ce_api_explain_failure($status, $seen['server'], $data, $seen['location']),
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
    ce_capture_headers($ch, $seen);

    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    unset($ch); // see the note above — no curl_close() on PHP 8.5+

    // Case 1: nothing accepted the connection at all. This is the only
    // case where "it is not running" is the right thing to say.
    if ($body === false) {
        return [
            'ok' => false, 'health' => null,
            'problem' => 'Nothing answered at ' . ce_api_base() . ' (' . $err . '). '
                       . (ce_api_is_same_site()
                           ? 'That address is this same website, so the web server itself should always '
                             . 'answer something — an error here usually means the address is misspelt, '
                             . 'or this server is not allowed to call its own domain.' . ce_api_mount_hint()
                           : 'Start the backend from the project folder with "npm start", then reload this page.'),
            'status' => 0, 'server' => $seen['server'], 'body' => '',
        ];
    }

    $data = json_decode($body, true);

    // Case 2: our API answered and named itself. The only good outcome.
    if ($status === 200 && is_array($data) && ($data['service'] ?? '') === 'ceylon-energy-api') {
        return [
            'ok' => true, 'health' => $data, 'problem' => null,
            'status' => $status, 'server' => $seen['server'], 'body' => (string)$body,
        ];
    }

    // Case 3: someone answered, but it was not us.
    return [
        'ok' => false, 'health' => null,
        'problem' => ce_api_explain_failure($status, $seen['server'], $data, $seen['location']),
        'status' => $status, 'server' => $seen['server'], 'body' => (string)$body,
    ];
}
