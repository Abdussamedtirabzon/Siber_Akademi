<?php
// admin_kull.php - Kullanıcı Yönetimi Paneli (Düzenleme ve Detay Görünümü)
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("<div style='background:black; color:red; font-family:monospace; padding:20px;'>> ERİŞİM REDDEDİLDİ. YETKİ YOK.</div>");
}

// =========================================
// 1. SİLME İŞLEMİ
// =========================================
if (isset($_GET['delete_user'])) {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$_GET['delete_user']]);
    $pdo->prepare("DELETE FROM user_progress WHERE user_id = ?")->execute([$_GET['delete_user']]);
    header("Location: admin_kull.php"); exit;
}

// =========================================
// 2. EKLEME VE GÜNCELLEME İŞLEMİ
// =========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_user'])) {
    $u_id = $_POST['user_id'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $score = $_POST['score'] ?? 0;
    
    if (!empty($u_id)) {
        // GÜNCELLEME (Edit)
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET username=?, email=?, password=?, role=?, score=? WHERE id=?")
                ->execute([$username, $email, $password, $role, $score, $u_id]);
        } else {
            $pdo->prepare("UPDATE users SET username=?, email=?, role=?, score=? WHERE id=?")
                ->execute([$username, $email, $role, $score, $u_id]);
        }
    } else {
        // YENİ EKLEME (Add)
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, email, password, role, score) VALUES (?, ?, ?, ?, ?)")
            ->execute([$username, $email, $password, $role, $score]);
    }
    header("Location: admin_kull.php"); exit;
}

// Düzenlenecek kullanıcıyı çek
$edit_u = null;
if (isset($_GET['edit_user'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$_GET['edit_user']]);
    $edit_u = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kullanıcı Yönetimi | Siber Akademi</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-nav { background: #111; padding: 15px 20px; border-bottom: 2px solid var(--accent-color); display: flex; gap: 20px; position: sticky; top: 0; z-index: 100;}
        .admin-nav a { font-weight: bold; padding: 8px 15px; border-radius: 4px; transition: 0.3s; text-decoration: none; color: #aaa; border: 1px solid transparent; }
        .admin-nav a:hover, .admin-nav a.active { color: var(--accent-color); border-color: var(--accent-color); background: rgba(0,255,0,0.05); }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 0.9rem; }
        th, td { border: 1px solid #333; padding: 10px; text-align: left; }
        th { background-color: #222; color: var(--accent-color); }
        tr.main-row { cursor: pointer; transition: background 0.3s; }
        tr.main-row:nth-child(4n-3) { background-color: #1a1f26; } 
        tr.main-row:hover { background-color: #2a323d; box-shadow: inset 2px 0 0 var(--accent-color); }
        
        .btn-sm { padding: 5px 10px; font-size: 0.8rem; border-radius: 3px; background: #333; color: #fff; text-decoration: none; border: 1px solid #555; }
        .btn-sm:hover { background: var(--accent-color); color: #000; }
        .btn-danger { background: #333; color: #ff4c4c; border-color: #ff4c4c; }
        .btn-danger:hover { background: #ff4c4c; color: #fff; }
        
        .form-box { background: #111; padding: 20px; border-radius: 8px; border: 1px solid #333; margin-bottom: 30px; }
        .edit-box { border: 1px solid var(--accent-color); box-shadow: 0 0 15px rgba(0,255,0,0.1); }
        .form-box input, .form-box select { width: 100%; margin-bottom: 10px; }

        .details-row { display: none; background-color: #050505; }
        .details-content { display: flex; gap: 20px; padding: 20px; border: 1px dashed #444; margin: 10px; border-radius: 5px; }
        .details-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-color); }
        .details-avatar-letter { width: 80px; height: 80px; border-radius: 50%; background: #111; border: 2px solid var(--accent-color); display: flex; align-items: center; justify-content: center; font-weight: bold; color: var(--accent-color); font-size: 2.5rem; }
    </style>
</head>
<body>

<header class="header">
    <h1>>_ ROOT / KULLANICI YÖNETİMİ</h1>
    <div><a href="index.php" style="color:#aaa;">Vitrine Dön</a> | <a href="logout.php" style="color:#ff4c4c;">Çıkış</a></div>
</header>

<div class="admin-nav">
    <a href="admin.php">>_ Genel Bakış</a>
    <a href="admin_kull.php" class="active">>_ Kullanıcı Yönetimi</a>
    <a href="admin_ders.php">>_ Müfredat & Dersler</a>
</div>

<div style="padding: 20px;">
    
    <div style="display: grid; grid-template-columns: 1fr 3fr; gap: 30px;">
        
        <div class="form-box <?= $edit_u ? 'edit-box' : '' ?>">
            <h3 style="color:var(--accent-color); margin-bottom: 15px;"><?= $edit_u ? '[~] Kullanıcıyı Düzenle' : '[+] Yeni Kullanıcı Ekle' ?></h3>
            <form method="POST">
                <input type="hidden" name="save_user" value="1">
                <input type="hidden" name="user_id" value="<?= $edit_u['id'] ?? '' ?>">
                
                <label style="color:#aaa; font-size:0.8rem;">Kullanıcı Adı</label>
                <input type="text" name="username" placeholder="Kullanıcı Adı" value="<?= $edit_u['username'] ?? '' ?>" required>
                
                <label style="color:#aaa; font-size:0.8rem;">E-posta Adresi</label>
                <input type="email" name="email" placeholder="E-posta Adresi" value="<?= $edit_u['email'] ?? '' ?>" required>
                
                <label style="color:#aaa; font-size:0.8rem;">Şifre <?= $edit_u ? '(Değişmeyecekse Boş Bırakın)' : '' ?></label>
                <input type="password" name="password" placeholder="<?= $edit_u ? '********' : 'Şifre Belirle' ?>" <?= $edit_u ? '' : 'required' ?>>
                
                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label style="color:#aaa; font-size:0.8rem;">Sistem Rolü</label>
                        <select name="role" required>
                            <option value="user" <?= ($edit_u && $edit_u['role']=='user')?'selected':'' ?>>Öğrenci</option>
                            <option value="admin" <?= ($edit_u && $edit_u['role']=='admin')?'selected':'' ?>>Yönetici</option>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label style="color:#aaa; font-size:0.8rem;">XP (Puan)</label>
                        <input type="number" name="score" value="<?= $edit_u['score'] ?? '0' ?>" required>
                    </div>
                </div>

                <button type="submit" style="width:100%; margin-top:10px;"><?= $edit_u ? 'Değişiklikleri Kaydet' : 'Sisteme Kaydet' ?></button>
                <?php if($edit_u) echo "<a href='admin_kull.php' class='btn-sm' style='display:block; text-align:center; margin-top:10px;'>İptal Et</a>"; ?>
            </form>
        </div>

        <div style="overflow-x: auto;">
            <h3 style="margin-bottom: 5px; color:#fff;">Sistemdeki Aktif Kullanıcılar</h3>
            <p style="color:#888; font-size:0.85rem; margin-bottom:15px;">Detaylarını görmek istediğiniz öğrencinin satırına tıklayın.</p>
            
            <?php
            try {
                // HATA BURADAYDI: COUNT(up.id) -> COUNT(up.lesson_id) OLARAK DÜZELTİLDİ
                $user_query = $pdo->query("SELECT u.id, u.username, u.email, u.role, u.score, u.profile_pic, COUNT(up.lesson_id) as comp_count, GROUP_CONCAT(l.title, ' | ') as completed_lessons FROM users u LEFT JOIN user_progress up ON u.id = up.user_id AND up.is_completed = 1 LEFT JOIN lessons l ON up.lesson_id = l.id GROUP BY u.id ORDER BY u.role, u.score DESC")->fetchAll();
                
                echo "<table>
                        <thead><tr><th>ID</th><th>Kullanıcı Adı</th><th>Rol</th><th>XP</th><th>Tamamlanan Ders Sayısı</th><th>İşlem</th></tr></thead>
                        <tbody>";
                        
                foreach ($user_query as $u) {
                    $comp_count_html = $u['comp_count'] > 0 ? "<span style='color:#00ff41; font-weight:bold;'>{$u['comp_count']} Görev</span>" : "<span style='color:#666;'>0</span>";
                    $mail_html = $u['email'] ? $u['email'] : "Belirtilmemiş";
                    $lessons_list = $u['completed_lessons'] ? $u['completed_lessons'] : "Henüz görev tamamlamadı.";
                    
                    echo "<tr class='main-row' onclick='toggleDetails({$u['id']})'>
                            <td>{$u['id']}</td>
                            <td><b>{$u['username']}</b></td>
                            <td>{$u['role']}</td>
                            <td><b style='color:var(--accent-color)'>{$u['score']}</b></td>
                            <td>{$comp_count_html}</td>
                            <td onclick='event.stopPropagation();'> 
                                <a href='?edit_user={$u['id']}' class='btn-sm'>Düzenle</a>";
                    
                    if ($u['role'] !== 'admin' || $u['username'] !== $_SESSION['username']) { 
                        echo "<a href='?delete_user={$u['id']}' class='btn-sm btn-danger' onclick='return confirm(\"Kullanıcı ve tüm ilerlemesi kalıcı olarak silinsin mi?\")' style='margin-left:5px;'>Sil</a>";
                    }
                    
                    echo "</td></tr>";
                    
                    echo "<tr id='details-{$u['id']}' class='details-row'>
                            <td colspan='6'>
                                <div class='details-content'>
                                    <div>";
                    if (!empty($u['profile_pic'])) {
                        echo "<img src='uploads/avatars/".htmlspecialchars($u['profile_pic'])."' class='details-avatar'>";
                    } else {
                        echo "<div class='details-avatar-letter'>".strtoupper(substr($u['username'], 0, 1))."</div>";
                    }
                    echo "          </div>
                                    <div>
                                        <p style='margin-bottom:8px;'><span style='color:#888;'>E-Posta Adresi:</span> <span style='color:#fff;'>{$mail_html}</span></p>
                                        <p style='margin-bottom:8px;'><span style='color:#888;'>Sistem ID / Rol:</span> <span style='color:#fff;'>#{$u['id']} - {$u['role']}</span></p>
                                        <p style='margin-bottom:8px;'><span style='color:#888;'>Bitirilen Modüller:</span> <span style='color:var(--accent-color);'>{$lessons_list}</span></p>
                                    </div>
                                </div>
                            </td>
                          </tr>";
                }
                echo "</tbody></table>";
                
            } catch (PDOException $e) {
                echo "<div style='background:#220000; color:#ff4c4c; padding:20px; font-family:monospace; margin-top:20px;'>> VERİTABANI HATASI:<br><br>" . $e->getMessage() . "</div>";
            }
            ?>
        </div>
    </div>

</div>

<script>
function toggleDetails(id) {
    const row = document.getElementById('details-' + id);
    if (row.style.display === 'table-row') {
        row.style.display = 'none';
    } else {
        document.querySelectorAll('.details-row').forEach(el => el.style.display = 'none');
        row.style.display = 'table-row';
    }
}
</script>

</body>
</html>