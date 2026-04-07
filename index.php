<?php
// index.php - Landing Page ve Kullanıcı Gösterge Paneli (Dashboard)

// Çift session (oturum) açılmasını engelleyen akıllı kontrol
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'db.php';

$isLoggedIn = isset($_SESSION['user_id']);
$score = 0;
$profile_pic = null;

// Giriş yapılmışsa kullanıcı verilerini ve dashboard istatistiklerini çek
if ($isLoggedIn) {
    $user_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("SELECT username, score, profile_pic FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $score = $user['score'];
        $profile_pic = $user['profile_pic'];
        $_SESSION['username'] = $user['username'];
    }

    // 1. Liderlik Tablosu (Top 5)
    $leaderboard = $pdo->query("SELECT username, score FROM users WHERE role='user' ORDER BY score DESC LIMIT 5")->fetchAll();

    // 2. Sıradaki Görev (Henüz tamamlanmamış ilk ders)
    $next_lesson_stmt = $pdo->prepare("SELECT l.id, l.title, c.name as cat_name FROM lessons l JOIN categories c ON l.category_id = c.id WHERE l.id NOT IN (SELECT lesson_id FROM user_progress WHERE user_id = ?) ORDER BY c.order_index, l.order_index LIMIT 1");
    $next_lesson_stmt->execute([$user_id]);
    $next_lesson = $next_lesson_stmt->fetch();

    // 3. Son Tamamlanan Görevler (HATA BURADAYDI: ORDER BY up.id SİLİNDİ)
    $recent_stmt = $pdo->prepare("SELECT l.title FROM user_progress up JOIN lessons l ON up.lesson_id = l.id WHERE up.user_id = ? LIMIT 4");
    $recent_stmt->execute([$user_id]);
    $recent_lessons = $recent_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Siber Güvenlik Akademisi</title>
    <link rel="stylesheet" href="style.css">
    <style>
        html { scroll-behavior: smooth; }
        
        .feature-card { transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); cursor: default; border: 1px solid #333; }
        .feature-card:hover { transform: translateY(-15px) scale(1.03); box-shadow: 0 15px 30px rgba(0, 255, 0, 0.15); border-color: var(--accent-color); }
        .scroll-down-icon { font-size: 2rem; color: #555; margin-top: 40px; display: inline-block; animation: bounce 2s infinite; text-decoration: none; }
        .scroll-down-icon:hover { color: var(--accent-color); }
        @keyframes bounce { 0%, 20%, 50%, 80%, 100% { transform: translateY(0); } 40% { transform: translateY(-20px); } 60% { transform: translateY(-10px); } }
        
        .content-section { padding: 80px 10%; border-top: 1px dashed #222; background: #0a0a0a; text-align: left; }
        .content-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 50px; align-items: center; }
        .content-grid.reverse { direction: rtl; } 
        .content-grid.reverse > * { direction: ltr; }
        .section-title { color: var(--accent-color); font-size: 2.2rem; margin-bottom: 20px; }
        .section-text { color: #aaa; line-height: 1.8; font-size: 1.1rem; }
        .image-placeholder { width: 100%; height: 350px; background: #111; border: 2px dashed #444; border-radius: 10px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; transition: 0.5s; }
        .image-placeholder img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .image-placeholder:hover { border-color: var(--accent-color); box-shadow: 0 0 20px rgba(0,255,0,0.1); }
        .image-placeholder:hover img { transform: scale(1.1); } 

        .stats-section { display: flex; justify-content: space-around; padding: 60px 10%; background: #050505; border-top: 1px solid #1a1a1a; }
        .stat-box { text-align: center; }
        .stat-number { font-size: 3rem; color: var(--accent-color); font-weight: bold; font-family: monospace; }
        .stat-label { color: #888; font-size: 1rem; text-transform: uppercase; letter-spacing: 2px; }

        .footer { text-align: center; padding: 30px; background: #000; color: #444; border-top: 2px solid var(--accent-color); margin-top: 50px; font-size: 0.9rem; }

        .header-profile { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; color: #fff; transition: 0.3s; padding: 5px 10px; border-radius: 5px; }
        .header-profile:hover { background: #111; color: var(--accent-color); }
        .header-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-color); }
        .header-avatar-letter { width: 32px; height: 32px; border-radius: 50%; background: #111; border: 2px solid var(--accent-color); display: flex; align-items: center; justify-content: center; font-weight: bold; color: var(--accent-color); font-size: 1.1rem; }

        /* DASHBOARD ÖZEL STİLLERİ */
        .dash-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 30px; text-align: left; }
        .dash-card { background: #0a0a0a; border: 1px solid #333; padding: 25px; border-radius: 8px; position: relative; overflow: hidden; }
        .dash-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--accent-color); }
        .dash-title { color: #888; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 15px; display: flex; justify-content: space-between; }
        
        .leaderboard-list { list-style: none; padding: 0; margin: 0; }
        .leaderboard-list li { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px dashed #222; }
        .leaderboard-list li:last-child { border-bottom: none; }
        .leaderboard-list li.top-1 { color: #e3a010; font-weight: bold; }
        .leaderboard-list li.top-2 { color: #c0c0c0; font-weight: bold; }
        .leaderboard-list li.top-3 { color: #cd7f32; font-weight: bold; }

        .recent-list { list-style: none; padding: 0; margin: 0; }
        .recent-list li { padding: 8px 0; color: #ccc; font-size: 0.95rem; }
        .recent-list li::before { content: '[OK] '; color: var(--accent-color); font-family: monospace; }
        
        @media (max-width: 900px) { .dash-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<header class="header">
    <h1><a href="index.php">>_ Siber Akademi</a></h1>
    <div style="display: flex; align-items: center; gap: 15px;">
        <?php if ($isLoggedIn): ?>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="admin.php" style="color: #ff4c4c; font-weight: bold; border: 1px dashed #ff4c4c; padding: 4px 8px; border-radius: 4px; text-decoration: none; background: #220000;" onmouseover="this.style.background='#ff4c4c'; this.style.color='#fff';" onmouseout="this.style.background='#220000'; this.style.color='#ff4c4c';">[ROOT] Panele Git</a>
            <?php endif; ?>

            <span class="score-board" style="margin-right: 10px;">Puan: <?= $score ?> XP</span>
            
            <a href="profil.php" class="header-profile" title="Profilime Git">
                <?php if (!empty($profile_pic)): ?>
                    <img src="uploads/avatars/<?= htmlspecialchars($profile_pic) ?>" alt="PP" class="header-avatar">
                <?php else: ?>
                    <div class="header-avatar-letter"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
                <?php endif; ?>
                <span><?= htmlspecialchars($_SESSION['username']) ?></span>
            </a>
            
            <a href="logout.php" style="margin-left: 10px; color: #ff4c4c;">[Çıkış]</a>
        <?php else: ?>
            <a href="login.php">Sisteme Bağlan</a>
        <?php endif; ?>
    </div>
</header>

<?php if (!$isLoggedIn): ?>
<div class="hero">
    <h2>Gerçek Dünyada Hayatta Kalmayı Öğren</h2>
    <p>Etik hackerlık, sızma testi araçları ve ağ güvenliği eğitimleri ile dijital savunmanı güçlendir.</p>
    <button onclick="window.location.href='login.php'" style="font-size: 1.5rem; padding: 15px 30px; cursor:pointer;">> Eğitime Başla</button>

    <div class="features" style="margin-top: 60px;">
        <div class="feature-card">
            <h3 style="color:var(--accent-color)">İnteraktif Terminal</h3>
            <p>Gerçekçi Kali Linux terminal simülasyonu ile komutları pratik yaparak öğren.</p>
        </div>
        <div class="feature-card">
            <h3 style="color:var(--accent-color)">Oyunlaştırma</h3>
            <p>Görevleri tamamla, XP kazan ve siber güvenlik saflarında rütbeni yükselt.</p>
        </div>
        <div class="feature-card">
            <h3 style="color:var(--accent-color)">Kapsamlı Müfredat</h3>
            <p>Temel ağ protokollerinden ileri seviye sızma testi metodolojilerine her şey burada.</p>
        </div>
    </div>
    <a href="#hakkimizda" class="scroll-down-icon">↓</a>
</div>

<div id="hakkimizda" class="content-section">
    <div class="content-grid">
        <div>
            <h2 class="section-title">>_ Savunmayı Öğrenin</h2>
            <p class="section-text">Siber güvenlik sadece teori ile öğrenilmez. Akademimizde sanal ortamlar üzerinde gerçek saldırı ve savunma senaryolarını test ederek el becerisi kazanırsınız. Ağ mimarilerinden web uygulaması açıklarına kadar güncel zafiyetleri analiz edin.</p>
        </div>
        <div class="image-placeholder">
            <img src="resimler/web_proje_site.png" alt="Akademi Görseli 1">
        </div>
    </div>
</div>

<div class="content-section">
    <div class="content-grid reverse">
        <div>
            <h2 class="section-title">>_ Gelişmiş Eğitim Altyapısı</h2>
            <p class="section-text">Oyunlaştırılmış eğitim modelimiz ile öğrenirken sıkılmayacaksınız. Her tamamladığınız görev size XP kazandırır. Teorik bilgiyi terminal üzerindeki uygulamalarla anında pekiştirin. Şirketlerin aradığı donanıma sahip bir siber güvenlik uzmanı olma yolunda ilk adımınızı atın.</p>
        </div>
        <div class="image-placeholder">
            <img src="resimler/web_site_2.png" alt="Akademi Görseli 2">
        </div>
    </div>
</div>

<div class="stats-section">
    <div class="stat-box"><div class="stat-number">50+</div><div class="stat-label">Aktif Modül</div></div>
    <div class="stat-box"><div class="stat-number">100%</div><div class="stat-label">Uygulamalı Eğitim</div></div>
    <div class="stat-box"><div class="stat-number">7/24</div><div class="stat-label">Sanal Laboratuvar</div></div>
</div>

<div class="footer">
    <p>>_ Siber Güvenlik Akademisi © <?= date("Y") ?> | Tüm Hakları Saklıdır.</p>
    <p style="font-size: 0.8rem; margin-top:10px;">Geliştirici Eğitim Projesi</p>
</div>

<?php else: ?>

<div class="lms-container">
    
    <div class="sidebar">
        <h3>Eğitim Modülleri</h3><br>
        <?php
        $categories = $pdo->query("SELECT * FROM categories ORDER BY order_index")->fetchAll();
        foreach ($categories as $cat) {
            echo "<div class='category-title' onclick='toggleMenu({$cat['id']})'>► {$cat['name']}</div>";
            echo "<ul class='lesson-list' id='cat-{$cat['id']}'>";
            
            $lessons = $pdo->prepare("SELECT * FROM lessons WHERE category_id = ? ORDER BY order_index");
            $lessons->execute([$cat['id']]);
            foreach ($lessons as $lesson) {
                $prog = $pdo->prepare("SELECT is_completed FROM user_progress WHERE user_id = ? AND lesson_id = ?");
                $prog->execute([$_SESSION['user_id'], $lesson['id']]);
                $isCompleted = $prog->fetchColumn();
                $tick = $isCompleted ? "<span class='completed-tick'>[✓]</span>" : "";

                echo "<li class='lesson-item'>
                        <a href='ders.php?id={$lesson['id']}'>{$lesson['title']}</a> $tick
                      </li>";
            }
            echo "</ul>";
        }
        ?>
    </div>

    <div class="main-content" style="padding: 20px;">
        <h2 style="color: var(--accent-color); font-size: 2.2rem; margin-bottom: 5px;">>_ Komuta Merkezine Hoş Geldin!</h2>
        <p style="color: #aaa; margin-bottom: 30px;">Sistem durumun ve sıradaki görevlerin aşağıda listelenmiştir.</p>
        
        <div class="dash-grid">
            
            <div style="display: flex; flex-direction: column; gap: 30px;">
                
                <div class="dash-card">
                    <div class="dash-title"><span>Sıradaki Hedef</span> <span>[TARGET ACQUIRED]</span></div>
                    <?php if ($next_lesson): ?>
                        <h3 style="color: #fff; font-size: 1.5rem; margin-bottom: 10px;"><?= htmlspecialchars($next_lesson['title']) ?></h3>
                        <p style="color: #888; margin-bottom: 20px;">Modül: <?= htmlspecialchars($next_lesson['cat_name']) ?></p>
                        <a href="ders.php?id=<?= $next_lesson['id'] ?>" style="display: inline-block; background: var(--accent-color); color: #000; padding: 10px 20px; text-decoration: none; font-weight: bold; border-radius: 4px;">> Göreve Başla</a>
                    <?php else: ?>
                        <h3 style="color: var(--accent-color); font-size: 1.5rem; margin-bottom: 10px;">Tüm Görevler Tamamlandı!</h3>
                        <p style="color: #888;">Tebrikler, sistemdeki mevcut tüm eğitimleri bitirdiniz. Yeni görevler eklendiğinde burada görünecektir.</p>
                    <?php endif; ?>
                </div>

                <div class="dash-card">
                    <div class="dash-title">Terminal Logları (Son Başarılar)</div>
                    <ul class="recent-list">
                        <?php if (count($recent_lessons) > 0): ?>
                            <?php foreach ($recent_lessons as $rl): ?>
                                <li><?= htmlspecialchars($rl['title']) ?> başarıyla tamamlandı.</li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li style="color:#666;">Henüz bir görev tamamlanmadı.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div>
                <div class="dash-card">
                    <div class="dash-title">Liderlik Tablosu <span>[TOP 5]</span></div>
                    <ul class="leaderboard-list">
                        <?php 
                        $rank = 1;
                        foreach ($leaderboard as $lb): 
                            $class = "top-" . $rank;
                            $you_tag = ($lb['username'] == $_SESSION['username']) ? " <span style='font-size:0.7rem; color:var(--accent-color); border:1px solid var(--accent-color); padding:1px 4px; border-radius:3px;'>SEN</span>" : "";
                        ?>
                            <li class="<?= $rank <= 3 ? $class : '' ?>">
                                <span><?= $rank ?>. <?= htmlspecialchars($lb['username']) ?><?= $you_tag ?></span>
                                <span><?= $lb['score'] ?> XP</span>
                            </li>
                        <?php 
                            $rank++;
                        endforeach; 
                        
                        if (count($leaderboard) == 0) {
                            echo "<li style='color:#666;'>Henüz sıralama oluşmadı.</li>";
                        }
                        ?>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function toggleMenu(id) {
    const list = document.getElementById('cat-' + id);
    list.classList.toggle('active');
}
</script>
<?php endif; ?>
</body>
</html>