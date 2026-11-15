# دفعات بناء قاعدة الأغذية السورية

كل دفعة بمجلد:

- `wanted.csv`: الأغذية المطلوبة بصيغة أداة المطابقة (`food_name_ar, food_name_en, forms, search_terms, exclude, note`).
- `draft_reviewed.csv`: المسودة بعد مراجعتها مع صاحب المشروع (`pick = yes` على المختار).
- `bundle/`: الملفات الثلاثة الجاهزة (`records.csv`، `nutrient_values.csv`، `portions.csv`).

**الرفع:** من اللوحة: "إضافة أغذية" ← "ملفات جاهزة"، ارفع ملفات `bundle/` الثلاثة، ثم "افحص الملفات" ← "احفظ وافتح المراجعة" ← "اعتمد كل السليم".

**إعادة البناء** (إن تغيّر اختيار):
`python3 tools/usda_to_template.py build --fdc-dir tools/fdc/FoodData_Central_foundation_food_csv_2026-04-30 --fdc-dir tools/fdc/sr_legacy --nutrients database/seeders/data/nutrients.csv --source-codes database/seeders/data/nutrient_source_codes.csv --crosswalk <draft_reviewed.csv> --out <bundle>`

| # | الدفعة | الحالة |
| --- | --- | --- |
| 01 | حبوب وبقول (18 غذاء، 42 سجل) | جاهزة للرفع |
