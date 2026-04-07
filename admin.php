<?php
// admin.php - Grafikli Ana Gösterge Paneli (Dashboard)
session_start();
require 'db.php';

// Güvenlik
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("<div style='background:black; color:red; font-family:monospace; padding:20px;'>> ERİŞİM REDDEDİLDİ. YETKİ YOK.</div>");
}

try {
    // ==========================================
    // GRAFİKLER VE İSTATİSTİKLER İÇİN VERİ ÇEKİMİ
    // ==========================================

    // Genel İstatistikler
    $total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
    $total_lessons = $pdo->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
    $total_questions = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
    $total_completed = $pdo->query("SELECT COUNT(*) FROM user_progress WHERE is_completed=1")->fetchColumn();

    // GRAFİK 1: Rütbelere (XP) Göre Öğrenci Dağılımı
    $ranks = [
        'Root Master (1000+)' => $pdo->query("SELECT COUNT(*) FROM users WHERE score >= 1000 AND role='user'")->fetchColumn(),
        'Cyber Ninja (500-999)' => $pdo->query("SELECT COUNT(*) FROM users WHERE score >= 500 AND score < 1000 AND role='user'")->fetchColumn(),
        'Ethical Hacker (200-499)' => $pdo->query("SELECT COUNT(*) FROM users WHERE score >= 200 AND score < 500 AND role='user'")->fetchColumn(),
        'Junior Analyst (50-199)' => $pdo->query("SELECT COUNT(*) FROM users WHERE score >= 50 AND score < 200 AND role='user'")->fetchColumn(),
        'Script Kiddie (<50)' => $pdo->query("SELECT COUNT(*) FROM users WHERE score < 50 AND role='user'")->fetchColumn()
    ];

    // GRAFİK 2: En Çok Tamamlanan İlk 5 Ders (HATA BURADAYDI, up.id YERİNE up.lesson_id YAPILDI)
    $top_lessons = $pdo->query("SELECT l.title, COUNT(up.lesson_id) as comp_count FROM lessons l LEFT JOIN user_progress up ON l.id = up.lesson_id AND up.is_completed = 1 GROUP BY l.id ORDER BY comp_count DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    $lesson_names = [];
    $lesson_counts = [];
    foreach($top_lessons as $tl) {
        $lesson_names[] = $tl['title'];
        $lesson_counts[] = $tl['comp_count'];
    }

} catch (PDOException $e) {
    die("<div style='background:#220000; color:#ff4c4c; padding:20px; font-family:monospace; font-size:1.2rem;'>> SİSTEM ÇÖKTÜ (DB HATASI): <br><br>" . $e->getMessage() . "</div>");
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | Siber Akademi</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .admin-nav { background: #111; padding: 15px 20px; border-bottom: 2px solid var(--accent-color); display: flex; gap: 20px; }
        .admin-nav a { font-weight: bold; padding: 8px 15px; border-radius: 4px; transition: 0.3s; text-decoration: none; color: #aaa; border: 1px solid transparent; }
        .admin-nav a:hover, .admin-nav a.active { color: var(--accent-color); border-color: var(--accent-color); background: rgba(0,255,0,0.05); }
        
        .stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 30px 20px; }
        .stat-card { background: #0a0a0a; border: 1px solid #333; padding: 25px; border-radius: 8px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.5); }
        .stat-card h3 { color: #888; font-size: 1rem; text-transform: uppercase; margin-bottom: 10px; }
        .stat-card .num { font-size: 2.5rem; color: var(--accent-color); font-family: monospace; font-weight: bold; }
        
        .chart-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 0 20px 40px 20px; }
        .chart-box { background: #0a0a0a; border: 1px solid #333; padding: 20px; border-radius: 8px; }
    </style>
</head>
<body>

<header class="header">
    <h1>>_ ROOT / DASHBOARD</h1>
    <div><a href="index.php" style="color:#aaa;">Vitrine Dön</a> | <a href="logout.php" style="color:#ff4c4c;">Çıkış</a></div>
</header>

<div class="admin-nav">
    <a href="admin.php" class="active">>_ Genel Bakış</a>
    <a href="admin_kull.php">>_ Kullanıcı Yönetimi</a>
    <a href="admin_ders.php">>_ Müfredat & Dersler</a>
</div>

<div class="stat-grid">
    <div class="stat-card"><h3>Toplam Öğrenci</h3><div class="num"><?= $total_users ?></div></div>
    <div class="stat-card"><h3>Aktif Ders</h3><div class="num"><?= $total_lessons ?></div></div>
    <div class="stat-card"><h3>Görev / Soru</h3><div class="num"><?= $total_questions ?></div></div>
    <div class="stat-card"><h3>Çözülen Laboratuvar</h3><div class="num"><?= $total_completed ?></div></div>
</div>

<div class="chart-grid">
    <div class="chart-box">
        <h3 style="color:var(--accent-color); margin-bottom: 20px; text-align:center;">Öğrenci Rütbe Dağılımı</h3>
        <canvas id="rankChart"></canvas>
    </div>
    
    <div class="chart-box">
        <h3 style="color:var(--accent-color); margin-bottom: 20px; text-align:center;">En Çok Tamamlanan 5 Görev</h3>
        <canvas id="lessonChart"></canvas>
    </div>
</div>

<script>
    Chart.defaults.color = '#888';
    Chart.defaults.font.family = 'monospace';

    const ctxRank = document.getElementById('rankChart').getContext('2d');
    new Chart(ctxRank, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($ranks)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($ranks)) ?>,
                backgroundColor: ['#ff4c4c', '#ffb84d', '#00ff41', '#4da6ff', '#555'],
                borderColor: '#0a0a0a',
                borderWidth: 2
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });

    const ctxLesson = document.getElementById('lessonChart').getContext('2d');
    new Chart(ctxLesson, {
        type: 'bar',
        data: {
            labels: <?= json_encode($lesson_names) ?>,
            datasets: [{
                label: 'Tamamlanma Sayısı',
                data: <?= json_encode($lesson_counts) ?>,
                backgroundColor: 'rgba(0, 255, 65, 0.2)',
                borderColor: '#00ff41',
                borderWidth: 1
            }]
        },
        options: { 
            responsive: true, 
            scales: { 
                y: { beginAtZero: true, grid: { color: '#222' } },
                x: { grid: { display: false } }
            },
            plugins: { legend: { display: false } }
        }
    });
</script>

</body>
</html>