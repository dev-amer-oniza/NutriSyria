# رفع NutriSyria على السيرفر

طريقتان: **استضافة cPanel مشتركة** (الحالية: LiteSpeed + MariaDB، انظر القسم التالي)، أو سيرفر صغير خاص (VPS: Nginx + PHP-FPM + MySQL، آخر الملف).
في الحالتين: لا Redis، ولا queue worker، ولا scheduler (Cron)، ولا Python على السيرفر.

## ما يحتاجه السيرفر

- **PHP 8.3.36 أو أحدث** (أو 8.4.27+ / 8.5.12+): النسخ الأقدم فيها ثغرتا **PHP2Shell** (CVE-2026-109618 وCVE-2026-109619، تنفيذ أوامر عن بُعد بلا تسجيل دخول)، و`composer install` يرفضها. حدّث PHP دورياً (`apt upgrade`)، مع الإضافات: `pdo_mysql`، `mbstring`، `intl`، `gd`، `zip`، `exif`، `bcmath`، `opcache`.
- **MySQL 8** أو **MariaDB 10.5+** (الاتصال `mysql`، فالـ uuid نص عادي؛ XAMPP نفسه 10.4).
- **Composer**، وعلى VPS **Nginx**.
- الإضافات أيضاً: `fileinfo` (رفع الصور والملفات)، وهي مفعّلة افتراضياً على VPS لكن ليس دائماً في cPanel.
- **Node.js** غير لازم على السيرفر إن بُنيت الواجهة على جهازك ورُفع مجلد `public/build`.
- **Python غير لازم:** أداة المطابقة تُشغَّل على جهازك (قرار صاحب المشروع)، ويُرفع ناتجها من "رفع دفعة جديدة".

## على استضافة cPanel (الاستضافة الحالية)

فُحصت: PHP 8.3.37، MariaDB 10.5، LiteSpeed (يقرأ `public/.htaccess`)، SSH وGit وSSL. الكود في `~/nutrisyria` **خارج** `public_html`، و`public_html` يصير رابطاً إلى `~/nutrisyria/public`، فلا يصل أحد إلى `.env` أو الكود.

### 1. PHP (من الواجهة)
- **Select PHP Version:** النسخة **8.3** (تأكد أنها 8.3.36+)، وفي Extensions فعّل فوق الافتراضي: `intl`، `fileinfo`، `opcache` (مع `pdo_mysql`، `mbstring`، `gd`، `zip`، `exif`، `bcmath`).
- **Options** في نفس الصفحة: `memory_limit` 256M، `upload_max_filesize` 10M، `post_max_size` 12M، و`session.upload_progress.enabled` Off إن ظهر (وإلا اطلبه من الشركة؛ حماية إضافية من PHP2Shell).

### 2. قاعدة البيانات والبريد (من الواجهة)
- **Manage My Databases:** قاعدة ومستخدم بكلمة مرور قوية، وكل الصلاحيات للمستخدم على القاعدة. cPanel يضيف بادئة الحساب للاسمين (`nutrisyr_…`).
- **حسابات البريد الإلكتروني:** `noreply@` على الدومين. إعداداته من "Connect Devices" (عادةً `mail.<الدومين>` والمنفذ 465 SSL).

### 3. الكود (SSH)
```bash
php -v                                   # يجب أن يطابق النسخة المختارة (8.3.x)
# مفتاح قراءة فقط لـ GitHub: يُضاف العام منه في المستودع: Settings ← Deploy keys (بلا "Allow write")
ssh-keygen -t ed25519 -f ~/.ssh/github_nutrisyria -N ""
cat ~/.ssh/github_nutrisyria.pub
printf 'Host github.com\n  IdentityFile ~/.ssh/github_nutrisyria\n  IdentitiesOnly yes\n' >> ~/.ssh/config && chmod 600 ~/.ssh/config
git clone git@github.com:dev-amer-oniza/NutriSyria.git ~/nutrisyria && cd ~/nutrisyria
composer install --no-dev --optimize-autoloader
# بلا أمر composer: curl -sS https://getcomposer.org/installer | php  ثم  php composer.phar install --no-dev --optimize-autoloader
```

### 4. ما يُرفع من جهازك (إدارة الملفات ← رفع ثم فك الضغط)
- `public/build` بعد `npm ci && npm run build` على جهازك (لا Node على السيرفر).
- صور `storage/app/public` (المنتجات والمطاعم والأخصائيون) إلى نفس المكان.
- القاعدة المحلية: phpMyAdmin على جهازك ← Export (SQL)، ثم phpMyAdmin في cPanel ← القاعدة الجديدة ← Import.

### 5. الإعدادات
```bash
cp .env.production.example .env && chmod 600 .env
nano .env      # APP_URL، DB_*، MAIL_*، (GOOGLE_*، FDC_API_KEY اختياري)
```
**`APP_KEY` نفسه من `.env` جهازك، لا `key:generate`،** لأن القاعدة المحلية تُرفع كما هي، وفيها مشفّر بهذا المفتاح: الحساسيات ورموز الدخول. مفتاح جديد يجعلها غير مقروءة.

### 6. التشغيل
```bash
chmod -R 775 storage bootstrap/cache
php artisan migrate --force              # فقط ما استُجد بعد نسخة القاعدة (لا db:seed: البيانات المرجعية فيها)
php artisan optimize && php artisan filament:optimize
php artisan meals:contributions --all
# ربط الدومين بالمنصة (public_html الفارغ يُحفظ احتياطاً)
mv ~/public_html ~/public_html.old && ln -s ~/nutrisyria/public ~/public_html
```
`public/.htaccess` يحوّل كل زيارة إلى HTTPS (عدا localhost)، ويرفض الملفات المخفية، ويضغط النصوص، ويخلي المتصفح يحفظ الملفات المبنية سنة والصور شهراً.

### كل تحديث بعد ذلك (cPanel)
```bash
cd ~/nutrisyria && php artisan down
git pull && composer install --no-dev --optimize-autoloader
# ارفع public/build الجديد من جهازك إن تغيّرت الواجهة
php artisan migrate --force
php artisan optimize:clear && php artisan optimize && php artisan filament:optimize
php artisan up
```

### النسخ الاحتياطي (cPanel)
JetBackup 5 في الواجهة ينسخ الملفات والقاعدة. نزّل نسخة يدوية من القاعدة (phpMyAdmin ← Export) قبل أي تحديث فيه migration.

## على سيرفر خاص (VPS)

### أول رفع

```bash
# 1. الكود والمكتبات (بلا مكتبات التطوير)
git clone https://github.com/dev-amer-oniza/NutriSyria.git nutrisyria && cd nutrisyria
composer install --no-dev --optimize-autoloader

# 2. الإعدادات
cp .env.production.example .env      # ثم املأ الفراغات: الرابط، قاعدة البيانات، البريد
php artisan key:generate            # فقط لقاعدة جديدة؛ مع قاعدة منقولة انسخ APP_KEY القديم

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

### كل تحديث بعد ذلك

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build              # أو ارفع public/build من جهازك
php artisan migrate --force
php artisan optimize:clear && php artisan optimize && php artisan filament:optimize
php artisan up
```

**مرة واحدة بعد تحديث «من أين؟»** (migration `2026_11_18_000000`): `php artisan meals:contributions` يعبّي مساهمة المكوّنات للوجبات المعتمدة من قبل. الوجبات الجديدة تأخذها لحظة اعتمادها.

**مرة واحدة بعد تحديث «ماذا تستطيع أن تفعل»** (الخطوة 6، صارت المساهمات تحفظ غرامات كل مكوّن): `php artisan meals:contributions --all`.

**مهم:** بعد أي تعديل على `.env` شغّل `php artisan optimize` من جديد، لأن الإعدادات محفوظة مجمّعة.

### Nginx

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

### PHP (OPcache)

في `/etc/php/8.3/fpm/conf.d/99-nutrisyria.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0        ; أسرع؛ لذلك أعد تشغيل php-fpm بعد كل تحديث
memory_limit=256M
session.upload_progress.enabled=0  ; حماية إضافية من PHP2Shell؛ المشروع لا يستعمل هذه الميزة
upload_max_filesize=10M
post_max_size=12M
```

بعد كل تحديث: `sudo systemctl reload php8.3-fpm`.

### النسخ الاحتياطي

نسخة يومية من قاعدة البيانات ومجلد الصور، تُحفظ خارج السيرفر:

```bash
# crontab -e
30 3 * * * mysqldump --single-transaction nutrisyria | gzip > /backups/db-$(date +\%F).sql.gz
40 3 * * * tar -czf /backups/storage-$(date +\%F).tar.gz -C /var/www/nutrisyria storage/app/public
50 3 * * * find /backups -mtime +14 -delete
```

## الفحص بعد الرفع

- `https://<الدومين>/up` يرد 200، و`http://` يحوّل إلى `https://`.
- `https://<الدومين>/.env` يرد 403 أو 404، أبداً المحتوى.
- الرئيسية واللوحة `/admin` تفتحان، والتسجيل برمز البريد يصل، والصور تظهر.
- `storage/logs/laravel-YYYY-MM-DD.log` بلا أخطاء.
