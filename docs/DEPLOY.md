# رفع NutriSyria على السيرفر

دليل لسيرفر واحد صغير (VPS بـ 2 غيغا رام يكفي لمئات المستخدمين): Nginx + PHP-FPM + MySQL على نفس الجهاز.
لا Redis، ولا queue worker، ولا scheduler، ولا Python على السيرفر.

## ما يحتاجه السيرفر

- **PHP 8.3** مع الإضافات: `pdo_mysql`، `mbstring`، `intl`، `gd`، `zip`، `exif`، `bcmath`، `opcache`.
- **MySQL 8** أو **MariaDB 10.6+**.
- **Nginx** و**Composer**.
- **Node.js** غير لازم على السيرفر إن بُنيت الواجهة على جهازك ورُفع مجلد `public/build`.
- **Python غير لازم:** أداة المطابقة تُشغَّل على جهازك (قرار صاحب المشروع)، ويُرفع ناتجها من "رفع دفعة جديدة".

## أول رفع

```bash
# 1. الكود والمكتبات (بلا مكتبات التطوير)
git clone https://github.com/dev-amer-oniza/NutriSyria.git nutrisyria && cd nutrisyria
composer install --no-dev --optimize-autoloader

# 2. الإعدادات
cp .env.production.example .env      # ثم املأ الفراغات: الرابط، قاعدة البيانات، البريد
php artisan key:generate

# 3. الواجهة: إما هنا (يحتاج Node) أو على جهازك ثم ارفع public/build
npm ci && npm run build

# 4. قاعدة البيانات والبيانات المرجعية
php artisan migrate --force
php artisan db:seed --force          # المغذيات والوحدات والأشكال والمصادر، مرة واحدة فقط

# 5. السرعة: يحفظ الإعدادات والمسارات والصفحات مجمّعة
php artisan optimize
php artisan filament:optimize

# 6. الصلاحيات
chown -R www-data:www-data storage bootstrap/cache
```

## كل تحديث بعد ذلك

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build              # أو ارفع public/build من جهازك
php artisan migrate --force
php artisan optimize:clear && php artisan optimize && php artisan filament:optimize
php artisan up
```

**مهم:** بعد أي تعديل على `.env` شغّل `php artisan optimize` من جديد، لأن الإعدادات محفوظة مجمّعة.

## Nginx

```nginx
server {
    listen 80;
    server_name nutrisyria.example;
    root /var/www/nutrisyria/public;
    index index.php;
    client_max_body_size 12M;        # ملفات الاستيراد حتى 10 ميغا

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~* \.(css|js|webp|jpg|png|svg|woff2)$ {
        expires 30d;                 # ملفات build أسماؤها تتغير مع كل بناء
        access_log off;
    }

    location ~ /\.(?!well-known) { deny all; }
}
```

ثم شهادة HTTPS مجانية: `certbot --nginx -d nutrisyria.example`.

## PHP (OPcache)

في `/etc/php/8.3/fpm/conf.d/99-nutrisyria.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0        ; أسرع؛ لذلك أعد تشغيل php-fpm بعد كل تحديث
memory_limit=256M
upload_max_filesize=10M
post_max_size=12M
```

بعد كل تحديث: `sudo systemctl reload php8.3-fpm`.

## النسخ الاحتياطي

نسخة يومية من قاعدة البيانات ومجلد الصور، تُحفظ خارج السيرفر:

```bash
# crontab -e
30 3 * * * mysqldump --single-transaction nutrisyria | gzip > /backups/db-$(date +\%F).sql.gz
40 3 * * * tar -czf /backups/storage-$(date +\%F).tar.gz -C /var/www/nutrisyria storage/app/public
50 3 * * * find /backups -mtime +14 -delete
```

## الفحص بعد الرفع

- `https://nutrisyria.example/up` يرد 200.
- الرئيسية واللوحة `/admin` تفتحان، والدخول برمز البريد يصل.
- `storage/logs/laravel-YYYY-MM-DD.log` بلا أخطاء.
