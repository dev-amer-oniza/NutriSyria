<?php

namespace App\Enums;

/**
 * Conditions a person can declare. Each brings a diet template and/or limits of its own
 * (config/nutrition_criteria.php 'conditions').
 */
enum HealthCondition: string
{
    // Diabetes and metabolism
    case Diabetes = 'diabetes';
    case GestationalDiabetes = 'gestational_diabetes';
    case InsulinResistance = 'insulin_resistance';
    case HighCholesterol = 'high_cholesterol';
    // Heart and kidneys
    case Hypertension = 'hypertension';
    case HeartDisease = 'heart_disease';
    case HeartFailure = 'heart_failure';
    case Kidney = 'kidney';
    case Dialysis = 'dialysis';
    // Digestion and fat
    case Reflux = 'reflux';
    case GallbladderRemoved = 'gallbladder_removed';
    case FatIntolerance = 'fat_intolerance';
    case Pancreatitis = 'pancreatitis';
    case Celiac = 'celiac';
    // The liver (step 3: what each reference says)
    case FattyLiver = 'fatty_liver';
    case HepatitisAcute = 'hepatitis_acute';
    case HepatitisChronic = 'hepatitis_chronic';
    case Cirrhosis = 'cirrhosis';
    case Jaundice = 'jaundice';
    /**
     * The old "الكبد الوبائي أو التليّف", which mixed a passing hepatitis A with cirrhosis. No
     * longer offered: the people who chose it are asked to pick the right one (decision 8a),
     * and judged on their general needs until they do.
     */
    case LiverCirrhosis = 'liver_cirrhosis';
    // Other
    case Gout = 'gout';
    case Anemia = 'anemia';
    case Osteoporosis = 'osteoporosis';
    case PregnantOrBreastfeeding = 'pregnant_or_breastfeeding';
    case Menopause = 'menopause';

    /** Group key => heading, in the order shown. */
    public const GROUPS = [
        'metabolic' => 'السكري والتمثيل الغذائي',
        'heart_kidney' => 'القلب والكلى',
        'digestion' => 'الهضم والدسم',
        'liver' => 'الكبد',
        'other' => 'حالات أخرى',
    ];

    public function label(): string
    {
        return match ($this) {
            self::Diabetes => 'السكري',
            self::GestationalDiabetes => 'سكري الحمل',
            self::InsulinResistance => 'مقاومة الإنسولين أو تكيّس المبايض',
            self::HighCholesterol => 'ارتفاع الكولسترول والدهون',
            self::Hypertension => 'ارتفاع ضغط الدم',
            self::HeartDisease => 'أمراض القلب',
            self::HeartFailure => 'قصور القلب',
            self::Kidney => 'أمراض الكلى (قبل الغسيل)',
            self::Dialysis => 'غسيل الكلى',
            self::Reflux => 'الارتجاع المريئي (الحرقة)',
            self::GallbladderRemoved => 'استئصال المرارة',
            self::FatIntolerance => 'عدم تحمّل الدسم',
            self::Pancreatitis => 'التهاب البنكرياس',
            self::Celiac => 'السيلياك (حساسية الغلوتين)',
            self::FattyLiver => 'الكبد الدهني',
            self::HepatitisAcute => 'التهاب كبد فيروسي حاد (A أو E)',
            self::HepatitisChronic => 'التهاب كبد فيروسي مزمن (B أو C)',
            self::Cirrhosis => 'تليّف الكبد',
            self::LiverCirrhosis => 'الكبد الوبائي أو التليّف',
            self::Jaundice => 'يرقان (السبب غير معروف بعد)',
            self::Gout => 'النقرس',
            self::Anemia => 'فقر الدم',
            self::Osteoporosis => 'هشاشة العظام',
            self::PregnantOrBreastfeeding => 'حامل أو مرضعة',
            self::Menopause => 'سن الأمل',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::Diabetes, self::GestationalDiabetes, self::InsulinResistance, self::HighCholesterol => 'metabolic',
            self::Hypertension, self::HeartDisease, self::HeartFailure, self::Kidney, self::Dialysis => 'heart_kidney',
            self::Reflux, self::GallbladderRemoved, self::FatIntolerance, self::Pancreatitis, self::Celiac => 'digestion',
            self::FattyLiver, self::HepatitisAcute, self::HepatitisChronic, self::Cirrhosis, self::Jaundice,
            self::LiverCirrhosis => 'liver',
            default => 'other',
        };
    }

    /**
     * A line under the choice. Jaundice is a sign rather than a diagnosis, with several causes
     * each with its own diet (the owner's decision a). The others say how the stages of a
     * recovery are told: by adding "عدم تحمّل الدسم" while fat still troubles, and removing it
     * after (the owner's decision c, instead of a condition per stage).
     */
    public function hint(): ?string
    {
        return match ($this) {
            self::Jaundice => 'اليرقان علامة لا مرض، ولكل سبب غذاؤه. لا نطبّق حدوداً خاصة حتى يُعرف السبب: راجع طبيبك ثم اختر الحالة الأدق.',
            self::HepatitisAcute => 'مرض مؤقت. لا تطلب المراجع نظاماً غذائياً خاصاً: أكل صحي، وسوائل كافية، وتجنّب الكحول والأدوية غير الضرورية. أزلها حين تتعافى، ونذكّرك كل أربعة أسابيع.',
            self::HepatitisChronic => 'أكل صحي ووزن صحي وتجنّب الكحول. إن وصل إلى تليّف، اختر «تليّف الكبد».',
            self::Cirrhosis => 'بروتين لا يقل عن 1.2 غ لكل كغ من وزنك، وثلاث إلى خمس وجبات في اليوم مع وجبة خفيفة قبل النوم. يحتاج متابعة أخصائي.',
            self::FattyLiver => 'نمط البحر المتوسط، وتجنّب المشروبات المحلّاة، وإنقاص الوزن إن كان أعلى من الطبيعي.',
            self::GallbladderRemoved => 'في الأسابيع الأولى بعد العملية، أو إن كان الدسم لا يزال يتعبك، اختر معها «عدم تحمّل الدسم»، وأزلها حين ترتاح.',
            self::Reflux => 'إن كان الطعام الدسم يزيد الحرقة عندك، اختر معها «عدم تحمّل الدسم».',
            self::FatIntolerance => 'حالة تمرّ غالباً: أزلها حين تتحمّل الدسم براحة. نذكّرك بها كل أربعة أسابيع.',
            default => null,
        };
    }

    /** Passing states: the person is asked every few weeks whether they still hold. */
    public function isTemporary(): bool
    {
        return in_array($this, [self::FatIntolerance, self::Jaundice, self::HepatitisAcute], true);
    }

    /** No longer offered (LiverCirrhosis): kept so those who chose it can be asked to pick again. */
    public function isLegacy(): bool
    {
        return $this === self::LiverCirrhosis;
    }

    /** @return list<self> what a person or nutritionist may choose */
    public static function offered(): array
    {
        return array_values(array_filter(self::cases(), fn (self $c) => ! $c->isLegacy()));
    }

    /**
     * A digestion that may suffer from a lot of legume fibre at once (gas, bloating): such a
     * meal gets a gentle note, never a lower level (the owner's decision 2a).
     */
    public function feelsLegumeFibre(): bool
    {
        return in_array($this, [self::Reflux, self::GallbladderRemoved, self::FatIntolerance, self::Pancreatitis, self::Jaundice], true);
    }

    /** Pregnancy, gestational diabetes and menopause are offered to women only. */
    public function appliesTo(?Sex $sex): bool
    {
        $womenOnly = [self::PregnantOrBreastfeeding, self::GestationalDiabetes, self::Menopause];

        return ! in_array($this, $womenOnly, true) || $sex === Sex::Female;
    }

    /** @return array<string, array{label: string, conditions: list<self>}> the groups, with what applies to this person */
    public static function grouped(?Sex $sex): array
    {
        $groups = [];
        foreach (self::GROUPS as $key => $label) {
            $groups[$key] = [
                'label' => $label,
                'conditions' => array_values(array_filter(self::offered(), fn (self $c) => $c->group() === $key && $c->appliesTo($sex))),
            ];
        }

        return $groups;
    }
}
