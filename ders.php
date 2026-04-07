<?php
// ders.php - Eğitim İçeriği, Terminal ve Sınav (Quiz) Laboratuvarı
session_start();
require 'db.php';

// Güvenlik: Giriş yapmayanları dışarı at
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ==========================================
// AJAX İSTEĞİ: GÖREVİ TAMAMLAMA VE XP KAZANMA
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'complete_lesson') {
    $lesson_id = $_POST['lesson_id'];
    
    // Ders zaten tamamlanmış mı diye kontrol et (Kullanıcı sürekli F5 yapıp puan kasmasın diye)
    $check = $pdo->prepare("SELECT * FROM user_progress WHERE user_id = ? AND lesson_id = ?");
    $check->execute([$user_id, $lesson_id]);
    
    if (!$check->fetch()) {
        // Tamamlanmadıysa ilerlemeyi kaydet ve 50 XP ekle
        $pdo->prepare("INSERT INTO user_progress (user_id, lesson_id, is_completed) VALUES (?, ?, 1)")->execute([$user_id, $lesson_id]);
        $pdo->prepare("UPDATE users SET score = score + 50 WHERE id = ?")->execute([$user_id]);
    }
    
    echo "OK"; // JavaScript'e işlemin başarılı olduğunu bildiriyoruz
    exit;
}

// ==========================================
// KULLANICI BİLGİLERİNİ ÇEKME (Header İçin)
// ==========================================
$stmt = $pdo->prepare("SELECT username, score, profile_pic FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$score = $user['score'];
$profile_pic = $user['profile_pic'];

// ==========================================
// AKTİF DERSİ ÇEKME
// ==========================================
$current_lesson_id = $_GET['id'] ?? null;
$current_lesson = null;
$question = null;

if ($current_lesson_id) {
    // Dersi Çek
    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE id = ?");
    $stmt->execute([$current_lesson_id]);
    $current_lesson = $stmt->fetch();

    // Derse ait Soruyu/Görevi Çek
    if ($current_lesson) {
        $qStmt = $pdo->prepare("SELECT * FROM questions WHERE lesson_id = ?");
        $qStmt->execute([$current_lesson_id]);
        $question = $qStmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title><?= $current_lesson ? htmlspecialchars($current_lesson['title']) : 'Laboratuvar' ?> | Siber Akademi</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Header Profil Stilleri */
        .header-profile { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; color: #fff; transition: 0.3s; padding: 5px 10px; border-radius: 5px; }
        .header-profile:hover { background: #111; color: var(--accent-color); }
        .header-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-color); }
        .header-avatar-letter { width: 32px; height: 32px; border-radius: 50%; background: #111; border: 2px solid var(--accent-color); display: flex; align-items: center; justify-content: center; font-weight: bold; color: var(--accent-color); font-size: 1.1rem; }
        
        /* Aktif Ders Menü Rengi */
        .lesson-item.active a { color: var(--accent-color); font-weight: bold; }
        .lesson-item.active { border-left: 2px solid var(--accent-color); padding-left: 8px; }
    </style>
</head>
<body>
<header class="header">
    <h1><a href="index.php">>_ Siber Akademi</a></h1>
    <div style="display: flex; align-items: center; gap: 15px;">
        
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="admin.php" style="color: #ff4c4c; font-weight: bold; border: 1px dashed #ff4c4c; padding: 4px 8px; border-radius: 4px; text-decoration: none; background: #220000;" onmouseover="this.style.background='#ff4c4c'; this.style.color='#fff';" onmouseout="this.style.background='#220000'; this.style.color='#ff4c4c';">[ROOT] Panele Git</a>
        <?php endif; ?>

        <span class="score-board" style="margin-right: 10px;">Puan: <span id="nav-score"><?= $score ?></span> XP</span>
        
        <a href="profil.php" class="header-profile" title="Profilime Git">
            <?php if (!empty($profile_pic)): ?>
                <img src="uploads/avatars/<?= htmlspecialchars($profile_pic) ?>" alt="PP" class="header-avatar">
            <?php else: ?>
                <div class="header-avatar-letter"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
            <?php endif; ?>
            <span><?= htmlspecialchars($_SESSION['username']) ?></span>
        </a>
        
        <a href="logout.php" style="margin-left: 10px; color: #ff4c4c;">[Çıkış]</a>
    </div>
</header>

<div class="lms-container">
    <div class="sidebar">
        <h3>Eğitim Modülleri</h3><br>
        <?php
        $categories = $pdo->query("SELECT * FROM categories ORDER BY order_index")->fetchAll();
        foreach ($categories as $cat) {
            // Eğer aktif ders bu kategorinin içindeyse, menüyü açık bırak
            $is_cat_active = false;
            if ($current_lesson && $current_lesson['category_id'] == $cat['id']) {
                $is_cat_active = true;
            }
            
            $cat_class = $is_cat_active ? "active" : "";
            
            echo "<div class='category-title' onclick='toggleMenu({$cat['id']})'>► {$cat['name']}</div>";
            echo "<ul class='lesson-list {$cat_class}' id='cat-{$cat['id']}'>";
            
            $lessons = $pdo->prepare("SELECT * FROM lessons WHERE category_id = ? ORDER BY order_index");
            $lessons->execute([$cat['id']]);
            
            foreach ($lessons as $lesson) {
                $prog = $pdo->prepare("SELECT is_completed FROM user_progress WHERE user_id = ? AND lesson_id = ?");
                $prog->execute([$user_id, $lesson['id']]);
                $isCompleted = $prog->fetchColumn();
                $tick = $isCompleted ? "<span class='completed-tick'>[✓]</span>" : "";

                // Aktif dersi CSS ile vurgula
                $active_style = ($current_lesson_id == $lesson['id']) ? "active" : "";

                echo "<li class='lesson-item {$active_style}'>
                        <a href='ders.php?id={$lesson['id']}'>{$lesson['title']}</a> $tick
                      </li>";
            }
            echo "</ul>";
        }
        ?>
        <div style="margin-top:30px; padding-top:15px; border-top:1px dashed #333;">
            <a href="index.php" style="color:#888; text-decoration:none; font-size:0.9rem;">&lt; Dashboard'a Dön</a>
        </div>
    </div>

    <div class="main-content">
        <?php if ($current_lesson): ?>
            <div class='lesson-card'>
                <h2 style="color:var(--accent-color); font-size: 2rem; border-bottom: 1px solid #333; padding-bottom:10px; margin-bottom:20px;">
                    <?= htmlspecialchars($current_lesson['title']) ?>
                </h2>
                
                <?php if ($current_lesson['image_url']): ?>
                    <img src='<?= htmlspecialchars($current_lesson['image_url']) ?>' alt='Ders Görseli' style='max-width:100%; border-radius:8px; border:1px solid #333; margin-bottom:20px;'>
                <?php endif; ?>
                
                <div class='lesson-content' style="line-height: 1.8; color: #ddd; font-size: 1.05rem;">
                    <?= $current_lesson['content'] ?> </div>

                <?php if ($question): ?>
                    <div class='task-box' style="margin-top: 40px; background: #050505; border: 1px solid #222; border-left: 4px solid var(--accent-color); padding: 20px; border-radius: 4px;">
                        
                        <h3 style="color:#fff; margin-bottom:15px;">>_ GÖREV: <?= htmlspecialchars($question['question_text']) ?></h3>
                        
                        <?php
                        $qProg = $pdo->prepare("SELECT is_completed FROM user_progress WHERE user_id = ? AND lesson_id = ?");
                        $qProg->execute([$user_id, $current_lesson_id]);
                        $already_completed = $qProg->fetchColumn();
                        ?>

                        <?php if ($already_completed): ?>
                            <div style="background: rgba(0,255,0,0.1); color: var(--accent-color); padding: 10px; border-radius: 4px; border: 1px dashed var(--accent-color);">
                                [+] BU GÖREVİ BAŞARIYLA TAMAMLADINIZ.
                            </div>
                        <?php else: ?>
                            
                            <?php if ($question['type'] == 'quiz'): ?>
                                <?php $opts = json_decode($question['options']); ?>
                                <div id='quiz-area' style='margin-top:10px;'>
                                    <?php foreach ($opts as $opt): ?>
                                        <?php $val = substr($opt, 0, 1); // A, B, C'yi alır ?>
                                        <label style='display:block; margin:8px 0; cursor:pointer; background:#111; padding:10px; border-radius:4px; border:1px solid #222;'>
                                            <input type='radio' name='quiz_ans' value='<?= htmlspecialchars($val) ?>'> <?= htmlspecialchars($opt) ?>
                                        </label>
                                    <?php endforeach; ?>
                                    
                                    <?php $safe_ans = htmlspecialchars($question['correct_answer'], ENT_QUOTES); ?>
                                    <button onclick='checkQuiz("<?= $safe_ans ?>", <?= $current_lesson_id ?>)' style='margin-top:15px; padding:10px 20px;'>Cevabı Gönder</button>
                                </div>
                            
                            <?php elseif ($question['type'] == 'terminal'): ?>
                                <div class='terminal' style="background:#000; padding:15px; border-radius:5px; border:1px solid #333; font-family:monospace; margin-top:15px;">
                                    <span class='prompt' style="color:var(--accent-color);">hacker@kali:~$</span>
                                    <input type='text' id='term-input' class='term-input' autocomplete='off' spellcheck='false' autofocus style="background:transparent; border:none; color:#fff; width:70%; outline:none; font-family:monospace; font-size:1rem;">
                                </div>
                                <?php $safe_ans = htmlspecialchars($question['correct_answer'], ENT_QUOTES); ?>
                                <input type='hidden' id='correct_cmd' value='<?= $safe_ans ?>'>
                                <input type='hidden' id='current_lesson_id' value='<?= $current_lesson_id ?>'>
                                <p style="font-size:0.8rem; color:#666; margin-top:5px;">* Komutu yazıp Enter'a basın.</p>
                            <?php endif; ?>

                            <p id='feedback' style='margin-top:15px; font-weight:bold;'></p>
                        <?php endif; ?>

                    </div>
                <?php endif; ?>

            </div>
        <?php else: ?>
            <div class='lesson-card' style="text-align: center; padding: 50px;">
                <h2 style='color:#ff4c4c;'>404 - Laboratuvar Bulunamadı</h2>
                <p style='color:#aaa; margin-top:10px;'>Seçtiğiniz ders sistemde mevcut değil veya silinmiş olabilir.</p>
                <button onclick="window.location.href='index.php'" style="margin-top:20px;">Ana Ekrana Dön</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Sidebar Akordiyon Menü
function toggleMenu(id) {
    const list = document.getElementById('cat-' + id);
    if(list.classList.contains('active')) {
        list.classList.remove('active');
    } else {
        list.classList.add('active');
    }
}

document.addEventListener('DOMContentLoaded', (event) => {
    document.querySelectorAll('.lesson-list.active').forEach(list => {
        list.style.display = 'block';
    });
});

// ==========================================
// AJAX İLE GÖREVİ TAMAMLAMA İŞLEMİ
// ==========================================
function markCompleted(lesson_id) {
    const formData = new FormData();
    formData.append('action', 'complete_lesson');
    formData.append('lesson_id', lesson_id);

    fetch('ders.php', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(data => {
        if(data.trim() === 'OK') {
            document.getElementById('feedback').innerText = "[+] GÖREV BAŞARILI! Cihaza sızıldı. (+50 XP)";
            document.getElementById('feedback').style.color = "var(--accent-color)";
            
            let currentScore = parseInt(document.getElementById('nav-score').innerText);
            document.getElementById('nav-score').innerText = currentScore + 50;
            
            setTimeout(() => location.reload(), 2000);
        } else {
            document.getElementById('feedback').innerText = "[!] Bir hata oluştu ama görev kaydedildi. Sayfa yenileniyor...";
            setTimeout(() => location.reload(), 1500);
        }
    })
    .catch(err => console.error("Bağlantı Hatası:", err));
}

// ==========================================
// TEST (QUİZ) KONTROL MEKANİZMASI (Büyük/Küçük Harf Korumalı)
// ==========================================
function checkQuiz(correct_ans, lesson_id) {
    const selected = document.querySelector('input[name="quiz_ans"]:checked');
    if (!selected) { 
        alert("Lütfen bir şık seçin."); 
        return; 
    }
    
    // Hem adminin girdiği cevabı hem öğrencinin seçtiğini küçük harfe çevirip boşluklarını siliyoruz
    const safeCorrectAns = correct_ans.trim().toLowerCase();
    const safeUserAns = selected.value.trim().toLowerCase();

    if (safeUserAns === safeCorrectAns) {
        document.querySelectorAll('input[name="quiz_ans"]').forEach(el => el.disabled = true);
        markCompleted(lesson_id);
    } else {
        document.getElementById('feedback').innerText = "[-] YANLIŞ CEVAP! Sistem tespit etti. Tekrar deneyin.";
        document.getElementById('feedback').style.color = "#ff4c4c";
    }
}

// ==========================================
// TERMİNAL SİMÜLASYONU (Alternatifli ve B/K Harf Korumalı)
// ==========================================
const termInput = document.getElementById('term-input');
if (termInput) {
    termInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            // 1. Öğrencinin yazdığını temizle: Fazla boşlukları tek boşluk yap ve küçük harfe çevir
            const userInput = this.value.trim().replace(/\s+/g, ' ').toLowerCase();
            
            // 2. Veritabanından gelen doğru cevabı al
            const correctCmdString = document.getElementById('correct_cmd').value;
            
            // 3. | (dik çizgi) ile ayrılmış alternatifleri diziye çevir
            // Örn: "ls -la | ls -al"  =>  ["ls -la", "ls -al"]
            const validAnswers = correctCmdString.split('|').map(cmd => cmd.trim().replace(/\s+/g, ' ').toLowerCase());
            
            const lessonId = document.getElementById('current_lesson_id').value;
            
            // 4. Öğrencinin cevabı, bizim kabul ettiğimiz alternatifler dizisinin içinde var mı?
            if (validAnswers.includes(userInput)) {
                this.disabled = true; 
                markCompleted(lessonId);
            } else {
                // Güvenlik: XSS açığı olmaması için kullanıcının girdiği html kodlarını engelliyoruz
                const safeOutput = userInput.replace(/</g, "&lt;").replace(/>/g, "&gt;");
                document.getElementById('feedback').innerHTML = "bash: " + safeOutput + ": komut anlaşılamadı veya yetkisiz işlem.";
                document.getElementById('feedback').style.color = "#ff4c4c";
            }
        }
    });
}
</script>
</body>
</html>