<?php

namespace App\Enums;

/** Allergies a person can declare; the same list will tag foods. A meal with one never suits them. */
enum Allergen: string
{
    case Gluten = 'gluten';
    case Lactose = 'lactose';
    case Sesame = 'sesame';
    case TreeNuts = 'tree_nuts';
    case Peanuts = 'peanuts';
    case Eggs = 'eggs';
    case Fish = 'fish';
    case Soy = 'soy';

    public function label(): string
    {
        return match ($this) {
            self::Gluten => 'الغلوتين',
            self::Lactose => 'اللاكتوز',
            self::Sesame => 'السمسم',
            self::TreeNuts => 'المكسرات',
            self::Peanuts => 'الفول السوداني',
            self::Eggs => 'البيض',
            self::Fish => 'السمك',
            self::Soy => 'الصويا',
        };
    }
}
