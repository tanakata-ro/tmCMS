<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

Auth::requireRole(['admin', 'editor']);
Csrf::verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    echo json_encode(['success' => false, 'message' => 'ファイルが送信されていません。']);
    exit;
}

$userId = Auth::id();
$postId = (int)($_POST['post_id'] ?? 0); // 記事IDがあれば記事フォルダに振り分け

// アップロード先ディレクトリを決定
// uploads/u{userId}/          ← ユーザー別
// uploads/u{userId}/p{postId}/ ← 記事別（post_id指定時）
$baseUploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : dirname(__DIR__) . '/uploads/';
$userDir       = $baseUploadDir . 'u' . $userId . '/';
$targetDir     = $postId > 0 ? $userDir . 'p' . $postId . '/' : $userDir;

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
    // 各ディレクトリにPHP実行禁止の.htaccessを置く
    file_put_contents($targetDir . '.htaccess', "Options -ExecCGI\nAddType text/plain .php .php5 .phtml\n");
}

$file   = $_FILES['image'];
$result = Security::validateUpload($file);

if (!$result['ok']) {
    echo json_encode(['success' => false, 'message' => $result['error']]);
    exit;
}

// ファイル名: タイムスタンプ_ランダム.拡張子
$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$safeName = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$savePath = $targetDir . $safeName;

if (!move_uploaded_file($file['tmp_name'], $savePath)) {
    echo json_encode(['success' => false, 'message' => 'ファイルの保存に失敗しました。']);
    exit;
}

// 公開URLを構築
$scheme   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$relPath  = 'uploads/u' . $userId . '/' . ($postId > 0 ? 'p' . $postId . '/' : '') . $safeName;
$imageUrl = $scheme . '://' . $host . $basePath . '/' . $relPath;

echo json_encode([
    'success'   => true,
    'url'       => $imageUrl,
    'file_name' => $safeName,
    'user_id'   => $userId,
    'post_id'   => $postId,
]);
