<?php

/*
| The references behind the medical rules (the owner's plan, step 1: layers 0 and 1).
|
| A rule in config/nutrition_criteria.php names its references with 'refs'. Each reference
| holds the source as it was read (organisation, title, year, url, the place in it), the
| verbatim sentence the number comes from, what it applies to, and the four gates:
|   source         the guideline itself was read, and the quote is copied from it
|   applicability  it says which condition, stage and people the number is for
|   test           a test checks the code applies the number as the reference says
|                  (tests/Unit/NutritionReferencesTest.php)
|   specialist     a specialist reviewed it (none yet: kept for the future)
| A rule is "verified" only when the first three gates pass. Never write a quote or a
| number from memory: read the reference and copy it.
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
        'where' => 'Chapter 5, Table 5.3',
        'quote' => 'Classification of lifestyles in relation to the intensity of habitual physical activity, or PAL — Sedentary or light activity lifestyle 1.40-1.69; Active or moderately active lifestyle 1.70-1.99; Vigorous or vigorously active lifestyle 2.00-2.40. … The PAL values that can be sustained for a long period of time by free-living adult populations range from about 1.40 to 2.40.',
        'summary' => 'مستوى النشاط (PAL) = الطاقة اليومية ÷ الطاقة في الراحة: خامل أو خفيف 1.40–1.69، نشط 1.70–1.99، شديد 2.00–2.40.',
        'applies' => 'البالغون',
        'read_at' => '2026-11-17',
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

    'niddk_celiac' => [
        'org' => 'NIDDK (NIH)',
        'title' => 'Treatment for Celiac Disease',
        'year' => 2026,
        'url' => 'https://www.niddk.nih.gov/health-information/digestive-diseases/celiac-disease/treatment',
        'where' => 'How do doctors treat celiac disease?',
        'quote' => 'Doctors treat celiac disease by helping people to follow a gluten-free diet. … In people who have celiac disease, consuming gluten triggers an abnormal immune system reaction that damages the small intestine.',
        'summary' => 'علاج السيلياك هو تجنّب الغلوتين، وتناوله يؤذي الأمعاء الدقيقة: قيد صارم.',
        'applies' => 'مرضى السيلياك',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    /* Digestion (step 7, batch 1): advice in words, no figures. */

    'nice_gord_cg184' => [
        'org' => 'NICE',
        'title' => 'Gastro-oesophageal reflux disease and dyspepsia in adults: investigation and management (CG184)',
        'year' => 2019,
        'url' => 'https://www.nice.org.uk/guidance/cg184/chapter/Recommendations',
        'where' => 'Recommendations 1.2.1 and 1.2.2 (Common elements of care)',
        'quote' => 'Offer simple lifestyle advice, including advice on healthy eating, weight reduction and smoking cessation. [2004] … Advise people to avoid known precipitants they associate with their dyspepsia where possible. These include smoking, alcohol, coffee, chocolate, fatty foods and being overweight. Raising the head of the bed and having a main meal well before going to bed may help some people. [2004]',
        'summary' => 'نصائح نمط حياة: تجنّب ما يثير الأعراض عندك (التدخين، الكحول، القهوة، الشوكولا، الأطعمة الدسمة، الوزن الزائد)، ووجبة رئيسية قبل النوم بوقت كافٍ. بلا أرقام.',
        'applies' => 'البالغون مع الارتجاع وعسر الهضم',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'niddk_gerd' => [
        'org' => 'NIDDK (NIH)',
        'title' => 'Eating, Diet, & Nutrition for GER & GERD',
        'year' => 2020,
        'url' => 'https://www.niddk.nih.gov/health-information/digestive-diseases/acid-reflux-ger-gerd-adults/eating-diet-nutrition',
        'where' => 'How can changes in my eating habits improve GERD symptoms? · What should I avoid eating if I have GERD symptoms?',
        'quote' => 'If you have GERD symptoms at night or when you’re lying down, eating meals at least 3 hours before you lie down or go to bed may improve symptoms. … Foods and drinks that have been commonly linked to GERD symptoms include acidic foods, such as citrus fruits and tomatoes alcoholic drinks chocolate coffee and other sources of caffeine high-fat foods mint spicy foods',
        'summary' => 'الوجبة قبل النوم بثلاث ساعات على الأقل؛ أطعمة تثير الأعراض عند بعض الناس (الحامض، الدسم، الشوكولا، القهوة، النعنع، الحار) يقرّر تجنّبها الطبيب حسب الشخص.',
        'applies' => 'البالغون مع الارتجاع',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'nice_gallstones_cg188' => [
        'org' => 'NICE',
        'title' => 'Gallstone disease: diagnosis and management (CG188)',
        'year' => 2014,
        'url' => 'https://www.nice.org.uk/guidance/cg188/chapter/Recommendations',
        'where' => 'Recommendations 1.4.1 and 1.4.2 (Patient, family member and carer information)',
        'quote' => 'Advise people to avoid food and drink that triggers their symptoms until they have their gallbladder or gallstones removed. … Advise people that they should not need to avoid food and drink that triggered their symptoms after they have their gallbladder or gallstones removed.',
        'summary' => 'قبل العملية: تجنّب ما يثير أعراضك أنت. بعد استئصال المرارة: لا يلزم تجنّب ما كان يثيرها. بلا أرقام للدهون.',
        'applies' => 'حصى المرارة، وما بعد استئصالها',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'ueg_pei_2025' => [
        'org' => 'UEG · EPC · EDS · ESPEN · ESPGHAN · ESDO · ESPCG',
        'title' => 'European guidelines for the diagnosis and treatment of pancreatic exocrine insufficiency (United European Gastroenterol J 2025;13(1):125–72)',
        'year' => 2025,
        'url' => 'https://pmc.ncbi.nlm.nih.gov/articles/PMC11866322/',
        'where' => 'Statement 3.10.1 and its comment (What dietary interventions are recommended for patients with PEI?)',
        'quote' => 'PERT should be optimized to allow as normal a diet as possible. … Comment: Patients with PEI receiving adequate PERT usually do not require dietary fat restriction. No studies have evaluated the effect of dietary fat restriction on the nutritional or clinical outcomes of patients with PEI. In malnutrition cases, an excessively restricted diet may be counterproductive.',
        'summary' => 'مع علاج الإنزيمات لا يلزم تقليل الدسم، وتقليله كثيراً قد يضر من يعاني سوء تغذية: أكل طبيعي قدر الإمكان.',
        'applies' => 'قصور البنكرياس الخارجي (ومنه التهاب البنكرياس المزمن)',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'nice_pancreatitis_ng104' => [
        'org' => 'NICE',
        'title' => 'Pancreatitis (NG104)',
        'year' => 2020,
        'url' => 'https://www.nice.org.uk/guidance/ng104/chapter/Recommendations',
        'where' => 'Recommendations 1.2.5 (acute) and 1.3.3, 1.3.5 (chronic): Nutrition support',
        'quote' => 'Ensure that people with acute pancreatitis are not made \'nil‑by‑mouth\' and do not have food withheld unless there is a clear reason for this (for example, vomiting). … Be aware that all people with chronic pancreatitis are at high risk of malabsorption, malnutrition and a deterioration in their quality of life. … Consider assessment by a dietitian for anyone diagnosed with chronic pancreatitis.',
        'summary' => 'في الحاد لا يُمنع الأكل بلا سبب؛ في المزمن خطر سوء التغذية عالٍ، وأخصائي تغذية لكل مريض.',
        'applies' => 'التهاب البنكرياس الحاد والمزمن',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'niddk_pancreatitis' => [
        'org' => 'NIDDK (NIH)',
        'title' => 'Eating, Diet, & Nutrition for Pancreatitis',
        'year' => 2017,
        'url' => 'https://www.niddk.nih.gov/health-information/digestive-diseases/pancreatitis/eating-diet-nutrition',
        'where' => 'Can what I eat help or prevent pancreatitis?',
        'quote' => 'Once you may start eating again, he or she will prescribe a healthy, low-fat eating plan that includes small, frequent meals. … If you have pancreatitis, drink plenty of fluids and limit caffeine. Health care professionals strongly advise people with pancreatitis not to drink any alcohol, even if your pancreatitis is mild.',
        'summary' => 'الطبيب يصف خطة أكل صحية بوجبات صغيرة متكررة، مع سوائل كافية وبلا كحول. لا رقم للدهون، والإرشاد الأوروبي الأحدث لا ينصح بتقليلها مع الإنزيمات.',
        'applies' => 'التهاب البنكرياس',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    /* The liver (step 3). */

    'who_hepatitis_a' => [
        'org' => 'WHO',
        'title' => 'Hepatitis A (fact sheet)',
        'year' => 2025,
        'url' => 'https://www.who.int/news-room/fact-sheets/detail/hepatitis-a',
        'where' => 'Treatment',
        'quote' => 'There is no specific antiviral treatment for hepatitis A. Instead, the management of hepatitis A focuses on supportive care to relieve symptoms and ensure adequate hydration and nutrition. … It is important to avoid unnecessary medications that can adversely affect the liver, e.g. acetaminophen, paracetamol.',
        'summary' => 'لا علاج خاص لالتهاب الكبد A: رعاية داعمة وسوائل وتغذية كافية، وتجنّب الأدوية غير الضرورية. لا حدود غذائية خاصة.',
        'applies' => 'التهاب الكبد A الحاد',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'who_hepatitis_e' => [
        'org' => 'WHO',
        'title' => 'Hepatitis E (fact sheet)',
        'year' => 2025,
        'url' => 'https://www.who.int/news-room/fact-sheets/detail/hepatitis-e',
        'where' => 'Treatment',
        'quote' => 'There is no specific antiviral treatment capable of altering the course of acute hepatitis E. As the disease is usually self-limiting, hospitalization is generally not required. It is important to avoid unnecessary medications that can adversely affect liver function …',
        'summary' => 'التهاب الكبد E الحاد يشفى غالباً وحده، بلا علاج خاص ولا حدود غذائية خاصة.',
        'applies' => 'التهاب الكبد E الحاد',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'niddk_hepatitis_a' => [
        'org' => 'NIDDK (NIH)',
        'title' => 'Hepatitis A',
        'year' => 2026,
        'url' => 'https://www.niddk.nih.gov/health-information/liver-disease/viral-hepatitis/hepatitis-a',
        'where' => 'What should I eat and drink if I have hepatitis A?',
        'quote' => 'If you have hepatitis A, choose healthy foods and drinks. Talk with your doctor about healthy eating. You should also avoid alcohol because it can cause more liver damage. … Hepatitis A is an acute infection, which means it lasts only a short time.',
        'summary' => 'أكل صحي وتجنّب الكحول. مرض حاد قصير، لا يؤدي إلى تليّف.',
        'applies' => 'التهاب الكبد A',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'niddk_hepatitis_b' => [
        'org' => 'NIDDK (NIH)',
        'title' => 'Hepatitis B',
        'year' => 2026,
        'url' => 'https://www.niddk.nih.gov/health-information/liver-disease/viral-hepatitis/hepatitis-b',
        'where' => 'What should I eat and drink if I have hepatitis B?',
        'quote' => 'If you have hepatitis B, choose healthy foods and drinks. Obesity can increase the chance of developing nonalcoholic fatty liver disease … Talk with your doctor about healthy eating and maintaining a healthy weight. You should also avoid drinking alcohol because it can cause more liver damage.',
        'summary' => 'أكل صحي ووزن صحي وتجنّب الكحول، بلا أرقام خاصة.',
        'applies' => 'التهاب الكبد B',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'niddk_hepatitis_c' => [
        'org' => 'NIDDK (NIH)',
        'title' => 'Hepatitis C',
        'year' => 2026,
        'url' => 'https://www.niddk.nih.gov/health-information/liver-disease/viral-hepatitis/hepatitis-c',
        'where' => 'What should I eat and drink if I have hepatitis C?',
        'quote' => 'If you have hepatitis C, choose healthy foods and drinks. Talk with your doctor about healthy eating. You should also avoid alcohol because it can cause more liver damage.',
        'summary' => 'أكل صحي وتجنّب الكحول، بلا أرقام خاصة.',
        'applies' => 'التهاب الكبد C',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'easl_nutrition_2019' => [
        'org' => 'EASL',
        'title' => 'EASL Clinical Practice Guidelines on nutrition in chronic liver disease (J Hepatol 2019;70:172–193)',
        'year' => 2019,
        'url' => 'https://pmc.ncbi.nlm.nih.gov/articles/PMC6657019/',
        'where' => 'Nutritional management principles in patients with liver cirrhosis (recommendations)',
        'quote' => 'Optimal daily energy intake should not be lower than the recommended 35 kcal/kg.BW/d (in non-obese individuals). (Grade II-2, B1) Optimal daily protein intake should not be lower than the recommended 1.2–1.5 g/kg.BW/d. (Grade II-2, B1) … In cirrhotic patients with ascites under sodium restriction (recommended intake of sodium ~80 mmol day = 2 g of sodium …) take care to improve diet palatability …',
        'summary' => 'في التليّف: سعرات لا تقل عن 35 لكل كغ لغير البدين، وبروتين لا يقل عن 1.2–1.5 غ لكل كغ. صوديوم 2 غ مع الاستسقاء فقط (نفس الحد العام).',
        'applies' => 'تليّف الكبد',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'espen_liver_2019' => [
        'org' => 'ESPEN',
        'title' => 'ESPEN guideline on clinical nutrition in liver disease (Clin Nutr 2019;38:485–521)',
        'year' => 2019,
        'url' => 'https://pmc.ncbi.nlm.nih.gov/articles/PMC6686849/',
        'where' => 'Recommendations 51 and 58',
        'quote' => 'Recommendation 51: In cirrhotic patients, an increased energy intake is not recommended in overweight or obese patients. … Recommendation 58: Periods of starvation should be kept short by consuming three to five meals a day and a late evening snack should be recommended to improve total body protein status.',
        'summary' => 'لا زيادة في الطاقة لصاحب الوزن الزائد أو السمنة. ثلاث إلى خمس وجبات ووجبة خفيفة قبل النوم.',
        'applies' => 'تليّف الكبد',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

    'easl_masld_2024' => [
        'org' => 'EASL-EASD-EASO',
        'title' => 'EASL-EASD-EASO Clinical Practice Guidelines on the management of metabolic dysfunction-associated steatotic liver disease (MASLD) (Obes Facts 2024)',
        'year' => 2024,
        'url' => 'https://pmc.ncbi.nlm.nih.gov/articles/PMC11299976/',
        'where' => 'Lifestyle recommendations',
        'quote' => 'In adults with MASLD, improving diet quality (similar to the Mediterranean dietary pattern), limiting the consumption of ultra-processed food (rich in sugars and saturated fat) and avoiding sugar-sweetened beverages should be recommended … (LoE 2, strong recommendation, strong consensus). … In adults with MASLD and overweight, dietary and behavioural therapy-induced weight loss should aim at a sustained reduction of ≥5% to reduce liver fat, 7-10% to improve liver inflammation, and ≥10% to improve fibrosis …',
        'summary' => 'نمط البحر المتوسط، والتقليل من المصنّعات والمشروبات المحلّاة، وإنقاص الوزن لصاحب الوزن الزائد. بلا أرقام للمشبعة أو السكريات.',
        'applies' => 'الكبد الدهني (MASLD)',
        'read_at' => '2026-11-17',
        'gates' => ['source' => true, 'applicability' => true, 'test' => true, 'specialist' => false],
    ],

];
