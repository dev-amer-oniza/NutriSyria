<?php

namespace App\Auth;

/**
 * The panel's roles and permissions in one place.
 *
 * Roles and their default permissions are written to the database by a migration; the
 * super-admin can then change which permissions a role has from the panel. What a permission
 * can never be changed into: "manage own" brands and products is always limited to the brands
 * the user is linked to (brand_user). That ownership check lives in the policies, not here.
 */
final class Access
{
    public const SUPER_ADMIN = 'super-admin';
    public const BRAND_OWNER = 'brand-owner';
    public const NUTRITIONIST = 'nutritionist';

    /** Only these roles may sign in to the admin panel. */
    public const PANEL_ROLES = [self::SUPER_ADMIN, self::BRAND_OWNER, self::NUTRITIONIST];

    // Food composition database (foods, records, portions, data sources, comparison).
    public const FOODS_VIEW = 'foods.view';
    public const FOODS_MANAGE = 'foods.manage';

    // Imports: see batches, approve/reject their records, upload and match new data.
    public const IMPORTS_VIEW = 'imports.view';
    public const IMPORTS_REVIEW = 'imports.review';
    public const IMPORTS_UPLOAD = 'imports.upload';

    // Brands and their products/recipes.
    public const BRANDS_MANAGE_ALL = 'brands.manage-all';
    public const BRANDS_MANAGE_OWN = 'brands.manage-own';
    public const PRODUCTS_VIEW_ALL = 'products.view-all';
    public const NUTRITION_APPROVE = 'nutrition.approve';

    // Accounts.
    public const USERS_MANAGE = 'users.manage';

    /** @return array<string, string> permission => Arabic label shown in the panel */
    public static function permissions(): array
    {
        return [
            self::FOODS_VIEW => 'عرض قاعدة الأغذية',
            self::FOODS_MANAGE => 'تعديل قاعدة الأغذية ومصادر البيانات',
            self::IMPORTS_VIEW => 'عرض دفعات الاستيراد',
            self::IMPORTS_REVIEW => 'اعتماد ورفض السجلات المستوردة',
            self::IMPORTS_UPLOAD => 'رفع الدفعات وأدوات المطابقة وحذف الدفعات',
            self::BRANDS_MANAGE_ALL => 'إدارة كل البراندات',
            self::BRANDS_MANAGE_OWN => 'إدارة براندات المستخدم ومنتجاتها ووصفاتها',
            self::PRODUCTS_VIEW_ALL => 'عرض منتجات كل البراندات ووصفاتها',
            self::NUTRITION_APPROVE => 'اعتماد القيم الغذائية الرسمية للمنتجات',
            self::USERS_MANAGE => 'إدارة المستخدمين والأدوار والصلاحيات',
        ];
    }

    /** @return array<string, string> role => Arabic label */
    public static function roles(): array
    {
        return [
            self::SUPER_ADMIN => 'مدير النظام',
            self::BRAND_OWNER => 'صاحب براند',
            self::NUTRITIONIST => 'أخصائي تغذية',
        ];
    }

    /**
     * Starting permissions per role. The super-admin needs none: Gate::before lets it do
     * everything, so it can never lock itself out by editing its own permissions.
     *
     * @return array<string, list<string>>
     */
    public static function defaultPermissions(): array
    {
        return [
            self::SUPER_ADMIN => [],
            self::BRAND_OWNER => [self::BRANDS_MANAGE_OWN],
            self::NUTRITIONIST => [self::IMPORTS_VIEW, self::IMPORTS_REVIEW, self::PRODUCTS_VIEW_ALL, self::NUTRITION_APPROVE],
        ];
    }
}
