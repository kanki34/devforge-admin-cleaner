# WordPress.org Plugin Submit Form Bilgileri

## ZIP Dosyası
✅ **Hazır**: `/Users/macos/Desktop/bak/devforge-admin-cleaner-wordpress-org.zip` (81KB)

## Form Bilgileri

### Plugin Name
**Admin Toolkit**

**Not:** Plugin Name, plugin header'ında (`devforge-admin-cleaner.php`) tanımlı. WordPress.org bu ismi kullanarak URL oluşturacak:
- URL: `https://wordpress.org/plugins/admin-toolkit`
- Slug: `admin-toolkit`

**⚠️ ÖNEMLİ:** Eğer farklı bir slug istiyorsanız, plugin header'ındaki "Plugin Name" değerini değiştirmeniz gerekir. Şu anki değer: `Admin Toolkit`

### Short Description
```
The ultimate WordPress admin customization toolkit.
```

### Description
`readme.txt` dosyasındaki description'ı kullanabilirsiniz veya şunu kullanabilirsiniz:

```
Admin Toolkit is a comprehensive WordPress admin customization and optimization plugin. Clean up your dashboard, hide unwanted widgets, optimize performance, customize admin toolbar, enable maintenance mode, and so much more - all from one powerful interface.

Perfect for developers, agencies, and site owners who want a cleaner, faster, and more professional WordPress admin experience.
```

### Checkbox'lar
✅ Tüm checkbox'ları işaretleyin:
- [x] I have read the Frequently Asked Questions.
- [x] This plugin complies with all of the Plugin Developer Guidelines.
- [x] I have permission to upload this plugin to WordPress.org for others to use and share.
- [x] This plugin, all included libraries, and any other included assets are licenced as GPL or are under a GPL compatible license.
- [x] I confirm that the plugin has been tested with the Plugin Check plugin, and all indicated issues resolved (apart from what I believe to be false-positives).

## Plugin Check Testi

WordPress.org göndermeden önce Plugin Check plugin'i ile test edin:

1. WordPress admin panelinde Plugin Check plugin'ini yükleyin
2. Plugin'i test edin
3. Tüm uyarıları düzeltin (false-positive'ler hariç)

## Önemli Notlar

1. **Plugin Name = URL Slug**: Plugin header'ındaki "Plugin Name" değeri URL slug'ını belirler. Eğer `Admin Toolkit` ise, slug `admin-toolkit` olur.

2. **Freemius SDK**: `vendor/freemius/` ZIP'te yok (doğru). WordPress.org otomatik ekler.

3. **Text Domain**: `devforge-admin-cleaner` - Tüm metinler çevrilebilir.

4. **Versiyon**: 2.5.0

5. **Onay Süresi**: 1-10 gün (genellikle 5 iş günü)

## Yükleme Sonrası

Onaylandıktan sonra:
1. SVN repository otomatik oluşturulur
2. `upload-to-wordpress-org.sh` script'ini çalıştırabilirsiniz
3. Veya manuel olarak SVN'e yükleyebilirsiniz

