# onyxinc Steam Checker

Web tabanlı Steam hesap checker - Proxyless mod destekli, GitHub proxyler otomatik alınır.

![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)

## Özellikler

- 🔍 **Steam hesap kontrol** - RSA şifreleme ile Steam API doğrulama
- 🌐 **Proxyless mod** - Doğrudan bağlantı (proxy gerektirmez)
- 📦 **GitHub Proxy** - GitHub'dan otomatik proxy çekme (cache ile)
- 📊 **Combo checker** - Toplu hesap kontrol (kullanıcı:şifre formatı)
- 📝 **Canlı log** - Gerçek zamanlı kontrol logları
- 🛡️ **CSRF koruması** - Token tabanlı güvenlik
- 🎨 **Modern dark theme** - Responsive arayüz

## Gereksinimler

- **PHP 7.4+**
- **php_curl** genişlenti
- **php_bcmath** genişlenti
- **php_session** genişlenti

## Kurulum

1. Repoyu klonla:
```bash
git clone https://github.com/onyxinc-code/steam-checker.git
cd steam-checker
```

2. Web sunucusunda `index.php`'nin bulunduğu dizine yerleştir

3. İzin ver:
```bash
chmod 777 proxy_cache.json
```

4. Tarayıcıda aç

## Kullanım

1. **Combo Checker** sekmesinde `kullanici:şifre` formatında hesaplar gir
2. **Başlat** butonuna tıkla
3. Sonuçlar Hit / 2FA / Bad / Error olarak kategorize edilir

### Tek Hesap Kontrolü

- **Tek Hesap** sekmesinden tek bir hesap kontrol edebilirsiniz

## API

```http
POST /
Content-Type: application/x-www-form-urlencoded

action=check_single&csrf=TOKEN&username=kullanici&password=sifre
```

```http
POST /
Content-Type: application/x-www-form-urlencoded

action=get_proxies&csrf=TOKEN
```

## Proje Yapısı

```
onyxinc-steam-checker/
├── index.php          # Ana PHP dosyası (backend + frontend)
├── assets/
│   ├── style.css      # CSS stilleri
│   └── script.js      # JavaScript dosyası
├── proxy_cache.json   # Proxy cache dosyası (runtime)
├── LICENSE            # MIT Lisans
└── README.md          # Bu dosya
```

## Lisans

Bu proje **MIT Lisans** ile lisanslanmıştır. Detay için [LICENSE](LICENSE) dosyasına bakın.

## Disclaimer

Bu araç yalnızca eğitim amaçlıdır. Steam'in Hizmet Şartlarını ihlal edebilir. Sorumluluğu kullanıcının kendi üzerineindedir.

## Developer

**onyxinc**
