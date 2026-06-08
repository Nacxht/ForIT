<?php
/**
 * CommentController — Logika bisnis untuk komentar
 */

require_once __DIR__ . '/../db_conn.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../repositories/CommentRepository.php';
require_once __DIR__ . '/../validators/ProfanityFilter.php';

$commentRepo = new CommentRepository(DBH);

/**
 * Susun flat comments menjadi tree (nested)
 * Mengembalikan array komentar dengan key 'replies' berisi balasan
 */
function build_comment_tree(array $comments): array
{
    $map      = [];
    $roots    = [];

    foreach ($comments as $c) {
        $c['replies'] = [];
        $map[$c['comment_id']] = $c;
    }

    foreach ($map as $id => &$c) {
        if ($c['parent_comment_id'] !== null && isset($map[$c['parent_comment_id']])) {
            $map[$c['parent_comment_id']]['replies'][] = &$c;
        } else {
            $roots[] = &$c;
        }
    }

    return $roots;
}

/**
 * Handle tambah komentar
 */
function handle_add_comment(CommentRepository $repo, array $user): array
{
    $content         = trim($_POST['content'] ?? '');
    $threadId        = trim($_POST['thread_id'] ?? '');
    $parentCommentId = trim($_POST['parent_comment_id'] ?? '') ?: null;

    if ($user['status'] === 'restricted') {
        return ['success' => false, 'message' => 'Hak posting kamu sedang dibatasi oleh moderator.'];
    }

    if (empty($content)) {
        return ['success' => false, 'message' => 'Komentar tidak boleh kosong.'];
    }

    if (mb_strlen($content) > 5000) {
        return ['success' => false, 'message' => 'Komentar terlalu panjang (maks. 5000 karakter).'];
    }

    $foundBadWord = ProfanityFilter::detect($content);
    if ($foundBadWord !== null) {
        return ['success' => false, 'message' => 'Komentar mengandung kata kasar / tidak pantas.'];
    }

    if (empty($threadId)) {
        return ['success' => false, 'message' => 'Thread tidak valid.'];
    }

    $ok = $repo->create([
        'comment_id'        => generate_ulid(),
        'content'           => $content,
        'parent_comment_id' => $parentCommentId,
        'thread_id'         => $threadId,
        'author_id'         => $user['user_id'],
    ]);

    return $ok
        ? ['success' => true]
        : ['success' => false, 'message' => 'Gagal menambah komentar.'];
}

/**
 * Handle hapus komentar
 */
function handle_delete_comment(CommentRepository $repo, string $commentId, array $user): array
{
    $comment = $repo->findById($commentId);
    if (!$comment) {
        return ['success' => false, 'message' => 'Komentar tidak ditemukan.'];
    }

    $canDelete = $comment['author_id'] === $user['user_id'] || has_role('moderator', 'superadmin');
    if (!$canDelete) {
        return ['success' => false, 'message' => 'Kamu tidak berhak menghapus komentar ini.'];
    }

    $repo->delete($commentId);
    return ['success' => true];
}
