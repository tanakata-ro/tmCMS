<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';

Auth::requireRole(['admin', 'editor']);

$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

// ── サムネイル配信（GETのみ・CSRFなし） ──────────────────────────────────────
if ($action === 'thumb') {
    $file    = basename((string)($_GET['file'] ?? ''));
    $userId  = (int)($_GET['uid'] ?? 0);
    $postId  = (int)($_GET['pid'] ?? 0);
    $baseDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : dirname(__DIR__) . '/uploads/';

    $subDir   = 'u' . $userId . '/' . ($postId > 0 ? 'p' . $postId . '/' : '');
    $filePath = $baseDir . $subDir . $file;

    // パストラバーサル対策
    if (!Security::isSafePath($filePath, $baseDir)) {
        http_response_code(403); exit;
    }
    if (!file_exists($filePath)) { http_response_code(404); exit; }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
        http_response_code(400); exit;
    }

    $thumbDir  = $baseDir . '.thumbs/' . $subDir;
    $thumbExt  = ($ext === 'png') ? 'png' : 'jpg';
    $thumbPath = $thumbDir . $file . '.' . $thumbExt;

    if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

    if (!file_exists($thumbPath) || filemtime($thumbPath) < filemtime($filePath)) {
        if (!function_exists('imagecreatefromjpeg')) {
            // GDなし → そのまま返す
            header('Content-Type: image/' . ($ext === 'jpg' ? 'jpeg' : $ext));
            readfile($filePath); exit;
        }
        $src = match($ext) {
            'jpg','jpeg' => @imagecreatefromjpeg($filePath),
            'png'        => @imagecreatefrompng($filePath),
            'gif'        => @imagecreatefromgif($filePath),
            'webp'       => @imagecreatefromwebp($filePath),
            default      => false,
        };
        if (!$src) { http_response_code(500); exit; }
        [$ow, $oh] = [imagesx($src), imagesy($src)];
        $ratio = min(280 / $ow, 200 / $oh, 1.0);
        [$nw, $nh] = [max(1,(int)round($ow*$ratio)), max(1,(int)round($oh*$ratio))];
        $thumb = imagecreatetruecolor($nw, $nh);
        if (in_array($ext, ['png','gif'], true)) {
            imagealphablending($thumb, false); imagesavealpha($thumb, true);
            imagefill($thumb, 0, 0, imagecolorallocatealpha($thumb,255,255,255,127));
        }
        imagecopyresampled($thumb, $src, 0,0,0,0, $nw,$nh, $ow,$oh);
        imagedestroy($src);
        $ext === 'png' ? imagepng($thumb, $thumbPath, 7) : imagejpeg($thumb, $thumbPath, 80);
        imagedestroy($thumb);
    }
    header('Content-Type: image/' . ($thumbExt === 'png' ? 'png' : 'jpeg'));
    header('Cache-Control: public, max-age=86400');
    readfile($thumbPath); exit;
}

// 以降はJSONレスポンス
header('Content-Type: application/json; charset=utf-8');

$baseDir  = defined('UPLOAD_DIR') ? UPLOAD_DIR : dirname(__DIR__) . '/uploads/';
$myUserId = Auth::id();
$myRole   = Auth::role();

// ── 一覧取得 ─────────────────────────────────────────────────────────────────
if ($action === 'list') {
    $search  = mb_strtolower(trim((string)($_GET['search'] ?? '')));
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $uid     = (int)($_GET['uid'] ?? $myUserId); // admin は他ユーザーも閲覧可
    $pid     = (int)($_GET['pid'] ?? 0);
    $perPage = 16;

    // admin以外は自分のフォルダのみ
    if ($myRole !== 'admin') $uid = $myUserId;

    $subDir  = 'u' . $uid . '/' . ($pid > 0 ? 'p' . $pid . '/' : '');
    $scanDir = $baseDir . $subDir;

    $images = [];
    if (is_dir($scanDir)) {
        foreach (scandir($scanDir) as $f) {
            if ($f[0] === '.') continue;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) continue;
            if ($search !== '' && strpos(mb_strtolower($f), $search) === false) continue;

            $scheme   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host     = $_SERVER['HTTP_HOST'];
            $basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
            $url      = $scheme . '://' . $host . $basePath . '/uploads/' . $subDir . $f;

            $thumbUrl = $_SERVER['SCRIPT_NAME']
                . '?action=thumb&file=' . rawurlencode($f)
                . '&uid=' . $uid . '&pid=' . $pid;

            $images[] = [
                'file'      => $f,
                'url'       => $url,
                'thumb_url' => $thumbUrl,
                'mtime'     => filemtime($scanDir . $f),
                'uid'       => $uid,
                'pid'       => $pid,
            ];
        }
    }

    usort($images, fn($a,$b) => $b['mtime'] - $a['mtime']);
    $total      = count($images);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $paged      = array_slice($images, ($page-1)*$perPage, $perPage);

    echo json_encode([
        'success'     => true,
        'images'      => array_values($paged),
        'total'       => $total,
        'page'        => $page,
        'total_pages' => $totalPages,
    ]);
    exit;
}

// ── リネーム ─────────────────────────────────────────────────────────────────
if ($action === 'rename' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $file   = basename((string)($input['file'] ?? ''));
    $uid    = (int)($input['uid'] ?? $myUserId);
    $pid    = (int)($input['pid'] ?? 0);
    $newName= trim((string)($input['new_name'] ?? ''));

    if ($myRole !== 'admin') $uid = $myUserId;

    $subDir  = 'u' . $uid . '/' . ($pid > 0 ? 'p' . $pid . '/' : '');
    $oldPath = $baseDir . $subDir . $file;

    if (!Security::isSafePath($oldPath, $baseDir) || !file_exists($oldPath)) {
        echo json_encode(['success'=>false,'message'=>'ファイルが見つかりません。']); exit;
    }

    $ext     = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $safeName= Security::sanitizeFilename($newName) . '.' . $ext;
    $newPath = $baseDir . $subDir . $safeName;

    if (file_exists($newPath)) {
        echo json_encode(['success'=>false,'message'=>'同名のファイルがすでに存在します。']); exit;
    }

    rename($oldPath, $newPath);
    echo json_encode(['success'=>true, 'new_file'=>$safeName]);
    exit;
}

// ── 削除 ─────────────────────────────────────────────────────────────────────
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $file  = basename((string)($input['file'] ?? ''));
    $uid   = (int)($input['uid'] ?? $myUserId);
    $pid   = (int)($input['pid'] ?? 0);

    if ($myRole !== 'admin') $uid = $myUserId;

    $subDir  = 'u' . $uid . '/' . ($pid > 0 ? 'p' . $pid . '/' : '');
    $path    = $baseDir . $subDir . $file;

    if (!Security::isSafePath($path, $baseDir) || !file_exists($path)) {
        echo json_encode(['success'=>false,'message'=>'ファイルが見つかりません。']); exit;
    }

    unlink($path);
    echo json_encode(['success'=>true]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'不明なアクションです。']);
