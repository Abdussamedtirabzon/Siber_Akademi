<?php
// db.php - Veritabanı bağlantısı ve kurulumu
session_start();

$dbFile = __DIR__ . '/academy.db';
$isNewDB = !file_exists($dbFile);

try {
    // SQLite bağlantısı
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Eğer veritabanı yeni oluşturulduysa tabloları kur
    if ($isNewDB) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE,
                password TEXT,
                role TEXT DEFAULT 'user',
                score INTEGER DEFAULT 0
            );
            
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                order_index INTEGER DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS lessons (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER,
                title TEXT,
                content TEXT,
                image_url TEXT,
                order_index INTEGER DEFAULT 0,
                FOREIGN KEY (category_id) REFERENCES categories(id)
            );

            CREATE TABLE IF NOT EXISTS questions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lesson_id INTEGER,
                type TEXT, -- 'quiz' veya 'terminal'
                question_text TEXT,
                correct_answer TEXT,
                options TEXT, -- JSON formatında şıklar
                FOREIGN KEY (lesson_id) REFERENCES lessons(id)
            );

            CREATE TABLE IF NOT EXISTS user_progress (
                user_id INTEGER,
                lesson_id INTEGER,
                is_completed INTEGER DEFAULT 0,
                PRIMARY KEY (user_id, lesson_id)
            );
        ");

        // --- BAŞLANGIÇ VERİLERİNİ (SEED) EKLE ---
        
        // Admin ve normal kullanıcı ekle (Şifreler düz metin olarak eklendi, gerçekte password_hash() kullanılmalıdır)
        $pdo->exec("INSERT INTO users (username, password, role) VALUES ('admin', 'admin123', 'admin')");
        $pdo->exec("INSERT INTO users (username, password, role) VALUES ('hacker', 'hacker123', 'user')");

        // Kategoriler
        $pdo->exec("INSERT INTO categories (id, name, order_index) VALUES (1, 'Siber Güvenlik Temelleri', 1)");
        $pdo->exec("INSERT INTO categories (id, name, order_index) VALUES (2, 'Sızma Testi Araçları', 2)");

        // Dersler
        $pdo->exec("INSERT INTO lessons (id, category_id, title, content, image_url, order_index) 
                    VALUES (1, 1, 'CIA Triad Nedir?', 'CIA Triad; Gizlilik (Confidentiality), Bütünlük (Integrity) ve Erişilebilirlik (Availability) prensiplerinden oluşur. Bilgi güvenliğinin temel taşıdır.', 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c5/CIA_Icon.svg/300px-CIA_Icon.svg.png', 1)");
        
        $pdo->exec("INSERT INTO lessons (id, category_id, title, content, image_url, order_index) 
                    VALUES (2, 2, 'Nmap ile Ağ Keşfi', 'Nmap, ağ üzerindeki cihazları ve açık portları tespit etmek için kullanılan en popüler araçtır. Hedefin servis sürümlerini öğrenmek sızma testinin ilk adımıdır.', 'https://upload.wikimedia.org/wikipedia/commons/1/14/Nmap_logo.svg', 1)");

        // Sorular ve Görevler
        // Quiz Sorusu (Ders 1)
        $options = json_encode(['A) Central Intelligence Agency', 'B) Confidentiality, Integrity, Availability', 'C) Cyber Internet Access']);
        $pdo->prepare("INSERT INTO questions (lesson_id, type, question_text, correct_answer, options) VALUES (?, ?, ?, ?, ?)")
            ->execute([1, 'quiz', 'CIA Triad açılımı hangisidir?', 'B', $options]);

        // Terminal Görevi (Ders 2)
        $pdo->prepare("INSERT INTO questions (lesson_id, type, question_text, correct_answer) VALUES (?, ?, ?, ?)")
            ->execute([2, 'terminal', 'Hedef 10.0.0.1 makinesindeki servis versiyonlarını tespit etmek için Nmap komutunu yazın.', 'nmap -sV 10.0.0.1']);
    }
} catch (PDOException $e) {
    die("Veritabanı Hatası: " . $e->getMessage());
}
?>