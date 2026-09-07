<?php
require_once __DIR__ . '/config.php';

/**
 * Legacy/default gallery data — used only the very first time
 * projects.json doesn't exist yet, so nothing on the live site breaks.
 * After the first load this file on disk is always the source of truth.
 */
function ce_default_projects() {
    return [
        ['name' => 'Rathnapura', 'projects' => [
            ['name' => 'Belihuloya project 01', 'photos' => [1, 3, 4, 5]],
            ['name' => 'Belihuloya project 02', 'photos' => [8, 7, 6, 9]],
            ['name' => 'Sabaragamuwa University', 'photos' => [39, 37, 38, 36]],
            ['name' => 'Udawalawa project', 'photos' => [42, 41, 40]],
        ]],
        ['name' => 'Colombo', 'projects' => [
            ['name' => 'Project 01', 'photos' => [10, 11, 12, 13]],
            ['name' => 'Project 02', 'photos' => [14, 16, 17]],
            ['name' => 'Project 03', 'photos' => [46, 47, 48]],
            ['name' => 'Wellampitiya', 'photos' => [43, 44, 45]],
            ['name' => 'Moratuwa', 'photos' => [49, 50, 51]],
            ['name' => 'Microchip Solution - Moratuwa', 'photos' => [58, 56, 57, 55, 59]],
        ]],
        ['name' => 'Gampaha', 'projects' => [
            ['name' => "Wattala (I C M Perera 's site)", 'photos' => [63, 64, 65, 68]],
        ]],
        ['name' => 'piliyandala', 'projects' => [
            ['name' => "B C D Mendis 's site", 'photos' => [67, 69, 70]],
        ]],
        ['name' => 'Dehiwala', 'projects' => [
            ['name' => 'Project 01', 'photos' => [30, 31]],
            ['name' => "G S Indika Perera's site", 'photos' => [60, 61, 62]],
        ]],
        ['name' => 'Kaluthara', 'projects' => [
            ['name' => 'Project 01', 'photos' => [33, 34]],
        ]],
        ['name' => 'Bandaragama', 'projects' => [
            ['name' => 'Project 01', 'photos' => [52, 53, 54]],
        ]],
    ];
}

/** Make sure every location/project has a stable id, assigning one if missing. */
function ce_ensure_project_ids($locations, &$changed) {
    foreach ($locations as &$loc) {
        if (empty($loc['id'])) { $loc['id'] = bin2hex(random_bytes(4)); $changed = true; }
        if (!isset($loc['projects']) || !is_array($loc['projects'])) $loc['projects'] = [];
        foreach ($loc['projects'] as &$proj) {
            if (empty($proj['id'])) { $proj['id'] = bin2hex(random_bytes(4)); $changed = true; }
            if (!isset($proj['photos']) || !is_array($proj['photos'])) $proj['photos'] = [];
        }
        unset($proj);
    }
    unset($loc);
    return $locations;
}

function ce_load_projects() {
    if (file_exists(PROJECTS_JSON)) {
        $json = json_decode(file_get_contents(PROJECTS_JSON), true);
        $locations = is_array($json) ? $json : [];
        $hadFile = true;
    } else {
        $locations = ce_default_projects();
        $hadFile = false;
    }

    $changed = false;
    $locations = ce_ensure_project_ids($locations, $changed);
    if ($changed || !$hadFile) {
        ce_save_projects($locations);
    }
    return $locations;
}

function ce_save_projects($locations) {
    $dir = dirname(PROJECTS_JSON);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $tmp = PROJECTS_JSON . '.tmp';
    file_put_contents($tmp, json_encode(array_values($locations), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    rename($tmp, PROJECTS_JSON);
}

/** Next free photo number, so newly uploaded photos never collide with existing project-N.jpg files. */
function ce_projects_next_photo_number($locations) {
    $max = 0;
    foreach ($locations as $loc) {
        foreach ($loc['projects'] as $proj) {
            foreach ($proj['photos'] as $n) {
                if ((int)$n > $max) $max = (int)$n;
            }
        }
    }
    return $max + 1;
}

function ce_add_location($name) {
    $name = trim($name);
    if ($name === '') return false;
    $locations = ce_load_projects();
    $locations[] = ['id' => bin2hex(random_bytes(4)), 'name' => $name, 'projects' => []];
    ce_save_projects($locations);
    return true;
}

function ce_add_project($locationId, $projectName) {
    $projectName = trim($projectName);
    if ($projectName === '') return false;
    $locations = ce_load_projects();
    foreach ($locations as &$loc) {
        if ($loc['id'] !== $locationId) continue;
        $loc['projects'][] = ['id' => bin2hex(random_bytes(4)), 'name' => $projectName, 'photos' => []];
        ce_save_projects($locations);
        return true;
    }
    return false;
}

/** Validate a single uploaded project photo ($_FILES['photo']-style entry). */
function ce_validate_project_photo($file) {
    if (empty($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return 'Please choose a photo to upload.';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'The photo upload failed (error code ' . $file['error'] . ').';
    }
    if ($file['size'] > MAX_IMAGE_BYTES) {
        return 'That photo is larger than the ' . ce_human_bytes(MAX_IMAGE_BYTES) . ' limit.';
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
        return 'Photos must be a JPG or PNG image.';
    }
    return true;
}

/** $file is a validated $_FILES['photo']-style entry. */
function ce_add_project_photo($locationId, $projectId, $file) {
    $locations = ce_load_projects();
    $n = ce_projects_next_photo_number($locations);

    if (!is_dir(PROJECTS_IMAGES_DIR)) mkdir(PROJECTS_IMAGES_DIR, 0755, true);
    $dest = PROJECTS_IMAGES_DIR . '/project-' . $n . '.jpg';
    if (!move_uploaded_file($file['tmp_name'], $dest)) return false;

    foreach ($locations as &$loc) {
        if ($loc['id'] !== $locationId) continue;
        foreach ($loc['projects'] as &$proj) {
            if ($proj['id'] !== $projectId) continue;
            $proj['photos'][] = $n;
            ce_save_projects($locations);
            return true;
        }
    }

    // couldn't find the target project — undo the file move rather than leave it orphaned
    @unlink($dest);
    return false;
}

function ce_delete_photo($locationId, $projectId, $photoNumber) {
    $locations = ce_load_projects();
    foreach ($locations as &$loc) {
        if ($loc['id'] !== $locationId) continue;
        foreach ($loc['projects'] as &$proj) {
            if ($proj['id'] !== $projectId) continue;
            $before = count($proj['photos']);
            $proj['photos'] = array_values(array_filter($proj['photos'], function ($n) use ($photoNumber) {
                return (int)$n !== (int)$photoNumber;
            }));
            if (count($proj['photos']) === $before) return false;
            ce_save_projects($locations);
            $img = PROJECTS_IMAGES_DIR . '/project-' . (int)$photoNumber . '.jpg';
            if (file_exists($img)) @unlink($img);
            return true;
        }
    }
    return false;
}

function ce_delete_project($locationId, $projectId) {
    $locations = ce_load_projects();
    foreach ($locations as &$loc) {
        if ($loc['id'] !== $locationId) continue;
        $before = count($loc['projects']);
        $loc['projects'] = array_values(array_filter($loc['projects'], function ($p) use ($projectId) {
            return $p['id'] !== $projectId;
        }));
        if (count($loc['projects']) === $before) return false;
        ce_save_projects($locations);
        return true;
    }
    return false;
}

function ce_delete_location($locationId) {
    $locations = ce_load_projects();
    $before = count($locations);
    $locations = array_values(array_filter($locations, function ($l) use ($locationId) {
        return $l['id'] !== $locationId;
    }));
    if (count($locations) === $before) return false;
    ce_save_projects($locations);
    return true;
}
