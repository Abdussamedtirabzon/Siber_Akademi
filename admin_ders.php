<?php
// admin_ders.php - Kategori, Ders ve Soru/Görev Yönetimi Paneli
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("<div style='background:black; color:red; font-family:monospace; padding:20px;'>> ERİŞİM REDDEDİLDİ. YETKİ YOK.</div>");
}

$upload_dir = __DIR__ . '/uploads';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

// =========================================
// 1. SİLME İŞLEMLERİ
// =========================================
if (isset($_GET['delete_cat'])) {
    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$_GET['delete_cat']]);
    header("Location: admin_ders.php#categories"); exit;
}
if (isset($_GET['delete_lesson'])) {
    $pdo->prepare("DELETE FROM lessons WHERE id = ?")->execute([$_GET['delete_lesson']]);
    $pdo->prepare("DELETE FROM questions WHERE lesson_id = ?")->execute([$_GET['delete_lesson']]); 
    header("Location: admin_ders.php#lessons"); exit;
}
if (isset($_GET['delete_question'])) {
    $pdo->prepare("DELETE FROM questions WHERE id = ?")->execute([$_GET['delete_question']]);
    header("Location: admin_ders.php#questions"); exit;
}

// =========================================
// 2. EKLEME VE GÜNCELLEME İŞLEMLERİ
// =========================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- KATEGORİ İŞLEMLERİ ---
    if (isset($_POST['save_category'])) {
        $name = $_POST['cat_name'];
        $order = $_POST['cat_order'];
        if (!empty($_POST['cat_id'])) { 
            $pdo->prepare("UPDATE categories SET name=?, order_index=? WHERE id=?")->execute([$name, $order, $_POST['cat_id']]);
        } else { 
            $pdo->prepare("INSERT INTO categories (name, order_index) VALUES (?, ?)")->execute([$name, $order]);
        }
        header("Location: admin_ders.php#categories"); exit;
    }

    // --- DERS İŞLEMLERİ ---
    if (isset($_POST['save_lesson'])) {
        $cat_id = $_POST['category_id'];
        $title = $_POST['title'];
        $content = $_POST['content'];
        $order = $_POST['lesson_order'];
        $final_image_url = $_POST['image_url'];

        // YEREL DOSYA YÜKLENMİŞSE
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
            $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
            $filename = 'img_' . time() . '_' . uniqid() . '.' . $ext;
            $dest = 'uploads/' . $filename;
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], __DIR__ . '/' . $dest)) {
                $final_image_url = $dest;
            }
        }
        
        if (!empty($_POST['lesson_id'])) { 
            $pdo->prepare("UPDATE lessons SET category_id=?, title=?, content=?, image_url=?, order_index=? WHERE id=?")
                ->execute([$cat_id, $title, $content, $final_image_url, $order, $_POST['lesson_id']]);
        } else { 
            $pdo->prepare("INSERT INTO lessons (category_id, title, content, image_url, order_index) VALUES (?, ?, ?, ?, ?)")
                ->execute([$cat_id, $title, $content, $final_image_url, $order]);
        }
        header("Location: admin_ders.php#lessons"); exit;
    }

    // --- SORU / GÖREV İŞLEMLERİ ---
    if (isset($_POST['save_question'])) {
        $lesson_id = $_POST['lesson_id'];
        $type = $_POST['q_type'];
        $q_text = $_POST['q_text'];
        $correct = $_POST['correct_ans'];
        
        $options = null;
        if ($type == 'quiz') {
            $opts_array = explode(',', $_POST['options_csv']);
            $options = json_encode(array_map('trim', $opts_array));
        }

        if (!empty($_POST['question_id'])) {
            $pdo->prepare("UPDATE questions SET lesson_id=?, type=?, question_text=?, correct_answer=?, options=? WHERE id=?")
                ->execute([$lesson_id, $type, $q_text, $correct, $options, $_POST['question_id']]);
        } else { 
            $pdo->prepare("INSERT INTO questions (lesson_id, type, question_text, correct_answer, options) VALUES (?, ?, ?, ?, ?)")
                ->execute([$lesson_id, $type, $q_text, $correct, $options]);
        }
        header("Location: admin_ders.php#questions"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Müfredat Yönetimi | Siber Akademi</title>
    <link rel="stylesheet" href="style.css">
    <style>
        html { scroll-behavior: smooth; }
        .admin-nav { background: #111; padding: 15px 20px; border-bottom: 2px solid var(--accent-color); display: flex; gap: 20px; position: sticky; top: 0; z-index: 100;}
        .admin-nav a { font-weight: bold; padding: 8px 15px; border-radius: 4px; transition: 0.3s; text-decoration: none; color: #aaa; border: 1px solid transparent; }
        .admin-nav a:hover, .admin-nav a.active { color: var(--accent-color); border-color: var(--accent-color); background: rgba(0,255,0,0.05); }
        
        .admin-section { padding: 40px 20px; border-bottom: 1px dashed #333; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 0.9rem; }
        th, td { border: 1px solid #333; padding: 10px; text-align: left; }
        th { background-color: #222; color: var(--accent-color); }
        tr:nth-child(even) { background-color: #1a1f26; }
        tr:hover { background-color: #2a323d; }
        
        .btn-sm { padding: 5px 10px; font-size: 0.8rem; border-radius: 3px; background: #333; color: #fff; text-decoration: none; border: 1px solid #555; }
        .btn-sm:hover { background: var(--accent-color); color: #000; }
        .btn-danger { background: #333; color: #ff4c4c; border-color: #ff4c4c; }
        .btn-danger:hover { background: #ff4c4c; color: #fff; }
        
        .edit-box { border: 1px solid var(--accent-color); background: #0a0a0a; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,255,0,0.1); }
        .edit-title { color: var(--accent-color); margin-bottom: 15px; }
        .file-upload-box { border: 1px dashed #555; background: #111; padding: 15px; border-radius: 4px; margin: 10px 0; }
        input, select, textarea { width: 100%; margin-bottom: 10px; }
    </style>
</head>
<body>

<header class="header">
    <h1>>_ ROOT / MÜFREDAT YÖNETİMİ</h1>
    <div><a href="index.php" style="color:#aaa;">Vitrine Dön</a> | <a href="logout.php" style="color:#ff4c4c;">Çıkış</a></div>
</header>

<div class="admin-nav">
    <a href="admin.php">>_ Genel Bakış</a>
    <a href="admin_kull.php">>_ Kullanıcı Yönetimi</a>
    <a href="admin_ders.php" class="active">>_ Müfredat & Dersler</a>
    <span style="border-left: 1px solid #333; margin: 0 10px;"></span>
    <a href="#categories" style="font-size:0.8rem;">↓ Kategoriler</a>
    <a href="#lessons" style="font-size:0.8rem;">↓ Dersler</a>
    <a href="#questions" style="font-size:0.8rem;">↓ Görevler</a>
</div>

<div id="categories" class="admin-section">
    <h2 class="edit-title">> Kategori (Modül) Yönetimi</h2>
    <?php
    $edit_cat = null;
    if (isset($_GET['edit_cat'])) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id=?");
        $stmt->execute([$_GET['edit_cat']]);
        $edit_cat = $stmt->fetch();
    }
    ?>
    <div class="<?= $edit_cat ? 'edit-box' : '' ?>" style="<?= !$edit_cat ? 'background:#111; padding:15px; border-radius:5px; border:1px solid #333; margin-bottom:20px;' : '' ?>">
        <h3><?= $edit_cat ? '[~] Kategoriyi Düzenle' : '[+] Yeni Kategori Ekle' ?></h3>
        <form method="POST" style="display:flex; gap:10px; margin-top:10px;">
            <input type="hidden" name="save_category" value="1">
            <input type="hidden" name="cat_id" value="<?= $edit_cat['id'] ?? '' ?>">
            <input type="text" name="cat_name" placeholder="Kategori Adı" value="<?= $edit_cat['name'] ?? '' ?>" required>
            <input type="number" name="cat_order" placeholder="Sıra Numarası (Menüdeki Sırası)" value="<?= $edit_cat['order_index'] ?? '' ?>" required>
            <button type="submit" style="white-space:nowrap; padding: 10px 20px;"><?= $edit_cat ? 'Güncelle' : 'Ekle' ?></button>
            <?php if($edit_cat) echo "<a href='admin_ders.php#categories' class='btn-sm' style='padding:10px 20px; display:flex; align-items:center;'>İptal</a>"; ?>
        </form>
    </div>
    <table>
        <tr><th>ID</th><th>Sıra</th><th>Kategori Adı</th><th>İşlemler</th></tr>
        <?php
        $cats = $pdo->query("SELECT * FROM categories ORDER BY order_index")->fetchAll();
        foreach ($cats as $c) {
            echo "<tr><td>{$c['id']}</td><td>{$c['order_index']}</td><td><b style='color:#fff;'>{$c['name']}</b></td><td>
                    <a href='?edit_cat={$c['id']}#categories' class='btn-sm'>Düzenle</a>
                    <a href='?delete_cat={$c['id']}' class='btn-sm btn-danger' onclick='return confirm(\"Silinirse içindeki dersler boşa çıkar. Devam edilsin mi?\")'>Sil</a>
                  </td></tr>";
        }
        ?>
    </table>
</div>

<div id="lessons" class="admin-section">
    <h2 class="edit-title">> Ders İçerik Yönetimi</h2>
    <?php
    $edit_lesson = null;
    if (isset($_GET['edit_lesson'])) {
        $stmt = $pdo->prepare("SELECT * FROM lessons WHERE id=?");
        $stmt->execute([$_GET['edit_lesson']]);
        $edit_lesson = $stmt->fetch();
    }
    ?>
    <div class="edit-box">
        <h3><?= $edit_lesson ? '[~] Dersi Düzenle' : '[+] Yeni Ders Oluştur' ?></h3>
        
        <form method="POST" enctype="multipart/form-data" style="margin-top:15px;">
            <input type="hidden" name="save_lesson" value="1">
            <input type="hidden" name="lesson_id" value="<?= $edit_lesson['id'] ?? '' ?>">
            
            <div style="display:flex; gap:10px;">
                <select name="category_id" required>
                    <option value="">-- Hangi Kategoriye Eklenecek? --</option>
                    <?php
                    foreach ($cats as $c) {
                        $selected = ($edit_lesson && $edit_lesson['category_id'] == $c['id']) ? "selected" : "";
                        echo "<option value='{$c['id']}' $selected>{$c['name']}</option>";
                    }
                    ?>
                </select>
                <input type="number" name="lesson_order" placeholder="Sıra Numarası (1, 2, 3..)" value="<?= $edit_lesson['order_index'] ?? '' ?>" required>
            </div>
            
            <input type="text" name="title" placeholder="Ders Başlığı (Örn: Nmap Temelleri)" value="<?= $edit_lesson['title'] ?? '' ?>" required>
            <textarea name="content" rows="8" placeholder="Ders İçeriği (HTML formatı kullanılabilir: <p>, <b>, <code> vs.)" required><?= $edit_lesson['content'] ?? '' ?></textarea>
            
            <div class="file-upload-box">
                <label style="color:var(--accent-color); font-weight:bold;">> Ders Görseli Ekle</label><br>
                <label style="color:#aaa; font-size:0.9rem;">1. Seçenek: Bilgisayardan Yükle</label>
                <input type="file" name="image_file" accept="image/*" style="background:transparent; border:none; margin-bottom:15px;">
                
                <label style="color:#aaa; font-size:0.9rem;">2. Seçenek: İnternetten Görsel URL'si Yapıştır</label>
                <input type="text" name="image_url" placeholder="https://..." value="<?= $edit_lesson['image_url'] ?? '' ?>">
                
                <?php if($edit_lesson && $edit_lesson['image_url']): ?>
                    <div style="margin-top:5px; font-size:0.85rem; color:#00ff41;">Mevcut Görsel: <?= htmlspecialchars($edit_lesson['image_url']) ?></div>
                <?php endif; ?>
            </div>

            <button type="submit"><?= $edit_lesson ? 'Değişiklikleri Kaydet' : 'Dersi Yayınla' ?></button>
            <?php if($edit_lesson) echo "<a href='admin_ders.php#lessons' class='btn-sm' style='margin-left:10px; padding:10px 20px;'>İptal Et</a>"; ?>
        </form>
    </div>

    <table>
        <tr><th>ID</th><th>Bağlı Olduğu Kategori</th><th>Sıra</th><th>Ders Başlığı</th><th>Görsel</th><th>İşlemler</th></tr>
        <?php
        $lessons = $pdo->query("SELECT l.*, c.name as cat_name FROM lessons l JOIN categories c ON l.category_id = c.id ORDER BY c.order_index, l.order_index")->fetchAll();
        foreach ($lessons as $l) {
            $img_status = $l['image_url'] ? "<span style='color:#00ff41;'>[IMG]</span>" : "<span style='color:#666;'>Yok</span>";
            echo "<tr><td>{$l['id']}</td><td><span style='color:#aaa;'>{$l['cat_name']}</span></td><td>{$l['order_index']}</td><td><b style='color:#fff;'>{$l['title']}</b></td><td>{$img_status}</td><td>
                    <a href='?edit_lesson={$l['id']}#lessons' class='btn-sm'>Düzenle</a>
                    <a href='?delete_lesson={$l['id']}' class='btn-sm btn-danger' onclick='return confirm(\"Ders ve derse bağlı sorular silinecek. Emin misin?\")'>Sil</a>
                  </td></tr>";
        }
        ?>
    </table>
</div>

<div id="questions" class="admin-section" style="border-bottom:none;">
    <h2 class="edit-title">> Görev ve Soru (Laboratuvar) Yönetimi</h2>
    <p style="color:#888; font-size:0.9rem; margin-bottom:15px;">Derslerin sonuna öğrencilerin pratik yapması için görevler ekleyin.</p>
    
    <?php
    $edit_q = null;
    if (isset($_GET['edit_question'])) {
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE id=?");
        $stmt->execute([$_GET['edit_question']]);
        $edit_q = $stmt->fetch();
    }
    ?>
    <div class="<?= $edit_q ? 'edit-box' : '' ?>" style="<?= !$edit_q ? 'background:#111; padding:20px; border-radius:5px; border:1px solid #333; margin-bottom:20px;' : '' ?>">
        <h3><?= $edit_q ? '[~] Görevi Düzenle' : '[+] Yeni Görev Ata' ?></h3>
        <form method="POST" style="margin-top:15px;">
            <input type="hidden" name="save_question" value="1">
            <input type="hidden" name="question_id" value="<?= $edit_q['id'] ?? '' ?>">
            
            <div style="display:flex; gap:10px;">
                <select name="lesson_id" required>
                    <option value="">-- Hangi Derse Eklenecek? --</option>
                    <?php
                    $all_lessons = $pdo->query("SELECT * FROM lessons ORDER BY title")->fetchAll();
                    foreach ($all_lessons as $l) {
                        $selected = ($edit_q && $edit_q['lesson_id'] == $l['id']) ? "selected" : "";
                        echo "<option value='{$l['id']}' $selected>{$l['title']}</option>";
                    }
                    ?>
                </select>
                <select name="q_type" id="q_type" onchange="toggleOptions()" required>
                    <option value="terminal" <?= ($edit_q && $edit_q['type']=='terminal')?'selected':'' ?>>Kali Terminal Görevi</option>
                    <option value="quiz" <?= ($edit_q && $edit_q['type']=='quiz')?'selected':'' ?>>Çoktan Seçmeli Test (Quiz)</option>
                </select>
            </div>
            
            <input type="text" name="q_text" placeholder="Görevi veya Soruyu Buraya Yazın (Örn: Hedef sistemin portlarını taramak için komut giriniz)" value="<?= $edit_q['question_text'] ?? '' ?>" required>
            <input type="text" name="correct_ans" placeholder="Örn: ls -la | ls -al (Alternatifleri | ile ayırın)" value="<?= htmlspecialchars($edit_q['correct_answer'] ?? '') ?>" required>
            <p style="font-size: 0.8rem; color: #888; margin-top: -5px; margin-bottom: 15px;">
             * <b>Terminal Görevleri:</b> Birden fazla doğru yazım varsa aralarına <b>|</b> (dik çizgi) koyun. Sistem büyük/küçük harf ve boşluklara duyarsızdır.<br>
             * <b>Quiz Görevleri:</b> Sadece doğru şıkkın harfini (A, B, C vb.) yazın.
            </p>
            <?php
            $opts_csv = "";
            if ($edit_q && $edit_q['type'] == 'quiz' && $edit_q['options']) {
                $arr = json_decode($edit_q['options']);
                $opts_csv = implode(", ", $arr);
            }
            ?>
            <div id="options_div" style="<?= ($edit_q && $edit_q['type']=='quiz') ? 'block' : 'display:none;' ?> padding:10px; background:#050505; border:1px dashed #555; margin-bottom:10px;">
                <label style="color:var(--accent-color); font-size:0.9rem;">> Quiz Şıkları (Sadece Quiz seçili ise doldurun)</label>
                <input type="text" name="options_csv" id="opt_input" placeholder="Şıkları VİRGÜL ile ayırarak yazın (Örn: A) Nmap, B) SQLmap, C) Wireshark)" value="<?= $opts_csv ?>" style="margin-top:5px; margin-bottom:0;">
            </div>
            
            <button type="submit"><?= $edit_q ? 'Görevi Güncelle' : 'Görevi Ekle' ?></button>
            <?php if($edit_q) echo "<a href='admin_ders.php#questions' class='btn-sm' style='margin-left:10px; padding:10px 20px;'>İptal</a>"; ?>
        </form>
    </div>

    <table>
        <tr><th>Bağlı Olduğu Ders</th><th>Görev Tipi</th><th>Görev Metni</th><th>Beklenen Cevap</th><th>İşlemler</th></tr>
        <?php
        $questions = $pdo->query("SELECT q.*, l.title as lesson_title FROM questions q JOIN lessons l ON q.lesson_id = l.id ORDER BY l.title")->fetchAll();
        foreach ($questions as $q) {
            $type_html = $q['type'] == 'terminal' ? "<span style='color:#e3a010; font-weight:bold;'>Terminal</span>" : "<span style='color:#00ff41; font-weight:bold;'>Quiz</span>";
            echo "<tr>
                    <td><span style='color:#aaa;'>{$q['lesson_title']}</span></td>
                    <td>{$type_html}</td>
                    <td>{$q['question_text']}</td>
                    <td style='font-family:monospace; color:var(--accent-color);'>{$q['correct_answer']}</td>
                    <td>
                        <a href='?edit_question={$q['id']}#questions' class='btn-sm'>Düzenle</a>
                        <a href='?delete_question={$q['id']}' class='btn-sm btn-danger' onclick='return confirm(\"Silinsin mi?\")'>Sil</a>
                    </td>
                  </tr>";
        }
        ?>
    </table>
</div>

<script>
// Quiz seçildiğinde şık girme kutusunu gösteren Javascript
function toggleOptions() {
    var type = document.getElementById('q_type').value;
    var div = document.getElementById('options_div');
    var input = document.getElementById('opt_input');
    if (type === 'quiz') {
        div.style.display = 'block';
        input.required = true;
    } else {
        div.style.display = 'none';
        input.required = false;
        input.value = ""; // Temizle
    }
}
// Sayfa yüklendiğinde mevcut duruma göre çalıştır
toggleOptions();
</script>
</body>
</html>