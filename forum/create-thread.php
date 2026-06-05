<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

$allowedRoles = ['user', 'moderator', 'superadmin'];
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/role.php';

require_once __DIR__ . '/../controllers/ThreadController.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$user = auth_user();

// Blokir user yang sedang dibatasi
if ($user && $user['status'] === 'restricted') {
    set_flash('error', 'Hak posting kamu sedang dibatasi oleh moderator. Kamu tidak dapat membuat thread baru saat ini.');
    redirect(BASE_URL . '/');
}

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors['general'] = 'Permintaan tidak valid. Silakan coba lagi.';
    } else {
        $result = handle_create_thread($threadRepo, $topicRepo, $user);
        if ($result['success']) {
            set_flash('success', 'Thread berhasil dibuat!');
            redirect(BASE_URL . '/thread.php?id=' . $result['thread_id']);
        } else {
            $errors = $result['errors'];
            $old = [
                'thread_title'       => trim($_POST['thread_title'] ?? ''),
                'thread_description' => trim($_POST['thread_description'] ?? ''),
                'topic_ids'          => $_POST['topic_ids'] ?? [],
            ];
        }
    }
}

$topics  = $topicRepo->getAll();
$pageCSS = ['auth.css', 'thread.css'];
$title   = 'Buat Thread';
?>
<!DOCTYPE html>
<html lang="id">
<?php include_once __DIR__ . '/../components/metadata.php'; ?>
<style>
.create-thread-page { max-width: 780px; margin: 0 auto; padding: 2rem; }
.create-thread-card {
    background: white;
    border: 1px solid var(--gray-200, #e5e7eb);
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}
.create-thread-card h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
.create-thread-subtitle { color: var(--gray-500,#6b7280); font-size:0.875rem; margin-bottom:2rem; }
.form-group { margin-bottom: 1.5rem; }
.form-group label { display:block; font-weight:600; font-size:0.875rem; color:var(--gray-700,#374151); margin-bottom:0.5rem; }
.form-group input[type="text"],
.form-group textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1.5px solid var(--gray-200, #e5e7eb);
    border-radius: 12px;
    font-family: inherit;
    font-size: 0.9375rem;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    color: var(--gray-800, #1f2937);
    resize: vertical;
}
.form-group input:focus, .form-group textarea:focus {
    border-color: #155DFC;
    box-shadow: 0 0 0 3px rgba(21,93,252,0.12);
}
.form-group input.is-invalid, .form-group textarea.is-invalid {
    border-color: #ef4444;
}
.error-msg { color: #ef4444; font-size:0.8rem; margin-top:0.375rem; }
.char-count { font-size:0.8rem; color:var(--gray-400,#9ca3af); text-align:right; margin-top:0.25rem; }
.topic-grid { display:flex; flex-wrap:wrap; gap:0.5rem; }
.topic-check { display:none; }
.topic-label {
    display: inline-block;
    padding: 0.35rem 0.875rem;
    border: 1.5px solid var(--gray-200,#e5e7eb);
    border-radius: 50px;
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--gray-600,#4b5563);
    cursor: pointer;
    transition: all 0.15s;
}
.topic-check:checked + .topic-label {
    background: #155DFC;
    border-color: #155DFC;
    color: white;
}
.topic-label:hover { border-color: #155DFC; color: #155DFC; }
</style>
<body style="background:var(--gray-50,#f9fafb);">
    <?php include_once __DIR__ . '/../components/navbar.php'; ?>

    <main class="create-thread-page">
        <div class="create-thread-card">
            <h1>Buat Thread Baru</h1>
            <p class="create-thread-subtitle">Mulai diskusi baru di komunitas ForIT</p>

            <?php if (!empty($errors['general'])): ?>
                <div class="flash-message flash-error"><?= e($errors['general']) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf() ?>">

                <!-- Judul -->
                <div class="form-group">
                    <label for="thread_title">Judul Thread <span style="color:#ef4444">*</span></label>
                    <input type="text" id="thread_title" name="thread_title"
                           placeholder="Tulis judul yang jelas dan deskriptif..."
                           value="<?= e($old['thread_title'] ?? '') ?>"
                           class="<?= isset($errors['thread_title']) ? 'is-invalid' : '' ?>"
                           maxlength="100">
                    <div class="char-count"><span id="title-count">0</span>/100</div>
                    <?php if (isset($errors['thread_title'])): ?>
                        <div class="error-msg"><?= e($errors['thread_title']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Topik -->
                <div class="form-group">
                    <label>Topik (opsional)</label>
                    <div class="topic-grid">
                        <?php foreach ($topics as $topic): ?>
                            <input type="checkbox"
                                id="topic-<?= e($topic['topic_id']) ?>"
                                name="topic_ids[]"
                                value="<?= e($topic['topic_id']) ?>"
                                class="topic-check"
                                <?= in_array($topic['topic_id'], $old['topic_ids'] ?? []) ? 'checked' : '' ?>>
                            <label for="topic-<?= e($topic['topic_id']) ?>" class="topic-label">
                                <?= e($topic['topic_name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Konten -->
                <div class="form-group">
                    <label for="thread_description">Konten Thread <span style="color:#ef4444">*</span></label>
                    <textarea id="thread_description" name="thread_description"
                              placeholder="Tulis detail thread kamu di sini..."
                              rows="12"
                              class="<?= isset($errors['thread_description']) ? 'is-invalid' : '' ?>"><?= e($old['thread_description'] ?? '') ?></textarea>
                    <?php if (isset($errors['thread_description'])): ?>
                        <div class="error-msg"><?= e($errors['thread_description']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div style="display:flex;gap:0.75rem;justify-content:flex-end;">
                    <a href="<?= BASE_URL ?>/" class="btn btn-ghost btn-primary">Batal</a>
                    <button type="submit" class="btn btn-primary" id="btn-publish-thread">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        Publikasikan Thread
                    </button>
                </div>
            </form>
        </div>
    </main>

    <?php include_once __DIR__ . '/../components/footer.php'; ?>

    <script>
    const titleInput = document.getElementById('thread_title');
    const countEl    = document.getElementById('title-count');
    titleInput.addEventListener('input', function() {
        countEl.textContent = this.value.length;
    });
    countEl.textContent = titleInput.value.length;
    </script>
</body>
</html>
