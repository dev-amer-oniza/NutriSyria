<?php

namespace App\Enums;

use App\Auth\Access;

/**
 * What someone signs up as on the public site. A plain user needs no role; a brand owner or
 * nutritionist asks for a panel role, which the super-admin grants after checking them.
 */
enum AccountType: string
{
    case Member = 'member';
    case BrandOwner = 'brand-owner';
    case Nutritionist = 'nutritionist';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'مستخدم',
            self::BrandOwner => 'صاحب مطعم',
            self::Nutritionist => 'أخصائي تغذية',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Member => 'أريد أن أعرف ما في وجبتي',
            self::BrandOwner => 'أريد عرض قيم وجباتي',
            self::Nutritionist => 'أريد مراجعة القيم واعتمادها',
        };
    }

    /** The panel role this account asks for; null for a plain user. */
    public function requestedRole(): ?string
    {
        return match ($this) {
            self::Member => null,
            self::BrandOwner => Access::BRAND_OWNER,
            self::Nutritionist => Access::NUTRITIONIST,
        };
    }

    /** Brand owners and nutritionists leave a WhatsApp number so the admin can reach them. */
    public function needsWhatsapp(): bool
    {
        return $this->requestedRole() !== null;
    }
}
