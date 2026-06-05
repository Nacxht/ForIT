<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db_conn.php';
require_once __DIR__ . '/repositories/ThreadRepository.php';
require_once __DIR__ . '/repositories/TopicRepository.php';
require_once __DIR__ . '/repositories/BookmarkRepository.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$user         = auth_user();
$threadRepo   = new ThreadRepository(DBH);
$topicRepo    = new TopicRepository(DBH);

// Query parameters
$search      = trim($_GET['q'] ?? '');
$topicFilter = trim($_GET['topic'] ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 15;
$offset      = ($page - 1) * $perPage;

// Ambil data
$threads      = $threadRepo->getList($search, $topicFilter, $perPage, $offset);
$totalThreads = $threadRepo->countList($search, $topicFilter);
$totalPages   = max(1, (int)ceil($totalThreads / $perPage));
$allTopics    = $topicRepo->getAll();   // semua topik (sidebar)
$totalAll     = $threadRepo->count();

// Ambil topik tiap thread
$threadTopics = [];
foreach ($threads as $thread) {
    $threadTopics[$thread['thread_id']] = $topicRepo->getByThread($thread['thread_id']);
}

// Ambil bookmark user (jika login)
$bookmarkedIds = [];
if ($user) {
    require_once __DIR__ . '/repositories/BookmarkRepository.php';
    $bookmarkRepo  = new BookmarkRepository(DBH);
    $bookmarkedIds = $bookmarkRepo->getBookmarkedThreadIds($user['user_id']);
}

$flash = get_flash();
$pageCSS = ['homepage.css'];
$title = '';
?>
<!DOCTYPE html>
<html lang="id">
<?php include_once __DIR__ . '/components/metadata.php'; ?>

<body>
    <?php include_once __DIR__ . '/components/navbar.php'; ?>

    <main>
        <!-- Flash message -->
        <?php if ($flash): ?>
            <div class="page-flash">
                <div class="flash-message flash-<?= e($flash['type']) ?>">
                    <?= e($flash['message']) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Banner peringatan untuk akun restricted -->
        <?php if ($user && $user['status'] === 'restricted'): ?>
            <div class="page-flash">
                <div class="flash-message" style="background:#fff7ed;border:1.5px solid #f97316;color:#9a3412;display:flex;align-items:center;gap:0.625rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span><strong>Hak posting kamu sedang dibatasi</strong> oleh moderator karena kontenmu melanggar aturan komunitas. Kamu masih bisa membaca dan melihat thread, namun tidak dapat membuat thread atau komentar baru.</span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Forum Hero Header -->
        <section id="forum-header">
            <div class="forum-header-content">
                <h1>Selamat Datang di ForIT 👋</h1>
                <p>Forum diskusi teknologi informasi untuk mahasiswa dan komunitas IT Indonesia. Bertanya, berbagi, dan berkembang bersama.</p>

                <div class="forum-header-actions">
                    <?php if ($user): ?>
                        <?php if ($user['status'] !== 'restricted'): ?>
                            <a href="<?= BASE_URL ?>/forum/create-thread.php" class="btn btn-white" id="btn-create-thread">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Buat Thread Baru
                            </a>
                        <?php else: ?>
                            <span class="btn btn-white" style="opacity:0.5;cursor:not-allowed;" title="Hak posting kamu sedang dibatasi">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                Posting Dibatasi
                            </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-white" id="btn-join-now">
                            Bergabung Sekarang
                        </a>
                        <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-ghost btn-white" id="btn-login-hero">
                            Login
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($user): ?>
                <!-- Stats -->
                <div class="forum-stats-bar">
                    <div class="forum-stat-item">
                        <span class="forum-stat-number"><?= number_format($totalAll) ?></span>
                        <span class="forum-stat-label">Thread</span>
                    </div>
                    <div class="forum-stat-item">
                        <span class="forum-stat-number"><?= count($allTopics) ?></span>
                        <span class="forum-stat-label">Topik</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Layout Utama -->
        <div class="forum-layout">
            <!-- Thread List (kiri) -->
            <section id="thread-list-section">
                <div class="thread-list-header">
                    <h2 class="thread-list-title">
                        <?php if (!empty($search)): ?>
                            Hasil pencarian: "<em><?= e($search) ?></em>"
                        <?php elseif (!empty($topicFilter)): ?>
                            <?php
                            $activeTopic = array_filter($allTopics, fn($t) => $t['topic_id'] === $topicFilter);
                            $activeTopic = array_values($activeTopic)[0] ?? null;
                            ?>
                            Topik: <?= $activeTopic ? e($activeTopic['topic_name']) : 'Tidak diketahui' ?>
                        <?php else: ?>
                            Thread Terbaru
                        <?php endif; ?>
                    </h2>
                    <span class="thread-list-count"><?= number_format($totalThreads) ?> thread</span>
                </div>

                <?php if (empty($threads)): ?>
                    <div class="empty-state">
                        <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <h3>Belum ada thread</h3>
                        <p>
                            <?php if (!empty($search)): ?>
                                Tidak ditemukan thread yang cocok dengan "<strong><?= e($search) ?></strong>"
                            <?php else: ?>
                                Jadilah yang pertama memulai diskusi!
                            <?php endif; ?>
                        </p>
                        <?php if ($user): ?>
                            <a href="<?= BASE_URL ?>/forum/create-thread.php" class="btn btn-primary">Buat Thread Pertama</a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-primary">Daftar & Mulai Diskusi</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="thread-list" id="forum-thread-list">
                        <?php foreach ($threads as $thread):
                            $topics_thread = $threadTopics[$thread['thread_id']] ?? [];
                        ?>
                            <?php
                            // Set variabel untuk thread-card.php
                            // Gunakan $topics untuk topik thread ini (bukan semua topik)
                            $topics        = $topics_thread;
                            include __DIR__ . '/components/thread-card.php';
                            // Restore $topics ke semua topik setelah include
                            $topics = $allTopics;
                            ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="pagination" aria-label="Navigasi halaman">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" aria-label="Sebelumnya">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <?php if ($i === $page): ?>
                                    <span class="active"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" aria-label="Berikutnya">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                                </a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <!-- Sidebar Topics (kanan) -->
            <aside class="forum-sidebar" id="forum-sidebar">
                <div class="sidebar-card">
                    <p class="sidebar-card-title">Topik</p>
                    <ul class="topic-filter-list">
                        <li class="topic-filter-item">
                            <a href="<?= BASE_URL ?>/"
                               class="<?= empty($topicFilter) && empty($search) ? 'active' : '' ?>"
                               id="topic-filter-all">
                                <span class="topic-filter-dot"></span>
                                Semua Topik
                            </a>
                        </li>
                        <?php foreach ($allTopics as $topic): ?>
                            <li class="topic-filter-item">
                                <a href="<?= BASE_URL ?>/?topic=<?= e($topic['topic_id']) ?>"
                                   class="<?= $topicFilter === $topic['topic_id'] ? 'active' : '' ?>"
                                   id="topic-<?= e($topic['topic_id']) ?>">
                                    <span class="topic-filter-dot"></span>
                                    <?= e($topic['topic_name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </aside>
        </div>
    </main>

    <?php include_once __DIR__ . '/components/footer.php'; ?>

    <script>
    // Share button handler
    document.querySelectorAll('.btn-share').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const url   = this.dataset.url;
            const title = this.dataset.title;

            if (navigator.share) {
                navigator.share({ title: title, url: url }).catch(() => {});
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    const orig = this.innerHTML;
                    this.innerHTML = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Disalin!`;
                    setTimeout(() => { this.innerHTML = orig; }, 2000);
                }).catch(() => {
                    prompt('Salin tautan ini:', url);
                });
            }
        });
    });

    // Bookmark AJAX handler
    document.querySelectorAll('.btn-bookmark').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            const threadId = this.dataset.threadId;
            const action   = this.dataset.action;

            try {
                const resp = await fetch(action, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        thread_id:  threadId,
                        csrf_token: '<?= generate_csrf() ?>'
                    })
                });
                const data = await resp.json();

                if (data.bookmarked) {
                    this.classList.add('bookmarked');
                    this.innerHTML = `<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg> Tersimpan`;
                } else {
                    this.classList.remove('bookmarked');
                    this.innerHTML = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg> Simpan`;
                }
            } catch (err) {
                console.error('Bookmark error:', err);
            }
        });
    });
    </script>
</body>
</html>