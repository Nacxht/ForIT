<?php
/**
 * ReportRepository — Query database untuk tabel reports
 */
class ReportRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** Buat laporan baru */
    public function create(array $data): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO reports (report_id, thread_id, comment_id, reporter_id, reason)
            VALUES (:report_id, :thread_id, :comment_id, :reporter_id, :reason)
        ");
        return $stmt->execute([
            ':report_id'  => $data['report_id'],
            ':thread_id'  => $data['thread_id'] ?? null,
            ':comment_id' => $data['comment_id'] ?? null,
            ':reporter_id'=> $data['reporter_id'],
            ':reason'     => $data['reason'],
        ]);
    }

    /** Ambil semua laporan pending (untuk moderator) */
    public function getPending(): array
    {
        return $this->db->query("
            SELECT r.*,
                u.username AS reporter_username,
                u.fullname AS reporter_fullname,
                t.thread_title,
                t.thread_id AS related_thread_id
            FROM reports r
            INNER JOIN users u ON r.reporter_id = u.user_id
            LEFT JOIN threads t ON r.thread_id = t.thread_id
            WHERE r.status = 'pending'
            ORDER BY r.reported_at DESC
        ")->fetchAll();
    }

    /** Ambil semua laporan dengan filter status */
    public function getAll(string $status = ''): array
    {
        $where = $status ? "WHERE r.status = '$status'" : "";
        return $this->db->query("
            SELECT r.*,
                u.username AS reporter_username,
                t.thread_title,
                t.author_id AS thread_author_id
            FROM reports r
            INNER JOIN users u ON r.reporter_id = u.user_id
            LEFT JOIN threads t ON r.thread_id = t.thread_id
            $where
            ORDER BY r.reported_at DESC
        ")->fetchAll();
    }

    /** Update status laporan */
    public function updateStatus(string $reportId, string $status, string $reviewedBy): bool
    {
        $stmt = $this->db->prepare("
            UPDATE reports
            SET status = :status, reviewed_by = :reviewed_by, reviewed_at = NOW()
            WHERE report_id = :id
        ");
        return $stmt->execute([':status' => $status, ':reviewed_by' => $reviewedBy, ':id' => $reportId]);
    }

    /** Cari laporan berdasarkan ID */
    public function findById(string $reportId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM reports WHERE report_id = :id LIMIT 1");
        $stmt->execute([':id' => $reportId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /** Hitung laporan pending */
    public function countPending(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
    }
}
