<?php
require_once dirname(__DIR__) . '/core/bootstrap.php';

Auth::requireRole(['admin', 'editor']);
Csrf::verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$postId = (int)($_POST['post_id'] ?? 0);
if ($postId === 0) {
    http_response_code(400);
    exit('post_id が指定されていません。');
}

$pdo    = Database::getInstance();
$userId = Auth::id();
$role   = Auth::role();

$s = $pdo->prepare('SELECT author_id FROM posts WHERE id = ?');
$s->execute([$postId]);
$post = $s->fetch();

if (!$post) {
    http_response_code(404);
    exit('記事が見つかりません。');
}

if ($role !== 'admin' && (int)$post['author_id'] !== $userId) {
    http_response_code(403);
    exit('この記事を削除する権限がありません。');
}

$pdo->beginTransaction();
try {
    $pdo->prepare('DELETE FROM post_tags WHERE post_id = ?')->execute([$postId]);
    $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$postId]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    exit('削除エラー: ' . $e->getMessage());
}

header('Location: dashboard.php');
exit;
