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


---

### 🚀 ADIM 3: GitHub'a Gönder (Push)
Burada şifre kaydetme (helper) komutunu da kullanacağız ki bir daha sormasın.

1.  **Şifre Hatırlatıcıyı Aç:** (Bunu bir kez yapman yeterli)
    ```bash
    git config --global credential.helper 'cache --timeout=31536000'
    ```
2.  **Değişiklikleri Sahneye Al:**
    ```bash
    git add .
    ```
3.  **Mühürle (Commit):**
    ```bash
    git commit -m "Docs: Professional README and DB schema added"
    ```
4.  **Gökyüzüne Gönder:**
    ```bash
    git push -u origin main
    ```

---

### 💡 Özet: Ne Yapmış Oldun?
Ankasoft mülakatında biri projeni açtığında şunu görecek:
1.  **README:** "Aha, bu çocuk işi biliyor, nasıl çalıştıracağımı yazmış."
2.  **database_setup.sql:** "Veritabanını göndermemiş ama şemasını koymuş, tam bir profesyonel."
3.  **.gitignore:** "Sanal dosyalarla repoyu kirletmemiş."

**Şimdi bu adımları sırayla yap. `git push` dediğinde Token'ı yapıştır ve bitince GitHub sayfanı kontrol et. Sayfan az önceki YouTube projesi gibi tertemiz göründüyse haber ver!**
