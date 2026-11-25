<?php

namespace App\Support\Site;

use Carbon\CarbonInterface;

/** Dates in the words people use in Syria: "الأربعاء 25 تشرين التاني". */
final class SyrianDate
{
    private const DAYS = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

    private const SHORT_DAYS = ['أحد', 'اثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'];

    private const MONTHS = [1 => 'كانون التاني', 'شباط', 'آذار', 'نيسان', 'أيار', 'حزيران', 'تموز', 'آب', 'أيلول', 'تشرين الأول', 'تشرين التاني', 'كانون الأول'];

    public static function long(CarbonInterface $date): string
    {
        return self::DAYS[$date->dayOfWeek].' '.$date->day.' '.self::MONTHS[$date->month];
    }

    public static function shortDay(CarbonInterface $date): string
    {
        return self::SHORT_DAYS[$date->dayOfWeek];
    }
}
