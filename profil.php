<?php
// profil.php - Kullanıcı Profil ve Ayar Yönetimi (Fotoğraf Yükleme Eklendi)
session_start();
require 'db.php';

// Güvenlik: Giriş yapılmamışsa login sayfasına at
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = "";
$error = "";

// Kullanıcının güncel verilerini baştan çek (Çünkü fotoğrafı PHP dosyasında da kullanacağız)
$stmt = $pdo->prepare("SELECT username, email, score, role, profile_pic FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Form gönderildiğinde (Güncelleme İşlemi)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $new_password = $_POST['new_password'];
    $new_avatar_filename = $user['profile_pic']; // Varsayılan olarak mevcut fotoğraf kalsın

    // --- FOTOĞRAF YÜKLEME MANTIĞI ---
    if (!empty($_FILES['profile_pic']['name'])) {
        $file = $_FILES['profile_pic'];
        $upload_dir = 'uploads/avatars/';
        
        // Dosya uzantısını al
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

        // Güvenlik Kontrolleri
        if (!in_array($file_ext, $allowed_exts)) {
            $error = "Hata: Sadece JPG, JPEG, PNG ve GIF dosyalarına izin verilir.";
        } elseif ($file['size'] > 2 * 1024 * 1024) { // 2MB Limit
            $error = "Hata: Dosya boyutu çok büyük (Max 2MB).";
        } else {
            // Güvenli dosya adı oluştur (kullanıcı_id + rastgele + uzantı)
            $new_filename = 'u_' . $user_id . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;

            // Dosyayı sunucuya taşı
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Eğer eski bir fotoğrafı varsa, onu klasörden sil (yer kaplamasın)
                if (!empty($user['profile_pic']) && file_exists($upload_dir . $user['profile_pic'])) {
                    unlink($upload_dir . $user['profile_pic']);
                }
                $new_avatar_filename = $new_filename; // Veritabanına yazmak için yeni adı kaydet
            } else {
                $error = "Fotoğraf yüklenirken teknik bir hata oluştu.";
            }
        }
    }

    // --- VERİTABANI GÜNCELLEME ---
    // Eğer hata oluşmadıysa güncellemeye devam et
    if (empty($error)) {
        try {
            // Şifre boşsa sadece mail ve avatarı güncelle
            if (empty($new_password)) {
                $stmt = $pdo->prepare("UPDATE users SET email = ?, profile_pic = ? WHERE id = ?");
                $stmt->execute([$email, $new_avatar_filename, $user_id]);
            } else {
                // Şifre doldurulmuşsa onu da güncelle
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET email = ?, password = ?, profile_pic = ? WHERE id = ?");
                $stmt->execute([$email, $hashed_password, $new_avatar_filename, $user_id]);
            }
            
            // Başarılı güncelleme sonrası sayfayı yenileyelim (Güncel veriyi görmek için)
            header("Location: profil.php?updated=1");
            exit;
        } catch (PDOException $e) {
            error_log("Profil Güncelleme Hatası: " . $e->getMessage());
            $error = "Bilgiler güncellenirken geçici bir hata oluştu.";
        }
    }
}

// Başarılı güncelleme mesajı (Sayfa yenilendiğinde)
if(isset($_GET['updated'])) {
    $success = "Profil bilgileri başarıyla güncellendi!";
}

// Oyunlaştırma: Puan (XP) tabanlı rütbe hesaplama
$score = $user['score'] ?? 0;
$rank = "Script Kiddie"; // Varsayılan Rütbe
$rank_color = "#aaa";

if ($score >= 1000) { $rank = "Root Master"; $rank_color = "#ff4c4c"; }
elseif ($score >= 500) { $rank = "Cyber Ninja"; $rank_color = "#ffb84d"; }
elseif ($score >= 200) { $rank = "Ethical Hacker"; $rank_color = "var(--accent-color)"; }
elseif ($score >= 50) { $rank = "Junior Analyst"; $rank_color = "#4da6ff"; }

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Profilim | Siber Akademi</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
        }
        
        .stat-card {
            background: #0a0a0a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
        }
        
        .avatar {
            width: 120px;
            height: 120px;
            background: #111;
            border: 2px dashed var(--accent-color);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden; /* Fotoğrafın yuvarlak çerçeveden taşmasını engeller */
        }
        
        /* Eğer fotoğraf varsa sığdırma stili */
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover; /* Fotoğrafın en boy oranını bozmadan çerçeveye sığdırır */
        }
        
        /* Fotoğraf yoksa harf avatar stili */
        .avatar-letter {
            font-size: 3rem;
            color: var(--accent-color);
            font-family: monospace;
        }
        
        .xp-text {
            font-size: 2rem;
            color: var(--accent-color);
            font-weight: bold;
            margin-top: 10px;
        }

        .settings-card {
            background: #0a0a0a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #888;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            background: #050505;
        }

        .back-btn {
            display: inline-block;
            margin-top: 20px;
            color: #888;
            text-decoration: none;
            border-bottom: 1px dashed #888;
        }
        .back-btn:hover { color: var(--accent-color); border-color: var(--accent-color); }
        
        /* Mobilde alt alta geçmesi için */
        @media (max-width: 768px) {
            .profile-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header class="header">
    <h1><a href="index.php">>_ Siber Akademi</a></h1>
    <div style="display: flex; align-items: center; gap: 15px;">
        
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="admin.php" style="color: #ff4c4c; font-weight: bold; border: 1px dashed #ff4c4c; padding: 4px 8px; border-radius: 4px; text-decoration: none; background: #220000;" onmouseover="this.style.background='#ff4c4c'; this.style.color='#fff';" onmouseout="this.style.background='#220000'; this.style.color='#ff4c4c';">[ROOT] Panele Git</a>
        <?php endif; ?>

        <span class="score-board">Puan: <?= $score ?> XP</span> | 
        <a href="logout.php" style="color: #ff4c4c;">[Çıkış]</a>
    </div>
</header>

<div class="profile-container">
    
    <div class="stat-card">
        <div class="avatar">
            <?php if (!empty($user['profile_pic'])): ?>
                <img src="uploads/avatars/<?= htmlspecialchars($user['profile_pic']) ?>" alt="Avatar">
            <?php else: ?>
                <div class="avatar-letter"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
            <?php endif; ?>
        </div>
        <h2 style="color: #fff; margin-bottom: 5px;"><?= htmlspecialchars($user['username']) ?></h2>
        
        <?php if($user['role'] == 'admin'): ?>
            <span style="background: #ff4c4c; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem;">YÖNETİCİ</span>
        <?php else: ?>
            <p style="color: <?= $rank_color ?>; font-weight: bold; font-size: 1.1rem;"><?= $rank ?></p>
        <?php endif; ?>

        <div class="xp-text"><?= $score ?> XP</div>
        <p style="color: #666; font-size: 0.85rem; margin-top: 10px;">Eğitimlere devam ederek seviye atlayın.</p>
        
        <a href="index.php" class="back-btn">&lt; Eğitim Paneline Dön</a>
    </div>

    <div class="settings-card">
        <h2 style="color: var(--accent-color); margin-bottom: 20px;">>_ Sistem Ayarları</h2>
        
        <?php if($error): ?>
            <p style='color: #ff4c4c; background: #331111; padding: 10px; border: 1px dashed #ff4c4c; margin-bottom: 15px; font-size: 0.9rem;'><?= $error ?></p>
        <?php endif; ?>
        <?php if($success): ?>
            <p style='color: var(--accent-color); background: #113311; padding: 10px; border: 1px dashed var(--accent-color); margin-bottom: 15px; font-size: 0.9rem;'><?= $success ?></p>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Kullanıcı Adı (Değiştirilemez)</label>
                <input type="text" value="<?= htmlspecialchars($user['username']) ?>" readonly style="color: #555; cursor: not-allowed;">
            </div>

            <div class="form-group">
                <label>Profil Fotoğrafı (Sadece JPG/PNG/GIF, Max 2MB)</label>
                <input type="file" name="profile_pic" accept="image/png, image/jpeg, image/jpg, image/gif" style="background: transparent; padding-left: 0; cursor:pointer;">
            </div>

            <div class="form-group">
                <label>E-posta Adresi</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="E-posta adresinizi girin" required>
            </div>

            <div class="form-group">
                <label>Yeni Şifre (Değiştirmek istemiyorsanız boş bırakın)</label>
                <input type="password" name="new_password" placeholder="********">
            </div>

            <button type="submit" style="width: 100%; margin-top: 10px;">> Bilgileri Güncelle</button>
        </form>
    </div>

</div>

</body>
</html>