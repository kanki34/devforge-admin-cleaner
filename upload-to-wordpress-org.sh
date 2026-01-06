#!/bin/bash

# WordPress.org Plugin Upload Script
# Kullanım: ./upload-to-wordpress-org.sh

set -e

PLUGIN_SLUG="devforge-admin-cleaner"
SOURCE_DIR="/Users/macos/Desktop/bak/devforge-admin-cleaner"
SVN_DIR="/Users/macos/Desktop/bak/${PLUGIN_SLUG}-svn"

echo "🚀 WordPress.org Plugin Upload Başlatılıyor..."
echo ""

# 1. SVN Repository'yi kontrol et
if [ ! -d "$SVN_DIR" ]; then
    echo "📦 SVN Repository clone'lanıyor..."
    cd /Users/macos/Desktop/bak
    svn co https://plugins.svn.wordpress.org/${PLUGIN_SLUG} ${PLUGIN_SLUG}-svn
    echo "✅ SVN Repository clone'landı"
else
    echo "✅ SVN Repository zaten mevcut"
    cd "$SVN_DIR"
    svn update
fi

cd "$SVN_DIR"

# 2. Trunk'ı temizle
echo ""
echo "🧹 Trunk temizleniyor..."
rm -rf trunk/*
echo "✅ Trunk temizlendi"

# 3. Plugin dosyalarını kopyala (vendor hariç)
echo ""
echo "📋 Plugin dosyaları kopyalanıyor..."
rsync -av \
  --exclude='vendor' \
  --exclude='.git' \
  --exclude='*.tmp' \
  --exclude='.DS_Store' \
  --exclude='node_modules' \
  --exclude='.env' \
  --exclude='.wordpress-org-ignore' \
  --exclude='WORDPRESS-ORG-README.md' \
  --exclude='upload-instructions.md' \
  --exclude='FIRST-UPLOAD-GUIDE.md' \
  --exclude='upload-to-wordpress-org.sh' \
  "$SOURCE_DIR/" trunk/

echo "✅ Dosyalar kopyalandı"

# 4. SVN Add
echo ""
echo "➕ SVN Add yapılıyor..."
svn add trunk/* --force
echo "✅ SVN Add tamamlandı"

# 5. Durum kontrolü
echo ""
echo "📊 SVN Durumu:"
svn status

echo ""
echo "⚠️  Lütfen yukarıdaki durumu kontrol edin."
echo ""
read -p "Commit yapmak istiyor musunuz? (y/n) " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo ""
    echo "💾 Commit yapılıyor..."
    svn commit -m "Initial release: Version 2.5.0"
    echo "✅ Commit tamamlandı"
    
    echo ""
    read -p "Tag oluşturmak istiyor musunuz? (y/n) " -n 1 -r
    echo ""
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo ""
        echo "🏷️  Tag oluşturuluyor..."
        svn copy trunk/ tags/2.5.0/
        svn commit -m "Tagging version 2.5.0"
        echo "✅ Tag oluşturuldu: 2.5.0"
    fi
else
    echo "❌ Commit iptal edildi"
fi

echo ""
echo "✨ İşlem tamamlandı!"

