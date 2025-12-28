# Güvenlik Notları

## ⚠️ ÖNEMLİ: Production'a Çıkmadan Önce

### 1. Test Modunu Kapat
`webtapot-admin-cleaner.php` dosyasındaki `WAC_TEST_PRO` kontrolünü kaldır veya sadece development ortamında kullan.

### 2. Free vs Pro Ayrımı
- Free versiyon: `includes/pro/` klasöründeki dosyalar YÜKLENMEMELİ
- Pro versiyon: Tüm dosyalar yüklenir
- Freemius'un otomatik kod temizleme özelliğini kullan veya manuel ayrım yap

### 3. Lisans Kontrolü
- Freemius sunucu tarafında doğrulama yapar
- Ancak kod değiştirilirse atlatılabilir
- Kritik özellikler için ek güvenlik katmanları ekle

### 4. Kod Obfuscation (İsteğe Bağlı)
- PHP kodunu gizlemek için obfuscation kullanabilirsin
- Ancak GPL lisansı gereği kaynak kodu açık olmalı
- Sadece premium versiyon için düşünülebilir

## Güvenlik Önerileri

1. **Sunucu Tarafı Doğrulama**: Kritik özellikler için API key ile remote validation
2. **Kod İmzalama**: Dosyaların değiştirilmediğini kontrol et
3. **Düzenli Güncellemeler**: Güvenlik açıklarını kapat
4. **Loglama**: Şüpheli aktiviteleri logla

## Test Modu Kullanımı

Sadece development ortamında:
```php
// wp-config.php
define( 'WP_DEBUG', true );
define( 'WAC_TEST_PRO', true );
```

Production'da ASLA kullanma!

