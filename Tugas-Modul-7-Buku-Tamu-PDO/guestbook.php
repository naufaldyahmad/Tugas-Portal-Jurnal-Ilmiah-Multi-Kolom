<?php
session_start();
require_once 'GuestBook.php';

// Konfigurasi Database
$host = '127.0.0.1';
$db   = 'perpustakaan_db';
$user = 'root'; // Sesuaikan jika perlu
$pass = ''; // Sesuaikan jika perlu
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Koneksi database gagal. Pastikan database dan tabel sudah dibuat.");
}

$guestBook = new GuestBook($pdo);
$errors = [];
$successMessage = '';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['csrf'] = "Validasi token gagal. Silakan muat ulang halaman.";
    }

    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pesan = trim($_POST['pesan'] ?? '');

    // Validation
    if (empty($nama)) {
        $errors['nama'] = "Nama wajib diisi.";
    }

    if (empty($email)) {
        $errors['email'] = "Email wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Format email tidak valid.";
    }

    if (empty($pesan)) {
        $errors['pesan'] = "Pesan wajib diisi.";
    } elseif ((function_exists('mb_strlen') ? mb_strlen($pesan) : strlen($pesan)) < 5) {
        $errors['pesan'] = "Pesan minimal 5 karakter.";
    }

    if (empty($errors)) {
        try {
            $guestBook->addMessage($nama, $email, $pesan);
            $_SESSION['success_message'] = "Pesan berhasil dikirim!";
            // Redirect to prevent form resubmission (PRG pattern)
            header("Location: guestbook.php");
            exit;
        } catch (Exception $e) {
            $errors['db'] = $e->getMessage();
        }
    }
}

// Check for success message in session
if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get all messages
try {
    $messages = $guestBook->getMessages();
} catch (Exception $e) {
    $messages = [];
    $errors['db_fetch'] = $e->getMessage();
}

// Helper untuk sanitasi output XSS
function sanitize(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Guestbook | Dark Futuristic Dashboard</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        :root {
            --bg-color: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --border-color: rgba(56, 189, 248, 0.2);
            --accent-color: #38bdf8;
            --accent-hover: #0ea5e9;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --danger: #ef4444;
            --success: #10b981;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(56, 189, 248, 0.05), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(139, 92, 246, 0.05), transparent 25%);
            color: var(--text-main);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            flex: 1;
            width: 100%;
        }

        /* Header */
        header {
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }

        .logo {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .logo span {
            color: var(--accent-color);
            text-shadow: 0 0 15px rgba(56, 189, 248, 0.5);
        }

        .subtitle {
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-top: 1rem;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background-color: var(--success);
            border-radius: 50%;
            box-shadow: 0 0 8px var(--success);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        /* Stats */
        .stats {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 3rem;
            flex-wrap: wrap;
        }

        .stat-item {
            background: var(--card-bg);
            padding: 1rem 2rem;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
            text-align: center;
            min-width: 150px;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent-color);
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        /* Layout */
        .main-layout {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 2rem;
        }

        @media (max-width: 992px) {
            .main-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Card styles */
        .glass-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Form */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-muted);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 8px;
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .error-text {
            color: var(--danger);
            font-size: 0.875rem;
            margin-top: 0.5rem;
            display: block;
        }

        .btn-submit {
            width: 100%;
            padding: 0.875rem;
            background: var(--accent-color);
            color: #0f172a;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(56, 189, 248, 0.3);
        }

        /* Messages List */
        .message-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-height: 600px;
            overflow-y: auto;
            padding-right: 0.5rem;
        }

        /* Scrollbar customization */
        .message-list::-webkit-scrollbar {
            width: 6px;
        }
        .message-list::-webkit-scrollbar-track {
            background: rgba(15, 23, 42, 0.5);
            border-radius: 4px;
        }
        .message-list::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.3);
            border-radius: 4px;
        }

        .message-item {
            background: rgba(15, 23, 42, 0.4);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            transition: transform 0.2s, background 0.2s;
        }

        .message-item:hover {
            background: rgba(15, 23, 42, 0.6);
            border-color: rgba(56, 189, 248, 0.3);
            transform: translateX(4px);
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
            padding-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .message-author {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-color), #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #fff;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .author-info {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .author-name {
            font-weight: 600;
            color: var(--text-main);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .author-email {
            font-size: 0.75rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-date {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .message-content {
            color: var(--text-main);
            font-size: 0.95rem;
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--success);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }

        /* Footer */
        footer {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
            font-size: 0.875rem;
            border-top: 1px solid rgba(255,255,255,0.05);
            margin-top: auto;
        }

        /* Char count */
        .char-count {
            text-align: right;
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }
        
        /* Table styles for mobile responsiveness (using CSS Grid/Flexbox mainly) */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            .glass-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1 class="logo">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--accent-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path>
            </svg>
            Library <span>Guestbook</span>
        </h1>
        <p class="subtitle">Ruang digital untuk berbagi pesan dan kesan</p>
        <div class="status-badge">
            <div class="status-dot"></div>
            System Online
        </div>
    </header>

    <div class="stats">
        <div class="stat-item">
            <div class="stat-value"><?= count($messages) ?></div>
            <div class="stat-label">Total Pesan</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">Secure</div>
            <div class="stat-label">Database Status</div>
        </div>
    </div>

    <div class="main-layout">
        <!-- Form Column -->
        <div class="glass-card">
            <h2 class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 20h9"></path>
                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                </svg>
                Tulis Pesan
            </h2>

            <?php if ($successMessage): ?>
                <div class="alert alert-success">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <?= sanitize($successMessage) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($errors['csrf'])): ?>
                <div class="alert alert-danger">
                    <?= sanitize($errors['csrf']) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($errors['db'])): ?>
                <div class="alert alert-danger">
                    <?= sanitize($errors['db']) ?>
                </div>
            <?php endif; ?>

            <form action="guestbook.php" method="POST" id="guestbookForm">
                <input type="hidden" name="csrf_token" value="<?= sanitize($_SESSION['csrf_token']) ?>">
                
                <div class="form-group">
                    <label for="nama" class="form-label">Nama Lengkap</label>
                    <input type="text" id="nama" name="nama" class="form-control" value="<?= isset($_POST['nama']) && empty($successMessage) ? sanitize($_POST['nama']) : '' ?>" placeholder="John Doe" required>
                    <?php if (isset($errors['nama'])): ?>
                        <span class="error-text"><?= sanitize($errors['nama']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Alamat Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= isset($_POST['email']) && empty($successMessage) ? sanitize($_POST['email']) : '' ?>" placeholder="john@example.com" required>
                    <?php if (isset($errors['email'])): ?>
                        <span class="error-text"><?= sanitize($errors['email']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="pesan" class="form-label">Pesan / Kesan</label>
                    <textarea id="pesan" name="pesan" class="form-control" placeholder="Tuliskan pesan Anda di sini..." required oninput="updateCharCount(this)"><?= isset($_POST['pesan']) && empty($successMessage) ? sanitize($_POST['pesan']) : '' ?></textarea>
                    <div class="char-count"><span id="charCount">0</span> karakter</div>
                    <?php if (isset($errors['pesan'])): ?>
                        <span class="error-text"><?= sanitize($errors['pesan']) ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-submit">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                    Kirim Pesan
                </button>
            </form>
        </div>

        <!-- Messages Column -->
        <div class="glass-card">
            <h2 class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                Riwayat Pesan
            </h2>

            <?php if (isset($errors['db_fetch'])): ?>
                <div class="alert alert-danger">
                    <?= sanitize($errors['db_fetch']) ?>
                </div>
            <?php endif; ?>

            <div class="message-list">
                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📂</div>
                        <h3>Belum ada pesan</h3>
                        <p>Jadilah yang pertama meninggalkan pesan di perpustakaan digital ini.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="message-item">
                            <div class="message-header">
                                <div class="message-author">
                                    <div class="avatar">
                                        <?= sanitize(strtoupper(substr($msg['nama'], 0, 1))) ?>
                                    </div>
                                    <div class="author-info">
                                        <span class="author-name"><?= sanitize($msg['nama']) ?></span>
                                        <span class="author-email"><?= sanitize($msg['email']) ?></span>
                                    </div>
                                </div>
                                <div class="message-date">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                    <?= date('d M Y, H:i', strtotime($msg['tanggal_kirim'])) ?>
                                </div>
                            </div>
                            <div class="message-content"><?= sanitize($msg['pesan']) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<footer>
    <p>Library Guestbook &mdash; Modul 7 Pemrograman Web &copy; <?= date('Y') ?></p>
</footer>

<script>
    function updateCharCount(textarea) {
        const count = textarea.value.length;
        document.getElementById('charCount').textContent = count;
    }
    
    // Initialize char count on load
    document.addEventListener('DOMContentLoaded', function() {
        const textarea = document.getElementById('pesan');
        if (textarea) {
            updateCharCount(textarea);
        }
    });
</script>

</body>
</html>
