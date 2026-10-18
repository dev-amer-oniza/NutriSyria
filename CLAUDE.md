# NutriSyria: دليل المتابعة

اقرأ هذا الملف كاملاً قبل أي عمل. هو ملخص المشروع، والقرارات المتفق عليها، والوضع الحالي، والخطوة التالية.
آخر تحديث: 2026-10-12.

## التواصل مع صاحب المشروع
- **تكلّم بالعربي، باللهجة السورية، وببساطة.** اشرح بأمثلة من المطبخ (كوب حمص، حبة فليفلة) لا بمصطلحات برمجية.
- **اشرح قبل أن تنفّذ أي تغيير كبير، وانتظر الموافقة.** قسّم العمل لخطوات، وقل بعد كل خطوة ما الذي تغيّر وما المطلوب منه.
- **عند القرارات** (قواعد حساب، اختيار مصدر بيانات) اعرض الخيارات مع توصيتك، ودعه يختار.
- **لا تفتح Pull Request إلا إذا طلبه.** وإذا طلبه، تأكد أن الـ PR السابق لم يُدمج قبل أن تدفع عليه commits جديدة. حصل مرة أن دُمج PR قبل وصول commit إليه، فضاع إصلاح.

## ما هو المشروع
لوحة إدارة (Laravel 12 + Filament 4) تبني **قاعدة بيانات للتركيب الغذائي للأغذية السورية**، ثم تحسب **القيم الغذائية لوصفات منتجات المطاعم** (البراندات). المصدر الحالي للأرقام هو USDA FoodData Central، مع خطة لإضافة مصادر محلية لاحقاً.

## خريطة الكود المهمة
| المجال | الملفات |
|---|---|
| نموذج البيانات | `database/migrations/2026_*` (foods, food_source_records, food_nutrient_values, food_portions, data_sources, nutrient_source_codes, import_batches/rows, brands/products/product_recipes...) |
| الاستيراد | `app/Services/Import/BundleImporter.php` (stage → validate → commit → approve)، `BundleValidator.php` (فحوص علمية، pure)، `Lookups.php` |
| أداة المطابقة مع USDA | `tools/usda_to_template.py` (suggest / build / search / verify-map)، `tools/wanted_foods_2.csv` (قائمة الأغذية السورية)، `config/source_adapters.php` |
| بيانات USDA الخام | `tools/fdc/sr_legacy`، `tools/fdc/FoodData_Central_foundation_food_csv_2026-04-30`، `tools/fdc/FoodData_Central_survey_food_csv_2024-10-31` (FNDDS) |
| تحويل الكمية إلى غرام | `app/Services/Nutrition/GramsConverter.php` (يقرأ من DB)، `PortionResolver.php` (القواعد، pure)، `GramsConversion.php` |
| حساب الوصفة | `RecipeCalculator.php` (pure)، `RecipeAnalysisResult.php`، `RecipeAnalysisService.php` (analyze + approve)، `NutrientGapFiller.php` (تكميل الناقص، pure) |
| الداشبورد | `app/Filament/Clusters/{FoodDatabase,ImportReview,Brands,ReferenceSettings}` |
| صفحات مهمة | `ImportReview/Pages/SourceMatchingTools.php` (مطابقة USDA من الواجهة)، `UploadImportBatch.php`، `Brands/Pages/RecipeAnalysis.php` + `resources/views/filament/clusters/brands/pages/recipe-analysis.blade.php` |
| الصفحة الرئيسية | `app/Filament/Widgets/{FoodDatabaseStats,DataSourcesOverview}.php`، والأرقام من `app/Services/Dashboard/DatabaseOverview.php`: أغذية معتمدة/للمراجعة (الغذاء يُعدّ مرة، وقد يظهر في الاثنين)، المصادر، الدفعات |
| البذور | `database/seeders/data/*.csv` (nutrients، nutrient_source_codes، measure_units + aliases، food_forms، data_sources) |

الأسلوب المتبع في الكود: المنطق الحسابي في كلاسات **pure** بلا قاعدة بيانات (Validator، Calculator، PortionResolver، GapFiller) لتُختبر بسهولة، وكلاس رفيع يقرأ من DB ويمرر لها arrays.

## القواعد المتفق عليها (لا تغيّرها دون موافقة)
1. **كل رقم له مصدر.** `food_source_records.data_source_id` إلزامي. السجلات المستوردة تبدأ `pending_review`، ولا تُستخدم في الوصفات قبل اعتمادها.
2. **أولوية المصادر** في `data_sources.priority`، والرقم الأصغر يُستخدم أولاً: تحليل مخبري أو جدول محلي (10–50)، ثم Foundation (90)، ثم SR Legacy (100)، ثم FNDDS (120).
3. **تحويل الكمية لغرام** (PortionResolver):
   - وحدات الوزن (g, kg, mg, oz, lb) تتحول عبر `measure_units.base_factor`.
   - الحصة تُبحث بالترتيب: من سجل المكوّن نفسه، ثم من نفس الشكل في مصدر آخر، ثم من حصة عامة بلا شكل. **لا تُستعمل حصة شكل آخر أبداً** (كوب الحمص النيء 200 غ، والمسلوق 164 غ).
   - عند تعدد الحصص: `is_default` أولاً، ثم الحصة البسيطة ("كوب"، "1 cup") على الموصوفة ("cup, mashed")، ثم الثقة الأعلى. إن بقي أكثر من وزن مختلف بنسبة تتجاوز 1%، **يُطلب من المشرف اختيار حصة افتراضية، ولا يُخمَّن**.
   - **حجم الحصة في سطر الوصفة** (`product_recipe_ingredients.food_portion_id`): إن كان للغذاء أكثر من وزن لنفس الوحدة (حبة صغيرة/وسط/كبيرة)، يظهر حقل "حجم الحصة" في نموذج المكوّن (`PortionResolver::choices`). الافتراضية تُختار فيه سلفاً، والحقل إلزامي إن لم توجد افتراضية. الاختيار يغلب `is_default`، لكن فقط إن كان حصة صالحة (نفس الوحدة، ونفس السجل أو الشكل أو بلا شكل). وإن حُذفت الحصة رجع السطر للقواعد العادية.
   - **كلمات الحجم** (small, medium, large, whole, صغيرة, كبيرة...) ليست حصصاً بسيطة.
   - الحجم (ml, l, fl oz) يتحول عبر كثافة حصة حجمية فقط. الكوب والملعقة وحدات منزلية بلا حجم ثابت.
   - المكوّن الذي لا يمكن تحويله **لا يُحسب صفراً**: يظهر في التحليل مع السبب، ويمنع الاعتماد.
4. **إعادة الاستيراد لا تمحو عمل المشرف.** الحصص تُحدَّث في مكانها (تطابق بالوحدة والكمية والاسم في المصدر). `is_default` والوزن المصحَّح يدوياً يبقيان. وإن لم تحوِ الحزمة `portions.csv` فلا تُلمس الحصص.
   - ملاحظة السجل: إن لم تتغير الأرقام، تُكتب ملاحظة الحزمة فقط إن كانت تُبقي النص الحالي وتضيف إليه ("مقاربة: … | FDC: …" بعد "FDC: …")، بلا مراجعة جديدة. الملاحظة التي أعاد المشرف كتابتها لا تُمس.
5. **الحصة المضافة من صفحة سجل** تحفظ شكل ذلك السجل (`FoodPortion::saving`).
6. **تكميل الناقص (borrowing)**، في `NutrientGapFiller`:
   - المكوّن يأخذ أرقامه من السجل الذي اختاره المشرف.
   - الناقص فقط يُكمَّل من سجل **معتمد آخر لنفس الغذاء ونفس الشكل**، حسب الأولوية، قيمةً بقيمة بلا متوسطات.
   - **السعرات الناقصة تُحسب أولاً** من بروتين ودهون وكربوهيدرات نفس السجل (4/9/4)، ولا تُستعار إلا إن نقص أحدها.
   - صفحة التحليل وملاحظات القيم المعتمدة تذكر ما استُعير ومن أين.
   - الاعتماد مسموح مع وجود قيم مستعارة.
7. **أداة المطابقة:**
   - تقبل أكثر من `--fdc-dir`، وكل إصدار يُرتَّب وحده، فيمكن اختيار عنصر من Foundation وعنصر من SR Legacy لنفس الغذاء والشكل.
   - تتجاهل نسخ Foundation القديمة (غير الموجودة في `foundation_food.csv`).
   - لا تعلّم "yes" تلقائياً لعنصر ناقص البروتين أو الدهون أو الكربوهيدرات، ولا تعلّم نفس `fdc_id` مرتين.
   - في FNDDS تترجم أرقام المغذيات القديمة (208 → 1008)، ولا تفترض الشكل.
   - عمود `note` اختياري في قائمة الأغذية: ينتقل إلى المسودة، ثم يوضع أول ملاحظة السجل عند `build` ("<note> | FDC: …"). يُستعمل لتسجيل المقاربات (8 حالياً، منها الزعتر).
8. **المصدر المنصوح للمطابقة:** Foundation + SR Legacy معاً. أما FNDDS فللأطباق الجاهزة فقط، لا للمكوّنات.

9. **الأدوار والصلاحيات** (`App\Auth\Access`، تُنشأ بـ migration `2026_10_22_000002`):
   - **super-admin:** كل شيء (`User::allows()` يعيد true دائماً). **brand-owner:** براندات مربوطة به في `brand_user` فقط ومنتجاتها ووصفاتها، بلا اعتماد قيم رسمية. **nutritionist:** دفعات الاستيراد (عرض واعتماد/رفض، والرفض بسبب إلزامي)، وعرض كل المنتجات، و**اعتماد القيم الغذائية الرسمية** (قرار صاحب المشروع).
   - الأدوار القديمة (restaurant-owner, customer, delivery) محذوفة، ومسار `/api/set-password` محذوف (كان يغيّر كلمة سر أي مستخدم بلا OTP).
   - **الحماية:** `strictAuthorization()` في اللوحة؛ كل نموذج له policy في `app/Policies` يستخدم `DeniesByDefault` (كل قدرة ممنوعة ما لم تُفتح). عزل البراندات في `App\Auth\BrandScope` على مستوى الاستعلام (القوائم، صفحات السجل، البحث، القوائم المنسدلة، الفلتر)، فبراند غيره يعطي 404. حقول البراند والمكوّن والحصة تُتحقق في السيرفر. الأزرار تُفحص بـ `->authorize()`.
   - **السجل:** `food_source_records.reviewed_by/reviewed_at` عبر `App\Services\Import\RecordReview`، و`product_recipes.nutrition_approved_by/at`. هذه الحقول ليست fillable.
   - صلاحيات كل دور قابلة للتعديل من اللوحة (قرار صاحب المشروع)، لكن الملكية (براندي فقط) مكتوبة في الـ policies ولا تتغير من الواجهة.
   - **الاختبارات:** `tests/Feature/AccessControlTest.php`. `TestCase::signInAs($role)` للدخول بدور، و`central` يشارك قاعدة SQLite في الاختبارات.
   - **أي Resource جديد يحتاج policy** وإلا يظهر خطأ (وضع strict).
   - **صفحات الإدارة** (cluster `AccessControl`، للمدير فقط): المستخدمون (إضافة، دور واحد لكل مستخدم، ربط البراندات لصاحب البراند، تفعيل/إيقاف، كلمة مرور 8+ بحروف وأرقام) والأدوار (تعديل صلاحيات صاحب البراند والأخصائي؛ دور المدير غير قابل للتعديل). `users.manage` لا يُعطى لأي دور من الواجهة (منعاً لرفع الصلاحيات). قواعد منع إغلاق النظام في `App\Auth\AccountRules`. ربط البراند بمسؤوليه أيضاً من صفحة البراند (للمدير فقط).

## الوضع الحالي (أين وصلنا)
- **مدموج في main:** PRs #1 إلى #6. تشمل:
  - إصلاح GramsConverter.
  - إصلاحات الحصص وإعادة الاستيراد.
  - تحسين مطابقة الأغذية السورية (المطابقة التلقائية من 73 إلى 191 من 228 على SR Legacy).
  - Foundation + التكميل.
  - إصلاحات FNDDS.
  - مهلة صفحة المطابقة، وتسريع `suggest` 14 مرة.
- **صاحب المشروع أنجز:**
  - المطابقة من الداشبورد على Foundation + SR Legacy.
  - رفع ملف المطابقة المراجَع (`draft_crosswalk_reviewed.csv`: 243 سجل، 6,859 قيمة) واستيراده.
- **تقرير التغطية** (ملف إكسل سُلِّم له): 206 من 230 غذاء/شكل متوفرة (90%).
  - 38 مستوى "أ": Foundation، تحليل حديث.
  - 162 مستوى "ب": SR Legacy.
  - 6 مقاربات: لبنة ← زبادي يوناني، جبنة بيضاء ← فيتا، خبز عربي ← بيتا، مخلل، زيت نباتي ← صويا، سمنة ← زبدة مصفّاة.
- **إضافات بعد التقرير** (في `wanted_foods_2.csv`، لم تُستورد بعد؛ ملاحظات المقاربات في عمود `note`):
  - زعتر يابس (الزعتر البري السوري، Origanum syriacum) ← أوريغانو مجفف (SR 171328)، لأنه نباتياً من الأوريغانو.
  - زعتر أخضر ← Thyme, fresh: **مقاربة** باختيار صاحب المشروع، لأن USDA لا تملك أوريغانو أخضر.
  - حبة البركة: غير متوفرة في USDA.
  - 24 غير متوفرة: أجبان سورية، دبس، فريكة، زعتر، سماق، محلب، مستكة، قيمر، فلفل حلبي، مكدوس، حلاوة، ماء ورد وزهر.
- **فحص الدقة:** 6,858 قيمة من Foundation قورنت آلياً بملفات USDA الأصلية: تطابق 100%.
- **مستند للمستثمر وأخصائي التغذية:** "دقة بيانات NutriSyria" (Claude Docs artifact). فيه سؤال مفتوح: هل تبقى نسبة 10–20% (تقدير عام لفرق حساب الوصفة عن التحليل المخبري) في نسخة المستثمر؟

## الخطوة التالية
1. **(عند صاحب المشروع)** اعتماد سجلات الدفعة المستوردة، **Foundation وSR Legacy معاً**، حتى يعمل التكميل. يمكن من الواجهة أو `php artisan import:approve <batch>`.
2. **(اختياري الآن)** تحديد الحصة الافتراضية للأغذية الملتبسة (حوالي 20)، لأن حجم الحصة صار يُختار في سطر الوصفة:
   - البندورة: حبة وكوب.
   - البيض والبصل والجزر واللفت والموز والتين: حسب الحجم.
   - الكسكس المطبوخ: كوب.
3. **(عند صاحب المشروع)** تجربة وصفة حقيقية، مثل مجدرة (عدس + برغل + زيت + بصل)، وإرسال صورة شاشة التحليل للمراجعة.
4. **إصلاحات الداشبورد المعروفة** (بعد موافقته):
   - أخطاء 500:
     - إنشاء سجل مصدر من صفحة الغذاء: `Call to undefined method Food::foodSourceRecords()`. الـ nested resource يتوقع اسم علاقة غير `sourceRecords`.
     - تكرار `external_ref`: يظهر خطأ قاعدة بيانات بدل رسالة تحقق.
     - حذف مصدر بيانات مستخدم: خطأ FK بدل رسالة.
   - البحث العربي بلا توحيد للكتابة ("ارز" لا يجد "أرز"، "جبنه" لا يجد "جبنة"). استخدم `ArabicNormalizer` و`food_aliases.search_normalized`.
   - **قائمة اختيار المكوّن في الوصفة:**
     - تعرض حالياً سجلات مرفوضة وقيد المراجعة؛ يجب أن تعرض المعتمد فقط.
     - يجب أن تضع Foundation أولاً، وتُظهر إن كان السجل ناقصاً.
   - رفع ملف تالف (أعمدة ناقصة، فاصل `;`، ملف فارغ) يعيد 422 بلا أي رسالة، ويترك دفعات معلّقة.
   - سبب فشل التحويل لا يظهر في tooltip الجدول، لأن Filament يستخدم `emptyTooltip` عندما تكون القيمة null.
5. **تحسينات صغيرة متفق عليها مبدئياً:**
   - الكربوهيدرات السالبة في USDA (لحوم) تُخزَّن صفراً بدل حذفها.
   - إضافة ربط `1085` (Total fat NLEA) و`2033` (Fiber AOAC) في `nutrient_source_codes.csv`.
   - إضافة aliases لوحدات غير معروفة (link, roast, steak, fillet, container...).
6. **ثم:** تقييم كامل للنظام.
7. **ثم (خطة صاحب المشروع الأصلية):**
   - حذف بقايا نظام المطاعم والطلبات القديم: routes/api.php (Cart/Restaurant controllers غير موجودة)، tenancy، OTP. (`/api/set-password` حُذف.) تنبيه: `/api/register` يسند دور `customer` المحذوف فيفشل بعد إنشاء المستخدم، و`/api/login` يعطي توكن Sanctum لأي مستخدم: الأفضل حذف الـ API القديم كله.
   - بعدها بناء واجهات Blade، وتسجيل الدخول.

## هوية اللوحة (اختيار صاحب المشروع)
- **الألوان:** "زيتون وزعتر": أساسي زيتوني (`AdminPanelProvider::OLIVE`، مدرّج يدوياً بـ OKLCH لأن `Color::hex` يعطي أخضر ليموني)، ورماديات `Stone` الدافئة. ألوان الحالة ثابتة: أخضر معتمد، كهرماني للمراجعة، أحمر مرفوض.
- **الخط:** IBM Plex Sans Arabic (Google Fonts).
- **الشعار الأساسي** (من صاحب المشروع): `public/images/brand/nutrisyria-logo.jpg`. علامة ابتسامة + نقطة ليمونية على أسود مخضر. الألوان: أسود `#0a1310`، أبيض `#f2f4ef`، ليموني `#c8f164` (نفس درجة لون الزيتوني تقريباً). مرسومة SVG في `public/images/brand/nutrisyria-mark.svg` (favicon) و`resources/views/filament/admin/brand.blade.php`. في الوضع النهاري تُكتب "Syria" بالزيتوني لأن الليموني لا يُقرأ على الأبيض.
- **اللغة والاتجاه:** اللوحة عربية RTL دائماً عبر `App\Http\Middleware\UseArabicInPanel` (persistent)، أياً كان `APP_LOCALE`.
- **الوضع الليلي يتبع الشعار** (طلب صاحب المشروع): رماديات خضراء-سوداء (خلفية `#0a1310`) والليموني أساسي، عبر `html.dark { --gray-*; --primary-* }` في `theme.css`، مع نص داكن على الأزرار الأساسية وعلامة الصح. النهاري يبقى زيتونياً وحجرياً.
- **الثيم الليلي** مفعّل؛ `resources/css/filament/admin/theme.css` يستعمل ألوان اللوحة فقط (primary-*/gray-*)، لا ألواناً ثابتة.
- **المراحل:** 1) الألوان والخط والعربية (تم). 2) أيقونات Heroicons (outline، والمعبّأة للقسم الحالي)، مجموعات القائمة (البيانات الغذائية، المطاعم، الإعدادات)، شارة السجلات المعلّقة على "الاستيراد والمراجعة" (تم). 3) تلميع (تم): ألوان وأيقونات الحالات في الـ enums (`FoodSourceStatus`، `ImportBatchStatus`)، صور بديلة `App\Filament\Support\Placeholders`، إعدادات الجداول العامة في `AdminPanelProvider::boot` (صفوف مخططة، 25 بالصفحة، "لا" شرطة رمادية)، رسائل التحقق العربية `lang/ar/validation.php`.
- **المعاينة:** صفحة الخيارات المعروضة عليه: https://claude.ai/artifact/TAeT2Eh9UczTrzxWBrPbPr

## معلومات تشغيل وتطوير
- **الاختبارات:** `php vendor/bin/phpunit` (SQLite في الذاكرة). فشل `Tests\Feature\ExampleTest` (404 على `/`) قديم ولا علاقة له.
- **قاعدة البيانات:**
  - `User` ونماذج أخرى تستعمل اتصال `central` الثابت على MySQL. في سكربتات tinker على SQLite نفّذ أولاً: `config(['database.connections.central' => config('database.connections.sqlite')]);`
  - قيد CHECK في migration جدول `food_nutrient_values` يُطبَّق على MySQL/MariaDB فقط.
- **التثبيت:**
  - `composer install` قد يحتاج `--ignore-platform-req=ext-redis` في بعض البيئات.
  - اللوحة تحتاج `npm ci && npm run build` (viteTheme).
- **الاستيراد من سطر الأوامر:** `php artisan import:bundle <dir>` ← `import:commit <batch>` ← `import:review <batch>` ← `import:approve <batch>`.
- **المطابقة من سطر الأوامر:**
  `python3 tools/usda_to_template.py suggest --fdc-dir tools/fdc/FoodData_Central_foundation_food_csv_2026-04-30 --fdc-dir tools/fdc/sr_legacy --wanted tools/wanted_foods_2.csv --out draft.csv`
  ثم `build` بنفس `--fdc-dir` و`--nutrients database/seeders/data/nutrients.csv --source-codes database/seeders/data/nutrient_source_codes.csv --crosswalk draft.csv --out bundle`.
- **بيئة صاحب المشروع:** Windows على الأغلب. `PYTHON_BIN` في `.env` يحدد مسار Python للوحة (الافتراضي `python`).
- **SR Legacy مجمّدة** منذ 2018-04، ولا توجد نسخة أحدث. أما Foundation فتتحدث دورياً، وتوضع النسخة الجديدة كمجلد جديد داخل `tools/fdc/`.
