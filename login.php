<?php
// login.php - Kullanıcı Giriş, Kayıt ve Şifre Sıfırlama Sistemi (Canlı ve Güvenli Sürüm)
session_start();
require 'db.php';

$error = "";
$success = "";

// Eğer URL'de bir şifre sıfırlama token'ı varsa reset formunu göstereceğiz
$reset_token_from_url = $_GET['token'] ?? '';
$show_reset_form = false;

try {
    if ($reset_token_from_url) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ?");
        $stmt->execute([$reset_token_from_url]);
        if ($stmt->fetch()) {
            $show_reset_form = true;
        } else {
            $error = "Geçersiz veya süresi dolmuş bir sıfırlama bağlantısı kullandınız.";
        }
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $action = $_POST['action'] ?? '';
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($action == "login") {
            // --- GİRİŞ İŞLEMİ ---
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) { 
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'] ?? 'user';
                
                if (isset($user['role']) && $user['role'] == 'admin') {
                    header("Location: admin.php");
                } else {
                    header("Location: index.php");
                }
                exit;
            } else {
                $error = "Erişim Reddedildi. Yanlış kullanıcı adı veya şifre.";
            }
        } 
        elseif ($action == "register") {
            // --- KAYIT İŞLEMİ ---
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            if ($check->fetch()) {
                $error = "Bu kullanıcı adı veya e-posta zaten sistemde kayıtlı!";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
                $stmt->execute([$username, $email, $hashed_password]);
                $success = "Kayıt başarılı! Şimdi sisteme giriş yapabilirsiniz.";
            }
        }
        /*----CANLIYA ALINACAKSA-----
        elseif ($action == "forgot") {
            // --- ŞİFREMİ UNUTTUM İŞLEMİ ---
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(16));
                $update = $pdo->prepare("UPDATE users SET reset_token = ? WHERE email = ?");
                $update->execute([$token, $email]);
                
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                $site_url = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
                $reset_link = $site_url . "/login.php?token=" . $token;

                $to = $email;
                $subject = "Siber Akademi - Şifre Sıfırlama Talebi";
                $message = "Merhaba,\n\nSistemimize ait şifrenizi sıfırlama talebinde bulundunuz. Yeni şifrenizi belirlemek için aşağıdaki bağlantıya tıklayın:\n\n" . $reset_link . "\n\nEğer bu işlemi siz yapmadıysanız, hesabınız güvendedir ve bu e-postayı görmezden gelebilirsiniz.";
                $headers = "From: noreply@siberakademi.com\r\n" .
                           "Reply-To: noreply@siberakademi.com\r\n" .
                           "X-Mailer: PHP/" . phpversion();

                if (mail($to, $subject, $message, $headers)) {
                    $success = "Eğer sistemimizde bu e-posta adresine ait bir kayıt varsa, sıfırlama bağlantısı gönderilmiştir. Lütfen gelen kutunuzu kontrol edin.";
                } else {
                    $error = "Sistem geçici olarak e-posta gönderemiyor. Lütfen daha sonra tekrar deneyin.";
                    error_log("Mail gönderim başarısız: E-posta sunucusu yapılandırılmamış olabilir.");
                }
            } else {
                $success = "Eğer sistemimizde bu e-posta adresine ait bir kayıt varsa, sıfırlama bağlantısı gönderilmiştir. Lütfen gelen kutunuzu kontrol edin.";
            }
        }
        */
        elseif ($action == "forgot") {
            // --- ŞİFREMİ UNUTTUM İŞLEMİ (ÖDEV / TEST SUNUM MODU) ---
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(16));
                $update = $pdo->prepare("UPDATE users SET reset_token = ? WHERE email = ?");
                $update->execute([$token, $email]);
                
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                $site_url = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
                $reset_link = $site_url . "/login.php?token=" . $token;

                // Ödev sunumu için linki ekrana yeşil mesaj olarak basıyoruz
                $success = "Bağlantı oluşturuldu! (Normalde bu maile gider) <br><br> <a href='$reset_link' style='color:#fff; text-decoration:underline;'>ŞİFREYİ SIFIRLAMAK İÇİN TIKLA</a>";
            } else {
                // Güvenlik için yine aynı mesajı veriyoruz
                $success = "Eğer sistemimizde bu e-posta adresine ait bir kayıt varsa, sıfırlama bağlantısı gönderilmiştir.";
            }
        }
        elseif ($action == "reset") {
            // --- YENİ ŞİFREYİ BELİRLEME İŞLEMİ ---
            $token = $_POST['token'];
            $new_password = $_POST['new_password'];
            
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL WHERE reset_token = ?");
            $stmt->execute([$hashed_password, $token]);
            
            $success = "Şifreniz başarıyla güncellendi! Şimdi yeni şifrenizle giriş yapabilirsiniz.";
            $show_reset_form = false;
        }
    }
} catch (PDOException $e) {
    // -------------------------------------------------------------
    // GÜVENLİK GÜNCELLEMESİ: Hata Gizleme (Information Disclosure Koruması)
    // -------------------------------------------------------------
    error_log("Siber Akademi DB Hatası: " . $e->getMessage());
    
    // Kullanıcıya ise hiçbir detay vermeyen, sıradan bir mesaj gösteriyoruz.
    $error = "Sistemde geçici bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.";
   // $error = "HATA DETAYI: " . $e->getMessage(); // Bu satır sadece test ve eğitim amaçlıdır. Canlıda yukarıdaki yorum satırındaki mesaj kullanılmalıdır.
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Sistem Erişimi | Siber Akademi</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .toggle-btns { display: flex; margin-bottom: 20px; border-bottom: 2px solid #333; }
        .toggle-btns button { flex: 1; background: transparent; color: #aaa; border-radius: 0; box-shadow: none; padding: 10px; border:none; cursor: pointer; }
        .toggle-btns button.active { color: var(--accent-color); border-bottom: 2px solid var(--accent-color); font-weight: bold; }
        .toggle-btns button:hover { background: #111; }
        .form-container { display: none; }
        .form-container.active { display: block; }
        .link-btn { background: none; border: none; color: #888; font-size: 0.8rem; cursor: pointer; text-decoration: underline; margin-top: 10px; padding: 0; }
        .link-btn:hover { color: var(--accent-color); box-shadow: none; }
        /* Ana Sayfa Butonu Stili */
        .home-btn { display: inline-block; margin-top: 25px; padding: 8px 15px; color: #888; text-decoration: none; font-size: 0.85rem; border: 1px solid #333; border-radius: 4px; transition: 0.3s; }
        .home-btn:hover { color: var(--accent-color); border-color: var(--accent-color); background: #111; }
    </style>
</head>
<body style="justify-content: center; align-items: center;">
    <div class="lesson-card" style="width: 400px; text-align: center;">
        <h2 style="color: var(--accent-color); margin-bottom: 20px;">>_ SİSTEM ERİŞİMİ</h2>
        
        <?php if($error): ?>
            <p style='color: #ff4c4c; background: #331111; padding: 10px; border: 1px dashed #ff4c4c; margin-bottom: 15px; font-size:0.9rem;'><?= $error ?></p>
        <?php endif; ?>
        <?php if($success): ?>
            <p style='color: var(--accent-color); background: #113311; padding: 10px; border: 1px dashed var(--accent-color); margin-bottom: 15px; font-size:0.9rem;'><?= $success ?></p>
        <?php endif; ?>

        <?php if($show_reset_form): ?>
            <p style="color:#aaa; font-size: 0.9rem;">Lütfen yeni şifrenizi belirleyin.</p>
            <form method="POST">
                <input type="hidden" name="action" value="reset">
                <input type="hidden" name="token" value="<?= htmlspecialchars($reset_token_from_url) ?>">
                <input type="password" name="new_password" placeholder="Yeni Şifreniz" required>
                <button type="submit" style="width: 100%; margin-top: 15px;">Şifreyi Güncelle</button>
            </form>
            <button class="link-btn" onclick="window.location.href='login.php'">İptal Et ve Girişe Dön</button>

        <?php else: ?>
            <div class="toggle-btns">
                <button id="btn-login" class="active" onclick="showForm('login')">GİRİŞ YAP</button>
                <button id="btn-register" onclick="showForm('register')">KAYIT OL</button>
            </div>

            <div id="form-login" class="form-container active">
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <input type="text" name="username" placeholder="Kullanıcı Adı" required>
                    <input type="password" name="password" placeholder="Şifre" required>
                    <button type="submit" style="width: 100%; margin-top: 15px;">Bağlan</button>
                </form>
                <button class="link-btn" onclick="showForm('forgot')">Şifremi Unuttum</button>
            </div>

            <div id="form-register" class="form-container">
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    <input type="text" name="username" placeholder="Kullanıcı Adı" required>
                    <input type="email" name="email" placeholder="E-posta Adresi" required>
                    <input type="password" name="password" placeholder="Şifre Belirle" required>
                    <button type="submit" style="width: 100%; margin-top: 15px;">Ağa Katıl</button>
                </form>
            </div>

            <div id="form-forgot" class="form-container">
                <p style="color:#aaa; font-size: 0.9rem;">Kayıtlı e-posta adresinizi girin. Ağa sıfırlama sinyali göndereceğiz.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="forgot">
                    <input type="email" name="email" placeholder="Kayıtlı E-posta Adresi" required>
                    <button type="submit" style="width: 100%; margin-top: 15px;">Sinyal Gönder</button>
                </form>
                <button class="link-btn" onclick="showForm('login')">< Geri Dön</button>
            </div>
        <?php endif; ?>

        <p style="margin-top: 20px; font-size: 0.8rem; color:#666;">Yetkisiz erişim denemeleri loglanmaktadır.</p>
        <a href="index.php" class="home-btn">&lt; Ana Sayfaya Dön</a>
    </div>

    <script>
        function showForm(type) {
            document.querySelectorAll('.form-container').forEach(el => el.classList.remove('active'));
            document.getElementById('btn-login').classList.remove('active');
            document.getElementById('btn-register').classList.remove('active');

            if (type === 'login') {
                document.getElementById('form-login').classList.add('active');
                document.getElementById('btn-login').classList.add('active');
            } else if (type === 'register') {
                document.getElementById('form-register').classList.add('active');
                document.getElementById('btn-register').classList.add('active');
            } else if (type === 'forgot') {
                document.getElementById('form-forgot').classList.add('active');
            }
        }
    </script>
</body>
</html>