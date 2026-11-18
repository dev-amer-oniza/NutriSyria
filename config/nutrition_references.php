<?php

/*
| The references behind the general rules: energy, macros, saturated fat, sodium, fibre,
| sugars and body mass index. The health conditions' references went with the conditions
| (the owner's decision, Nov 2026; they are in main's history at b76d834).
|
| A rule in config/nutrition_criteria.php names its references with 'refs'. Each reference
| holds the source as it was read (organisation, title, year, url, the place in it), the
| verbatim sentence the number comes from, what it applies to, and the four gates:
|   source         the guideline itself was read, and the quote is copied from it
|   applicability  it says which condition, stage and people the number is for
|   test           a test checks the code applies the number as the reference says
|                  (tests/Unit/NutritionReferencesTest.php)
|   specialist     a specialist reviewed it (none yet: kept for the future)
| Never write a quote or a number from memory: read the reference and copy it.
*/

return [

    'who_sodium' => [
        'org' => 'WHO',
        'title' => 'Salt reduction (fact sheet), from the WHO guideline Sodium intake for adults and children (2012)',
        'year' => 2012,
        'url' => 'https://www.who.int/news-room/fact-sheets/detail/salt-reduction',
        'where' => 'Recommendations for reducing sodium',
        'quote' => 'For adults, WHO recommends less than 2000 mg/day of sodium (equivalent to less than 5 g/day salt), or just under a teaspoon.',
        'summary' => 'للبالغين: أقل من 2000 ملغ صوديوم في اليوم (أقل من 5 غ ملح).',
        'applies' => 'البالغون',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'dga_2025_sodium' => [
        'org' => 'USDA/HHS',
        'title' => 'Dietary Guidelines for Americans, 2025–2030',
        'year' => 2026,
        'url' => 'https://cdn.realfood.gov/DGA.pdf',
        'where' => 'Sodium (p. 4)',
        'quote' => 'The general population, ages 14 and above, should consume less than 2,300 mg per day of sodium. Highly active individuals may benefit from increased sodium intake to offset sweat losses.',
        'summary' => 'من عمر 14: أقل من 2300 ملغ في اليوم. مرجع ثانٍ يختلف عن WHO، فالصوديوم توصية لا قيد.',
        'applies' => 'من عمر 14 فأكثر',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'who_fats_2023' => [
        'org' => 'WHO',
        'title' => 'WHO updates guidelines on fats and carbohydrates (Saturated fatty acid and trans-fatty acid intake; Total fat intake; Carbohydrate intake)',
        'year' => 2023,
        'url' => 'https://www.who.int/news/item/17-07-2023-who-updates-guidelines-on-fats-and-carbohydrates',
        'where' => 'News release, 17 July 2023',
        'quote' => 'WHO reaffirms that adults should limit total fat intake to 30% of total energy intake or less. Fat consumed by everyone 2 years of age and older should be primarily unsaturated fatty acids, with no more than 10% of total energy intake coming from saturated fatty acids …',
        'summary' => 'الدهون الكلية حتى 30% من السعرات للبالغين، والمشبعة حتى 10%.',
        'applies' => 'البالغون (والمشبعة من عمر سنتين)',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'dga_2025_saturated' => [
        'org' => 'USDA/HHS',
        'title' => 'Dietary Guidelines for Americans, 2025–2030',
        'year' => 2026,
        'url' => 'https://cdn.realfood.gov/DGA.pdf',
        'where' => 'Incorporate Healthy Fats (p. 3)',
        'quote' => 'In general, saturated fat consumption should not exceed 10% of total daily calories.',
        'summary' => 'المشبعة حتى 10% من السعرات اليومية.',
        'applies' => 'عامة الناس',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'who_fibre_2023' => [
        'org' => 'WHO',
        'title' => 'Carbohydrate intake for adults and children: WHO guideline (news release)',
        'year' => 2023,
        'url' => 'https://www.who.int/news/item/17-07-2023-who-updates-guidelines-on-fats-and-carbohydrates',
        'where' => 'News release, 17 July 2023',
        'quote' => 'WHO recommends that adults consume at least 400 grams of vegetables and fruits and 25 grams of naturally occurring dietary fibre per day.',
        'summary' => 'للبالغين: 25 غ ألياف طبيعية على الأقل في اليوم.',
        'applies' => 'البالغون',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'dga_2020_appendix' => [
        'org' => 'USDA/HHS',
        'title' => 'Dietary Guidelines for Americans, 2020–2025, Appendix 1: Nutritional Goals for Age-Sex Groups',
        'year' => 2020,
        'url' => 'https://www.dietaryguidelines.gov/sites/default/files/2021-03/Dietary_Guidelines_for_Americans-2020-2025.pdf',
        'where' => 'Appendix 1, Tables A1-2 and A1-3',
        'quote' => 'Fiber (g) — Source of Goal: 14g/1,000 kcal. Protein (g) — Source of Goal: RDA — 46 (females 19+), 56 (males 19+).',
        'summary' => 'الألياف 14 غ لكل 1000 سعرة. والحد الأدنى للبروتين (RDA) للبالغين: 46 غ للمرأة و56 غ للرجل في اليوم.',
        'applies' => 'البالغون من 19 سنة',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'who_free_sugars_2015' => [
        'org' => 'WHO',
        'title' => 'Guideline: Sugars intake for adults and children (news release)',
        'year' => 2015,
        'url' => 'https://www.who.int/news/item/04-03-2015-who-calls-on-countries-to-reduce-sugars-intake-among-adults-and-children',
        'where' => 'News release, 4 March 2015',
        'quote' => 'A new WHO guideline recommends adults and children reduce their daily intake of free sugars to less than 10% of their total energy intake. … Free sugars refer to monosaccharides (such as glucose, fructose) and disaccharides (such as sucrose or table sugar) added to foods and drinks by the manufacturer, cook or consumer, and sugars naturally present in honey, syrups, fruit juices and fruit juice concentrates.',
        'summary' => 'السكر الحر (المضاف، والعسل والعصير) أقل من 10% من السعرات. لا يشمل سكر الفواكه والحليب، وبياناتنا لا تفصله، فهو معلومة لا حكم.',
        'applies' => 'البالغون والأطفال، على السكر الحر لا الكلي',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => false, 'test' => true, 'specialist' => false],
    ],

    'nasem_amdr' => [
        'org' => 'NASEM (IOM)',
        'title' => 'Rethinking the Acceptable Macronutrient Distribution Range for the 21st Century: A Letter Report (2024), Table: AMDRs by Age Group (IOM 2002/2005)',
        'year' => 2024,
        'url' => 'https://www.nationalacademies.org/read/27957/chapter/5',
        'where' => 'Chapter 3, AMDR table',
        'quote' => 'Acceptable Macronutrient Distribution Ranges (AMDRs) by Age Group … (as percent of energy) … Adults: Fat 20–35 · Carbohydrate 45–65 · Protein 10–35. SOURCE: IOM, 2002/2005.',
        'summary' => 'للبالغين من السعرات: كربوهيدرات 45–65%، بروتين 10–35%، دهون 20–35%.',
        'applies' => 'البالغون',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'mifflin_1990' => [
        'org' => 'Am J Clin Nutr',
        'title' => 'Mifflin MD, St Jeor ST, et al. A new predictive equation for resting energy expenditure in healthy individuals (PMID 2305711)',
        'year' => 1990,
        'url' => 'https://pubmed.ncbi.nlm.nih.gov/2305711/',
        'where' => 'Abstract (copied by the project owner from PubMed)',
        'quote' => 'REE (males) = 10 x weight (kg) + 6.25 x height (cm) - 5 x age (y) + 5; REE (females) = 10 x weight (kg) + 6.25 x height (cm) - 5 x age (y) - 161.',
        'summary' => 'معادلة الطاقة في الراحة للرجال والنساء، من 498 شخصاً أصحاء بأوزان طبيعية وزائدة، بين 19 و78 سنة.',
        'applies' => 'البالغون الأصحاء (19–78 سنة)',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'fao_energy_2004' => [
        'org' => 'FAO/WHO/UNU',
        'title' => 'Human energy requirements: Report of a Joint FAO/WHO/UNU Expert Consultation (2001), FAO Food and Nutrition Technical Report Series 1',
        'year' => 2004,
        'url' => 'https://www.fao.org/4/y5686e/y5686e07.htm',
        'where' => 'Chapter 5: Tables 5.1 and 5.3, and the lifestyles described under them',
        'quote' => 'Table 5.1 … Sedentary or light activity lifestyle … Sitting (office work, selling produce, tending shop) 8 1.5 12.0 … Light leisure activities (watching TV, chatting) 2 1.4 2.8 Total 24 36.7 36.7/24 = 1.53. Active or moderately active lifestyle … Standing, carrying light loads (waiting on tables, arranging merchandise) 8 2.2 17.6 … Low intensity aerobic exercise 1 4.2 4.2 Light leisure activities (watching TV, chatting) 3 1.4 4.2 Total 24 42.2 42.2/24 = 1.76. … Sedentary or light activity lifestyles. These people have occupations that do not demand much physical effort, are not required to walk long distances, generally use motor vehicles for transportation, do not exercise or participate in sports regularly … the daily performance of one hour … of moderate to vigorous exercise, such as jogging/running, cycling, aerobic dancing or various sports activities, can raise a person’s average PAL from 1.55 (corresponding to the sedentary category) to 1.75 (the moderately active category). Other examples of moderately active lifestyles are associated with occupations such as masons and construction workers … Table 5.3: Sedentary or light activity lifestyle 1.40-1.69; Active or moderately active lifestyle 1.70-1.99; Vigorous or vigorously active lifestyle 2.00-2.40. … The PAL values that can be sustained for a long period of time by free-living adult populations range from about 1.40 to 2.40.',
        'summary' => 'معامل النهار بلا تمرين: موظف المكتب الذي لا يتمرّن 1.53، والنادل الواقف 1.64 بعد حذف ساعة التمرين من مثاله (1.76)، والبنّاؤون بين 1.70 و1.99. ساعة تمرين يومياً ترفع المعامل نحو 0.20. ما يعيشه البالغون بين 1.40 و2.40.',
        'applies' => 'البالغون الأصحاء',
        'read_at' => '2026-11-18',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'aha_obesity_2013' => [
        'org' => 'AHA/ACC/TOS',
        'title' => '2013 AHA/ACC/TOS Guideline for the Management of Overweight and Obesity in Adults (Jensen et al., Circulation 2014;129:S102–S138)',
        'year' => 2013,
        'url' => 'https://pmc.ncbi.nlm.nih.gov/articles/PMC5819889/',
        'where' => 'Recommendation 3a (Grade A, Strong)',
        'quote' => 'Prescribe a diet to achieve reduced calorie intake for obese or overweight individuals who would benefit from weight loss … Prescribe 1200–1500 kcal/d for women and 1500–1800 kcal/d for men (kilocalorie levels are usually adjusted for the individual’s body weight); Prescribe a 500-kcal/d or 750-kcal/d energy deficit …',
        'summary' => 'لمن وزنه زائد أو عنده سمنة: عجز 500 أو 750 سعرة في اليوم، أو حمية 1200–1500 للمرأة و1500–1800 للرجل.',
        'applies' => 'البالغون بوزن زائد أو سمنة (مؤشر الكتلة 25 فأكثر)',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'who_bmi' => [
        'org' => 'WHO',
        'title' => 'Obesity and overweight (fact sheet); Global Health Observatory: Body mass index',
        'year' => 2024,
        'url' => 'https://www.who.int/news-room/fact-sheets/detail/obesity-and-overweight',
        'where' => 'Definition of overweight and obesity; GHO indicators',
        'quote' => 'Adults: overweight is a BMI greater than or equal to 25; and obesity is a BMI greater than or equal to 30. (GHO: Underweight among adults, BMI < 18.5.)',
        'summary' => 'للبالغين: نقص الوزن أقل من 18.5، زيادة الوزن من 25، السمنة من 30. درجات السمنة (35، 40) لم تُقرأ بعد.',
        'applies' => 'البالغون، لا الحمل',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    /* The new calculation (Nov 2026): exercise, the deficit and the floor, protein. */

    'compendium_2024' => [
        'org' => 'Compendium of Physical Activities',
        'title' => '2024 Adult Compendium of Physical Activities (Herrmann et al., J Sport Health Sci 2024)',
        'year' => 2024,
        'url' => 'https://pacompendium.com/adult-compendium/',
        'where' => 'Unit Conversions; Walking, Running, Conditioning Exercise, Bicycling, Water Activities, Sports',
        'quote' => '1 MET = 3.5 ml/kg/min 1 MET = 1 kcal/kg/hr … 17170 3.0 Walking, 2.5 mph, firm, level surface … 17200 4.8 Walking, 3.5 to 3.9 mph, level, brisk, firm surface, walking for exercise … 12020 7.5 Jogging, general, self-selected pace … 12050 9.3 Running, 6-6.3 mph (10 min/mile) … 02054 3.5 Resistance (weight) training, multiple exercises, 8-15 reps at varied resistance … 02060 5.5 Health club exercise, general … 02050 6.0 Resistance (weight lifting – free weight, nautilus or universal-type), power lifting or body building, vigorous effort … 02048 5.0 Elliptical trainer, moderate effort … 01014 7.0 Bicycling, general … 18240 5.8 Swimming laps, freestyle, slow … 18230 9.8 Swimming laps, freestyle, fast, vigorous effort … 15610 7.0 Soccer, casual, general … 15605 9.5 Soccer, competitive … 15055 7.5 Basketball, general … 15110 5.8 Boxing, punching bag',
        'summary' => 'سعرة لكل كيلو بالساعة لكل MET، وأرقام كل رياضة برمزها: مشي عادي 3.0، مشي سريع 4.8، حديد عادي 3.5، كارديو متوسط 5.0، جري 9.3…',
        'applies' => 'البالغون',
        'read_at' => '2026-11-18',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'who_pa_2020' => [
        'org' => 'WHO',
        'title' => 'WHO guidelines on physical activity and sedentary behaviour',
        'year' => 2020,
        'url' => 'https://www.who.int/publications/i/item/9789240015128',
        'where' => 'Glossary',
        'quote' => 'The metabolic equivalent of task, or simply metabolic equivalent, is a physiological measure expressing the intensity of physical activities. One MET is the energy equivalent expended by an individual while seated at rest. … On an absolute scale, moderate-intensity refers to the physical activity that is performed between 3 and less than 6 times the intensity of rest. … vigorous-intensity refers to physical activity that is performed at 6.0 or more METS.',
        'summary' => 'الـ MET: جهد النشاط مقارنة بالجلوس مرتاحاً. المتوسط من 3 إلى أقل من 6، والعالي 6 فأكثر.',
        'applies' => 'البالغون',
        'read_at' => '2026-11-18',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'nice_obesity_ng246' => [
        'org' => 'NICE',
        'title' => 'Overweight and obesity management (NG246)',
        'year' => 2025,
        'url' => 'https://www.nice.org.uk/guidance/ng246/chapter/Physical-activity-and-diet',
        'where' => 'Recommendations 1.16.4 (committee discussion), 1.16.8 and 1.16.11',
        'quote' => 'The committee noted that the calorie deficit in the studies varied. Many used a 500 to 800 kilocalories a day deficit but it was also common to use an individual deficit for each participant, so they agreed not to specify a particular deficit. … Consider low-energy diets (800 to 1,200 kilocalories per day, also known as low-calorie diets) only as part of a multicomponent overweight and obesity management strategy with long-term support within a specialist overweight and obesity management service … Ensure that low-energy and very-low-energy diets: are nutritionally complete, last no more than 12 weeks, include ongoing clinical support and supervision …',
        'summary' => 'أغلب الدراسات استعملت عجزاً بين 500 و800 سعرة. الحمية من 800 إلى 1,200 سعرة «منخفضة الطاقة»: بإشراف خدمة متخصصة فقط، ولا تزيد عن 12 أسبوعاً.',
        'applies' => 'البالغون مع زيادة الوزن أو السمنة',
        'read_at' => '2026-11-18',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'dga_2025_protein' => [
        'org' => 'USDA/HHS',
        'title' => 'Dietary Guidelines for Americans, 2025–2030',
        'year' => 2026,
        'url' => 'https://cdn.realfood.gov/DGA.pdf',
        'where' => 'Prioritize Protein Foods at Every Meal',
        'quote' => 'Protein serving goals: 1.2–1.6 grams of protein per kilogram of body weight per day, adjusting as needed based on your individual caloric requirements.',
        'summary' => 'البروتين: من 1.2 إلى 1.6 غ لكل كغ من الوزن في اليوم.',
        'applies' => 'البالغون',
        'read_at' => '2026-11-18',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

];
