# 🛡️ Siber Akademi - Web Eğitim Platformu

Bu platform, siber güvenlik eğitimlerini interaktif sunan, PHP tabanlı bir eğitim portalıdır.

## 🚀 Yerel Çalıştırma (Fedora/Linux)
Terminalden projenin içine girin ve PHP'nin dahili sunucusunu başlatın:
```bash
php -S localhost:8979

Ardından tarayıcıdan http://localhost:8979 adresine gidin.
🗄️ Veritabanı (SQLite3)

Güvenlik ve sürüm kontrolü gereği .db dosyası yüklenmemiştir. Veritabanını kurmak için:

    database_setup.sql dosyasındaki şemayı kullanın.

    academy.db dosyasını oluşturun.

🛠️ Teknik Özellikler

    Backend: PHP 8.x

    Database: SQLite3

    Security: Role-based access control (RBAC), Prepared Statements.

