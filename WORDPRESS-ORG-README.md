# WordPress.org Upload Guide

## Önemli Notlar

1. **Freemius SDK**: `vendor/freemius/` klasörü WordPress.org'a yüklenmemeli. WordPress.org Freemius SDK'yı kendi ekler.

2. **SVN Upload**: WordPress.org'a yüklerken `.wordpress-org-ignore` dosyasındaki dosyalar otomatik olarak hariç tutulur.

3. **Gerekli Dosyalar**:
   - `devforge-admin-cleaner.php` (ana plugin dosyası)
   - `readme.txt` (WordPress.org formatında)
   - `includes/` klasörü (tüm class dosyaları)
   - `assets/` klasörü (CSS, JS, images)
   - `languages/` klasörü (.pot dosyası)
   - `LICENSE` dosyası

4. **Yüklenmemesi Gerekenler**:
   - `vendor/` klasörü (Freemius SDK)
   - `.git/` klasörü
   - `.gitignore`
   - `node_modules/`
   - Development dosyaları

## SVN Upload Adımları

1. WordPress.org SVN repository'yi clone'la:
```bash
svn co https://plugins.svn.wordpress.org/devforge-admin-cleaner
```

2. Plugin dosyalarını `trunk/` klasörüne kopyala (vendor hariç)

3. Commit ve push:
```bash
svn add trunk/*
svn commit -m "Version 2.5.0"
```

4. Tag oluştur:
```bash
svn copy trunk/ tags/2.5.0/
svn commit -m "Tagging version 2.5.0"
```

## Freemius Entegrasyonu

WordPress.org'da Freemius SDK otomatik olarak yüklenir. Plugin'deki `vendor/freemius/` kontrolü WordPress.org'da çalışmayacak, bu normaldir.

## Screenshots

Screenshots klasörüne eklenmeli:
- `assets/screenshot-1.png` (Dashboard Widget Management)
- `assets/screenshot-2.png` (Admin Toolbar Control)
- `assets/screenshot-3.png` (Maintenance Mode)
- vb.

Her screenshot için `readme.txt`'de açıklama olmalı.


