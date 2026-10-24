# رفع NutriSyria على InfinityFree (نسخة تجريبية)

الدومين: http://nutrisyria.rf.gd

InfinityFree ما فيه terminal، فالمشروع بيترفع "مطبوخ": كل شي جاهز، وإنت بس بترفع الملفات وبتستورد قاعدة البيانات.

## شكل الملفات على السيرفر

```
htdocs/
  index.php, build/, css/, js/, images/, fonts/   ← ملفات الموقع العامة
  core/                                           ← باقي Laravel (مقفول، ما حدا بيفتحه من المتصفح)
    .env                                          ← إعداداتك
    storage/app/public/                           ← الصور (شعارات البراندات، صور المنتجات)
```

## 1) تجهيز النسخة

إما بتاخد ملف `nutrisyria-htdocs.zip` الجاهز، أو بتبنيه بنفسك من مجلد المشروع (Git Bash، وبدها composer وnpm وzip):

```
bash tools/deploy/build_infinityfree.sh
```

بيطلع المجلد `build/infinityfree/htdocs` والملف `build/infinityfree/nutrisyria-htdocs.zip`.

## 2) قاعدة البيانات

**على جهازك (XAMPP):**
1. تأكد أن قاعدتك محدّثة: `php artisan migrate`.
2. افتح phpMyAdmin واختار قاعدة المشروع، ثم **Export**، ثم **Custom**:
   - Format: SQL.
   - شيل علامة الصح عن الجداول التالية بعمود **Data** (خلّي الـ Structure): `sessions`، `cache`، `cache_locks`، `jobs`، `failed_jobs`.
   - لا تختار "Add CREATE DATABASE".
   - اختار "Disable foreign key checks".
3. بيطلع ملف `.sql`.

**على InfinityFree:**
1. من Control Panel، ثم **MySQL Databases**: اعمل قاعدة جديدة، واحفظ الاسم (مثل `if0_xxx_nutrisyria`) والـ Host (مثل `sqlXXX.infinityfree.com`) واسم المستخدم وكلمة السر.
2. افتح **phpMyAdmin** للقاعدة، ثم **Import**، واختار ملف `.sql`.

## 3) ملف الإعدادات `.env`

1. انسخ `core/.env.example` إلى `core/.env`.
2. عبّي:
   - `APP_KEY`: **نفس المفتاح يلي بملف `.env` على جهازك.** البيانات الصحية ورموز الدخول مشفّرة فيه؛ إذا تغيّر ما بتنفتح.
   - `DB_HOST`، `DB_DATABASE`، `DB_USERNAME`، `DB_PASSWORD`: من الخطوة 2.
3. الإيميل: بشكل افتراضي رموز التحقق بتنكتب بملف `core/storage/logs/laravel.log`. إذا بدك إيميل حقيقي، حط إعدادات Gmail المكتوبة جوّا الملف.

## 4) الرفع

1. بـ **FileZilla** (الأسرع، ولأن ملف ZIP أكبر من حد الرفع بمدير الملفات): Host `ftpupload.net`، والمستخدم وكلمة السر من صفحة **FTP Details** بالـ Control Panel.
2. ارفع **محتوى** `htdocs` (مو المجلد نفسه) إلى `htdocs` على السيرفر. احذف قبلها ملف `index2.html` الافتراضي إذا موجود.
3. انسخ صورك من جهازك: `storage/app/public/` على جهازك إلى `htdocs/core/storage/app/public/` على السيرفر.
4. ارفع ملف `.env` إلى `htdocs/core/.env`.

حوالي 18 ألف ملف، فالرفع ممكن ياخد ساعة أو أكتر. إذا وقف، FileZilla بيكمل من وين وقف.

## 5) التجربة

- http://nutrisyria.rf.gd: الصفحة الرئيسية.
- http://nutrisyria.rf.gd/admin: اللوحة (بحساب المدير تبعك من جهازك).
- http://nutrisyria.rf.gd/core/.env: لازم يعطي **403**. إذا فتح، خبّرني فوراً.

## إذا طلع خطأ 500

1. حط مؤقتاً `APP_DEBUG=true` بملف `.env`، وحدّث الصفحة، وخود صورة للخطأ.
2. أو افتح `core/storage/logs/laravel.log`.
3. رجّعها `false` بعدين، لأن الصفحة بتكشف معلومات.

## شو ما بيشتغل هونيك

- **صفحة مطابقة USDA**: InfinityFree بيمنع Python. اعمل المطابقة على جهازك، وارفع الحزمة من "رفع دفعة استيراد".
- أي تعديل بالكود بيحتاج بناء جديد ورفع الملفات يلي تغيّرت (أو كل `core/app` و`core/resources`).
