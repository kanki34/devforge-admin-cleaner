# GitHub Stratejisi - Güvenlik

## ⚠️ ÖNEMLİ: Premium Kodlar Public'te OLMAZ!

### Riskler
1. **Herkes Premium Kodu Görebilir**: Public repo = herkes indirebilir
2. **Lisans Kontrolü Bypass**: Kod görülürse, kırılabilir
3. **Rekabet Avantajı Kaybı**: Rakipler özellikleri kopyalayabilir
4. **Gelir Kaybı**: Ücretsiz kullanım artar

## ✅ Önerilen Yaklaşım

### Seçenek 1: İki Ayrı Repo (ÖNERİLEN)

#### Public Repo (Free Versiyon)
```
webtapot-admin-cleaner (PUBLIC)
├── includes/
│   └── pro/ (BOŞ veya sadece .gitkeep)
├── webtapot-admin-cleaner.php
└── .gitignore (pro/ klasörünü ignore et)
```

**Kullanım:**
- WordPress.org'a yüklenecek versiyon
- Herkes görebilir, indirebilir
- Sadece FREE özellikler

#### Private Repo (Premium Versiyon)
```
webtapot-admin-cleaner-premium (PRIVATE)
├── includes/
│   └── pro/ (TÜM PREMIUM DOSYALAR)
├── webtapot-admin-cleaner.php
└── Tüm dosyalar
```

**Kullanım:**
- Sadece sen erişebilirsin
- Freemius'a yüklenecek versiyon
- Tüm premium özellikler

### Seçenek 2: Tek Private Repo

```
webtapot-admin-cleaner (PRIVATE)
├── free/ (Free versiyon dosyaları)
├── premium/ (Premium versiyon dosyaları)
└── build/ (Otomatik build scriptleri)
```

**Avantaj:** Tek repo, kolay yönetim
**Dezavantaj:** Public görünürlük yok (WordPress.org için)

### Seçenek 3: GitHub + Local Build

```
Public Repo: Sadece free kodlar
Local: Premium kodlar (git'e eklenmez)
Build Script: İki versiyonu otomatik oluşturur
```

## 🛠️ Uygulama Planı

### 1. Public Repo Oluştur (Free)
```bash
# Mevcut repo'yu free versiyona çevir
cd webtapot-admin-cleaner
git rm -r --cached includes/pro/*.php
git commit -m "Remove premium files from public repo"
```

### 2. Private Repo Oluştur (Premium)
```bash
# Yeni private repo oluştur
# Tüm dosyaları (premium dahil) buraya koy
```

### 3. .gitignore Ayarları

**Public Repo:**
```gitignore
includes/pro/*.php
!includes/pro/.gitkeep
```

**Private Repo:**
```gitignore
# Premium dosyalar dahil edilir
# Sadece geçici dosyalar ignore edilir
```

## 📦 Build & Deploy Stratejisi

### Senaryo 1: WordPress.org (Free)
1. Public repo'dan `git pull`
2. ZIP oluştur (premium dosyalar yok)
3. WordPress.org'a yükle

### Senaryo 2: Freemius (Premium)
1. Private repo'dan `git pull`
2. ZIP oluştur (tüm dosyalar dahil)
3. Freemius dashboard'a yükle

### Senaryo 3: Development
1. Local'de premium dosyalarla çalış
2. Değişiklikleri ilgili repo'ya push et
3. Free değişiklikler → Public repo
4. Premium değişiklikler → Private repo

## 🔒 Güvenlik Checklist

- [ ] Public repo'da premium dosya YOK
- [ ] Private repo'da tüm dosyalar VAR
- [ ] .gitignore doğru yapılandırılmış
- [ ] Build scriptleri test edilmiş
- [ ] WordPress.org versiyonu premium içermiyor
- [ ] Freemius versiyonu tüm özellikleri içeriyor

## 🚀 Hızlı Başlangıç

### Public Repo İçin:
```bash
# Premium dosyaları kaldır
git rm -r --cached includes/pro/*.php
echo "includes/pro/*.php" >> .gitignore
git commit -m "Remove premium files for public repo"
git push
```

### Private Repo İçin:
```bash
# Tüm dosyaları dahil et
# .gitignore'da premium dosyaları kaldır
git add .
git commit -m "Initial commit with premium features"
git push
```

## ⚠️ DİKKAT

**ASLA:**
- Premium dosyaları public repo'ya push etme
- Public repo'yu private'a çevirme (geçmiş commitler görünür kalır)
- Premium kodları commit message'da bahsetme

**HER ZAMAN:**
- Private repo kullan premium için
- Public repo sadece free kodlar
- Build öncesi kontrol et

