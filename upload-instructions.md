# WordPress.org Plugin Upload Talimatları

## Durum Kontrolü

SVN repository henüz oluşturulmamış görünüyor. Bu durumda:

### Senaryo 1: Plugin Henüz WordPress.org'da Oluşturulmamış

1. WordPress.org'a giriş yapın: https://wordpress.org/
2. Profil sayfanıza gidin: https://profiles.wordpress.org/devforge/
3. "Plugins" sekmesine tıklayın
4. "Add New Plugin" butonuna tıklayın
5. Plugin bilgilerini doldurun:
   - Plugin Name: Admin Toolkit
   - Plugin Slug: devforge-admin-cleaner (veya istediğiniz slug)
   - Description: The ultimate WordPress admin customization toolkit.
6. Submit edin ve onay bekleyin

**Not:** WordPress.org onayından sonra SVN repository otomatik olarak oluşturulur.

### Senaryo 2: Plugin Zaten Var Ama Farklı Slug

Eğer plugin zaten WordPress.org'da farklı bir slug ile varsa, doğru slug'ı kullanmalıyız.

Örnek slug'lar:
- `admin-toolkit`
- `devforge-admin-cleaner`
- `wac` (WordPress Admin Cleaner)

## SVN Upload Komutları (Plugin Oluşturulduktan Sonra)

```bash
# 1. SVN Repository'yi Clone'la
cd /Users/macos/Desktop/bak
svn co https://plugins.svn.wordpress.org/PLUGIN-SLUG devforge-admin-cleaner-svn

# 2. Plugin dosyalarını trunk'a kopyala (vendor hariç)
cd devforge-admin-cleaner-svn
rm -rf trunk/*
rsync -av --exclude='vendor' --exclude='.git' --exclude='*.tmp' \
  /Users/macos/Desktop/bak/devforge-admin-cleaner/ trunk/

# 3. SVN Add ve Commit
svn add trunk/*
svn commit -m "Initial release: Version 2.5.0"

# 4. Tag Oluştur
svn copy trunk/ tags/2.5.0/
svn commit -m "Tagging version 2.5.0"
```

## Hangi Slug'ı Kullanmalıyız?

Lütfen WordPress.org'da plugin slug'ınızı kontrol edin ve bana bildirin. Böylece doğru SVN repository'yi clone'layabiliriz.

