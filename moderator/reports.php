<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

$allowedRoles = ['moderator', 'superadmin'];
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../middleware/role.php';

require_once __DIR__ . '/../db_conn.php';
require_once __DIR__ . '/../repositories/ReportRepository.php';
require_once __DIR__ . '/../repositories/ThreadRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$user        = auth_user();
$reportRepo  = new ReportRepository(DBH);
$threadRepo  = new ThreadRepository(DBH);
$userRepo    = new UserRepository(DBH);

// Handle aksi moderasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $action   = $_POST['action'] ?? '';
    $reportId = trim($_POST['report_id'] ?? '');
    $userId   = trim($_POST['user_id'] ?? '');

    $report = $reportRepo->findById($reportId);

    switch ($action) {
        case 'takedown':
            if ($report && $report['thread_id']) {
                $threadRepo->delete($report['thread_id']);
            }
            $reportRepo->updateStatus($reportId, 'takedown', $user['user_id']);
            set_flash('success', 'Konten berhasil di-takedown.');
            break;

        case 'dismiss':
            $reportRepo->updateStatus($reportId, 'dismissed', $user['user_id']);
            set_flash('success', 'Laporan diabaikan.');
            break;

        case 'warn':
            // Set restricted untuk user terkait
            if (!empty($userId)) {
                $userRepo->updateStatus($userId, 'restricted');
            }
            $reportRepo->updateStatus($reportId, 'warning', $user['user_id']);
            set_flash('success', 'Peringatan dikirim dan hak posting dibatasi.');
            break;
    }

    redirect(BASE_URL . '/moderator/reports.php');
}

$statusFilter = $_GET['status'] ?? 'pending';
$reports      = $reportRepo->getAll($statusFilter);
$pendingCount = $reportRepo->countPending();
$flash        = get_flash();
$pageCSS      = ['homepage.css', 'dashboard.css'];
$title        = 'Panel Moderator';
?>
<!DOCTYPE html>
<html lang="id">
<?php include_once __DIR__ . '/../components/metadata.php'; ?>
<body style="background:var(--gray-50,#f9fafb);">
    <?php include_once __DIR__ . '/../components/navbar.php'; ?>

    <div class="dashboard-page">
        <!-- Sidebar -->
        <aside class="dash-sidebar">
            <p class="dash-sidebar-title">Moderasi</p>
            <a href="<?= BASE_URL ?>/moderator/reports.php?status=pending"
               class="dash-nav-item <?= $statusFilter === 'pending' ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
                Laporan Pending
                <?php if ($pendingCount > 0): ?>
                    <span class="dash-badge"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= BASE_URL ?>/moderator/reports.php?status=takedown"
               class="dash-nav-item <?= $statusFilter === 'takedown' ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                Ditakedown
            </a>
            <a href="<?= BASE_URL ?>/moderator/reports.php?status=dismissed"
               class="dash-nav-item <?= $statusFilter === 'dismissed' ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                Diabaikan
            </a>

            <p class="dash-sidebar-title">Lainnya</p>
            <a href="<?= BASE_URL ?>/" class="dash-nav-item">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                Kembali ke Forum
            </a>
        </aside>

        <!-- Main Content -->
        <main class="dash-content">
            <div class="dash-header">
                <h1>Panel Moderator</h1>
                <p>Validasi laporan dan terapkan sanksi terhadap konten yang melanggar</p>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?= e($flash['type']) ?>" style="margin-bottom:1.5rem;">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>

            <div class="dash-table-card">
                <div class="dash-table-card-header">
                    <h2>
                        Laporan
                        <span class="status-badge status-<?= e($statusFilter) ?>" style="margin-left:0.5rem;">
                            <?= match($statusFilter) {
                                'pending'   => 'Pending',
                                'takedown'  => 'Ditakedown',
                                'dismissed' => 'Diabaikan',
                                default     => ucfirst($statusFilter),
                            } ?>
                        </span>
                    </h2>
                    <span style="font-size:0.875rem;color:var(--gray-400,#9ca3af);"><?= count($reports) ?> laporan</span>
                </div>

                <?php if (empty($reports)): ?>
                    <div class="empty-state" style="padding:3rem;">
                        <h3>Tidak ada laporan</h3>
                        <p>Tidak ada laporan dengan status ini.</p>
                    </div>
                <?php else: ?>
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Pelapor</th>
                                <th>Thread/Konten</th>
                                <th>Alasan</th>
                                <th>Waktu</th>
                                <th>Status</th>
                                <?php if ($statusFilter === 'pending'): ?>
                                    <th>Aksi</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $r): ?>
                                <tr>
                                    <td><strong><?= e($r['reporter_username']) ?></strong></td>
                                    <td>
                                        <?php if ($r['thread_title']): ?>
                                            <a href="<?= BASE_URL ?>/thread.php?id=<?= e($r['related_thread_id'] ?? $r['thread_id']) ?>"
                                               style="color:#155DFC;font-weight:500;">
                                                <?= e(truncate($r['thread_title'], 50)) ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400,#9ca3af);">Komentar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width:250px;"><?= e(truncate($r['reason'], 80)) ?></td>
                                    <td style="white-space:nowrap;"><?= time_ago($r['reported_at']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= e($r['status']) ?>">
                                            <?= e(ucfirst($r['status'])) ?>
                                        </span>
                                    </td>
                                    <?php if ($statusFilter === 'pending'): ?>
                                        <td>
                                            <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                                                <!-- Takedown -->
                                                <form method="POST" onsubmit="return confirm('Takedown konten ini?')">
                                                    <input type="hidden" name="action" value="takedown">
                                                    <input type="hidden" name="report_id" value="<?= e($r['report_id']) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf() ?>">
                                                    <button type="submit" class="table-action-btn table-action-danger">Takedown</button>
                                                </form>

                                                <!-- Warn -->
                                                <?php if ($r['thread_id'] && !empty($r['thread_author_id'])): ?>
                                                    <form method="POST" onsubmit="return confirm('Batasi posting user ini?')">
                                                        <input type="hidden" name="action" value="warn">
                                                        <input type="hidden" name="report_id" value="<?= e($r['report_id']) ?>">
                                                        <input type="hidden" name="user_id" value="<?= e($r['thread_author_id']) ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf() ?>">
                                                        <button type="submit" class="table-action-btn table-action-warning">Warning</button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- Dismiss -->
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="dismiss">
                                                    <input type="hidden" name="report_id" value="<?= e($r['report_id']) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf() ?>">
                                                    <button type="submit" class="table-action-btn" style="border-color:var(--gray-300,#d1d5db);color:var(--gray-500,#6b7280);">Abaikan</button>
                                                </form>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php include_once __DIR__ . '/../components/footer.php'; ?>
</body>
</html>
