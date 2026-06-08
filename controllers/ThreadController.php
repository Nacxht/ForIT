<?php
/**
 * ThreadController — Logika bisnis untuk thread
 */

require_once __DIR__ . '/../db_conn.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../repositories/ThreadRepository.php';
require_once __DIR__ . '/../repositories/TopicRepository.php';
require_once __DIR__ . '/../validators/ProfanityFilter.php';

$threadRepo = new ThreadRepository(DBH);
$topicRepo  = new TopicRepository(DBH);

/**
 * Handle pembuatan thread baru
 */
function handle_create_thread(ThreadRepository $threadRepo, TopicRepository $topicRepo, array $user): array
{
    $errors = [];

    $title       = trim($_POST['thread_title'] ?? '');
    $description = trim($_POST['thread_description'] ?? '');
    $topicIds    = $_POST['topic_ids'] ?? [];

    if (empty($title)) {
        $errors['thread_title'] = 'Judul thread wajib diisi.';
    } elseif (mb_strlen($title) > 100) {
        $errors['thread_title'] = 'Judul maksimal 100 karakter.';
    } else {
        $foundBadWord = ProfanityFilter::detect($title);
        if ($foundBadWord !== null) {
            $errors['thread_title'] = 'Judul thread mengandung kata kasar / tidak pantas.';
        }
    }

    if (empty($description)) {
        $errors['thread_description'] = 'Konten thread wajib diisi.';
    } elseif (mb_strlen($description) < 20) {
        $errors['thread_description'] = 'Konten thread minimal 20 karakter.';
    } else {
        $foundBadWord = ProfanityFilter::detect($description);
        if ($foundBadWord !== null) {
            $errors['thread_description'] = 'Konten thread mengandung kata kasar / tidak pantas.';
        }
    }

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // Cek status user (restricted = tidak boleh posting)
    if ($user['status'] === 'restricted') {
        return ['success' => false, 'errors' => ['general' => 'Hak posting kamu sedang dibatasi oleh moderator.']];
    }

    $threadId = generate_ulid();

    $ok = $threadRepo->create([
        'thread_id'          => $threadId,
        'thread_title'       => $title,
        'thread_description' => $description,
        'author_id'          => $user['user_id'],
    ]);

    if (!$ok) {
        return ['success' => false, 'errors' => ['general' => 'Gagal membuat thread. Silakan coba lagi.']];
    }

    // Assign topik
    foreach ($topicIds as $topicId) {
        $threadRepo->assignTopic($threadId, $topicId, $user['user_id']);
    }

    return ['success' => true, 'thread_id' => $threadId];
}

/**
 * Handle update thread
 */
function handle_update_thread(ThreadRepository $threadRepo, TopicRepository $topicRepo, string $threadId, array $user): array
{
    $errors = [];

    $title       = trim($_POST['thread_title'] ?? '');
    $description = trim($_POST['thread_description'] ?? '');
    $topicIds    = $_POST['topic_ids'] ?? [];

    // Verifikasi kepemilikan
    $thread = $threadRepo->findById($threadId);
    if (!$thread) {
        return ['success' => false, 'errors' => ['general' => 'Thread tidak ditemukan.']];
    }

    $canEdit = $thread['author_id'] === $user['user_id'] || has_role('moderator', 'superadmin');
    if (!$canEdit) {
        return ['success' => false, 'errors' => ['general' => 'Kamu tidak memiliki izin untuk mengedit thread ini.']];
    }

    if (empty($title)) {
        $errors['thread_title'] = 'Judul thread wajib diisi.';
    } else {
        $foundBadWord = ProfanityFilter::detect($title);
        if ($foundBadWord !== null) {
            $errors['thread_title'] = 'Judul thread mengandung kata kasar / tidak pantas.';
        }
    }

    if (empty($description)) {
        $errors['thread_description'] = 'Konten thread wajib diisi.';
    } else {
        $foundBadWord = ProfanityFilter::detect($description);
        if ($foundBadWord !== null) {
            $errors['thread_description'] = 'Konten thread mengandung kata kasar / tidak pantas.';
        }
    }

    if (!empty($errors)) return ['success' => false, 'errors' => $errors];

    $threadRepo->update($threadId, $title, $description);
    $threadRepo->clearTopics($threadId);
    foreach ($topicIds as $topicId) {
        $threadRepo->assignTopic($threadId, $topicId, $user['user_id']);
    }

    return ['success' => true, 'thread_id' => $threadId];
}

/**
 * Handle hapus thread
 */
function handle_delete_thread(ThreadRepository $threadRepo, string $threadId, array $user): array
{
    $thread = $threadRepo->findById($threadId);
    if (!$thread) {
        return ['success' => false, 'message' => 'Thread tidak ditemukan.'];
    }

    $canDelete = $thread['author_id'] === $user['user_id'] || has_role('moderator', 'superadmin');
    if (!$canDelete) {
        return ['success' => false, 'message' => 'Kamu tidak memiliki izin untuk menghapus thread ini.'];
    }

    $threadRepo->delete($threadId);
    return ['success' => true];
}
