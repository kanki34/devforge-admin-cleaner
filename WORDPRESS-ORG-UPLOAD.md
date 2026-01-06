# WordPress.org Plugin Upload Guide

## Önkoşullar

1. WordPress.org hesabınız olmalı
2. Plugin slug: `devforge-admin-cleaner`
3. SVN yüklü (✅ Tamamlandı)

## Upload Adımları

### 1. SVN Repository'yi Clone'la

```bash
cd /Users/macos/Desktop/bak
svn co https://plugins.svn.wordpress.org/devforge-admin-cleaner devforge-admin-cleaner-svn
```

**Not:** İlk kez yüklüyorsanız, WordPress.org'dan SVN erişim bilgilerinizi almanız gerekecek.

### 2. Plugin Dosyalarını Hazırla

`.wordpress-org-ignore` dosyasındaki dosyalar otomatik olarak hariç tutulacak:
- `vendor/freemius/` (WordPress.org kendi ekler)
- `.git/` klasörü
- Development dosyaları

### 3. Dosyaları Trunk'a Kopyala

```bash
cd /Users/macos/Desktop/bak/devforge-admin-cleaner-svn
# Mevcut trunk'ı temizle (ilk kez yüklüyorsanız)
rm -rf trunk/*

# Plugin dosyalarını kopyala (vendor hariç)
rsync -av --exclude='vendor' --exclude='.git' \
  /Users/macos/Desktop/bak/devforge-admin-cleaner/ trunk/
```

### 4. SVN Add ve Commit

```bash
cd /Users/macos/Desktop/bak/devforge-admin-cleaner-svn
svn add trunk/*
svn commit -m "Initial release: Version 2.5.0"
```

### 5. Tag Oluştur

```bash
svn copy trunk/ tags/2.5.0/
svn commit -m "Tagging version 2.5.0"
```

## Önemli Notlar

1. **Freemius SDK**: `vendor/freemius/` klasörü WordPress.org'a yüklenmemeli. WordPress.org Freemius SDK'yı otomatik olarak ekler.

2. **Text Domain**: Tüm metinler `__()` ile sarmalanmış ve text domain `devforge-admin-cleaner` olarak ayarlanmış.

3. **.pot Dosyası**: `languages/devforge-admin-cleaner.pot` dosyası mevcut. WordPress.org otomatik olarak güncelleyecek.

4. **Screenshots**: Screenshots eklemek isterseniz, `assets/screenshot-1.png`, `assets/screenshot-2.png` vb. olarak ekleyin ve `readme.txt`'de açıklayın.

## İlk Yükleme Sonrası

1. WordPress.org plugin sayfasını kontrol edin
2. Çeviriler için `translate.wordpress.org` sayfasını kontrol edin
3. Plugin'in aktif olduğundan emin olun

## Güncelleme Yaparken

1. Yeni versiyon için `readme.txt`'deki `Stable tag` değerini güncelleyin
2. `devforge-admin-cleaner.php` dosyasındaki versiyonu güncelleyin
3. Trunk'a dosyaları kopyalayın
4. Yeni tag oluşturun: `svn copy trunk/ tags/2.5.1/`

