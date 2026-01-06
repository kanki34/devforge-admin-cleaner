# WordPress.org İlk Yükleme Rehberi

## Adım 1: WordPress.org'da Plugin Oluşturma

1. **WordPress.org'a giriş yapın**: https://wordpress.org/
2. **Profil sayfanıza gidin**: https://profiles.wordpress.org/devforge/
3. **"Plugins" sekmesine tıklayın**
4. **"Add New Plugin" butonuna tıklayın**
5. **Plugin bilgilerini doldurun**:
   - **Plugin Name**: Admin Toolkit
   - **Plugin Slug**: `devforge-admin-cleaner` (veya istediğiniz slug)
   - **Short Description**: The ultimate WordPress admin customization toolkit.
   - **Description**: (readme.txt'deki description'ı kullanabilirsiniz)
6. **Submit edin** ve WordPress.org onayını bekleyin

**Not:** Onay genellikle 1-2 gün sürer. Onaylandıktan sonra SVN repository otomatik olarak oluşturulur.

## Adım 2: SVN Repository'yi Clone'la (Onay Sonrası)

Onaylandıktan sonra:

```bash
cd /Users/macos/Desktop/bak
svn co https://plugins.svn.wordpress.org/devforge-admin-cleaner devforge-admin-cleaner-svn
```

## Adım 3: Plugin Dosyalarını Yükle

```bash
cd /Users/macos/Desktop/bak/devforge-admin-cleaner-svn

# Trunk'ı temizle (ilk yükleme için)
rm -rf trunk/*

# Plugin dosyalarını kopyala (vendor hariç)
rsync -av --exclude='vendor' --exclude='.git' --exclude='*.tmp' --exclude='.DS_Store' \
  --exclude='node_modules' --exclude='.env' \
  /Users/macos/Desktop/bak/devforge-admin-cleaner/ trunk/

# SVN Add
svn add trunk/*

# Commit
svn commit -m "Initial release: Version 2.5.0"

# Tag oluştur
svn copy trunk/ tags/2.5.0/
svn commit -m "Tagging version 2.5.0"
```

## Önemli Notlar

1. **Freemius SDK**: `vendor/freemius/` klasörü WordPress.org'a yüklenmemeli. WordPress.org otomatik olarak ekler.

2. **Text Domain**: Tüm metinler `__()` ile sarmalanmış ve text domain `devforge-admin-cleaner` olarak ayarlanmış.

3. **.pot Dosyası**: `languages/devforge-admin-cleaner.pot` mevcut. WordPress.org otomatik güncelleyecek.

4. **Screenshots**: İsterseniz `assets/screenshot-1.png`, `assets/screenshot-2.png` vb. ekleyebilirsiniz.

## Hızlı Başlangıç (Onay Sonrası)

WordPress.org onayı aldıktan sonra, şu komutları çalıştırın:

```bash
cd /Users/macos/Desktop/bak
./upload-to-wordpress-org.sh
```

(Bu script'i oluşturabilirim)

