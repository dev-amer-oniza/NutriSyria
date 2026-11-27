<?php

namespace App\Auth;

use Illuminate\Support\Facades\DB;

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

    /** Approves products' official values and reviews imported foods (the owner's decision). */
    public const REVIEWER = 'reviewer';

    /**
     * The roles the code itself relies on: they cannot be deleted, and their code name never
     * changes. Any other role is made from the panel (RoleResource) out of the same permissions.
     */
    public const PANEL_ROLES = [self::SUPER_ADMIN, self::BRAND_OWNER, self::NUTRITIONIST, self::REVIEWER];

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

    // Consultations: a public profile on the site and clients who book (nutritionists).
    public const CONSULTATIONS_PROVIDE = 'consultations.provide';

    // The platform's own dishes ("المطبخ السوري"): categories, dishes, analysis and approval,
    // its page's logo and text, and the dishes people asked for.
    public const KITCHEN_MANAGE = 'kitchen.manage';

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
            self::CONSULTATIONS_PROVIDE => 'بروفايل عام في الموقع واستقبال المراجعين',
            self::KITCHEN_MANAGE => 'إدارة المطبخ السوري',
            self::USERS_MANAGE => 'إدارة المستخدمين والأدوار والصلاحيات',
        ];
    }

    /** @return array<string, string> what each permission opens, for the permissions page */
    public static function descriptions(): array
    {
        return [
            self::FOODS_VIEW => 'يفتح «قاعدة الأغذية» للقراءة: الأغذية وسجلاتها وقيمها وحصصها.',
            self::FOODS_MANAGE => 'يضيف ويعدّل الأغذية والسجلات والحصص ومصادر البيانات.',
            self::IMPORTS_VIEW => 'يفتح «دفعات الاستيراد» وسجلاتها للقراءة.',
            self::IMPORTS_REVIEW => 'يعتمد أو يرفض سجلات الدفعات المستوردة.',
            self::IMPORTS_UPLOAD => 'يرفع دفعات جديدة ويستعمل أدوات المطابقة ويحذف الدفعات.',
            self::BRANDS_MANAGE_ALL => 'يدير كل المطاعم والبراندات وأصحابها.',
            self::BRANDS_MANAGE_OWN => 'يدير مطاعمه المربوطة بحسابه فقط، ومنتجاتها ووصفاتها وتحضيراتها.',
            self::PRODUCTS_VIEW_ALL => 'يشوف منتجات كل المطاعم ووصفاتها وتحليلها.',
            self::NUTRITION_APPROVE => 'يعتمد القيم الرسمية للمنتجات ويرسل ملاحظات المراجعة، ويوصله «منتج ينتظر الاعتماد».',
            self::CONSULTATIONS_PROVIDE => 'بروفايل أخصائي بالموقع، وطلبات الاستشارة ومراجعيني.',
            self::KITCHEN_MANAGE => 'قسم «المطبخ السوري» كاملاً: التصنيفات والوجبات ووصفاتها، تحليلها واعتمادها مباشرة، معلومات المطبخ، والأكلات المطلوبة.',
            self::USERS_MANAGE => 'المستخدمون والأدوار والصلاحيات. للمدير وحده.',
        ];
    }

    /** @return array<string, string> the built-in roles' Arabic names, before any edit from the panel */
    public static function builtInRoles(): array
    {
        return [
            self::SUPER_ADMIN => 'مدير النظام',
            self::BRAND_OWNER => 'صاحب مطعم أو براند',
            self::NUTRITIONIST => 'أخصائي تغذية',
            self::REVIEWER => 'مراجع الوصفات والأغذية',
        ];
    }

    /**
     * Every role by its Arabic name: the built-in ones and those made from the panel, as named
     * there (roles.label). Read once, and again after any role is saved or deleted.
     *
     * @return array<string, string> role => Arabic label
     */
    public static function roles(): array
    {
        return self::$cache['roles'] ??= (function (): array {
            try {
                $rows = DB::table(config('permission.table_names.roles', 'roles'))->orderBy('id')->get();
            } catch (\Throwable) {
                return self::builtInRoles();
            }
            $roles = [];
            foreach ($rows as $row) {
                $roles[$row->name] = ($row->label ?? null) ?: (self::builtInRoles()[$row->name] ?? $row->name);
            }

            return $roles ?: self::builtInRoles();
        })();
    }

    /**
     * Every permission by its Arabic name as edited on the permissions page (permissions.label),
     * or the name written here. Read once, and again after any change.
     *
     * @return array<string, string>
     */
    public static function permissionLabels(): array
    {
        return self::$cache['permissions'] ??= (function (): array {
            $labels = self::permissions();
            try {
                self::$cache['descriptions'] = [];
                foreach (DB::table(config('permission.table_names.permissions', 'permissions'))->get(['name', 'label', 'description']) as $row) {
                    if (isset($labels[$row->name]) && filled($row->label)) {
                        $labels[$row->name] = $row->label;
                    }
                    if (filled($row->description)) {
                        self::$cache['descriptions'][$row->name] = $row->description;
                    }
                }
            } catch (\Throwable) {
                // before the label column exists
            }

            return $labels;
        })();
    }

    /** What a permission opens, as edited on the permissions page, or as written here. */
    public static function permissionDescription(string $name): ?string
    {
        self::permissionLabels();

        return self::$cache['descriptions'][$name] ?? self::descriptions()[$name] ?? null;
    }

    /** @var array<string, array<string, string>> roles and permission names, read once */
    private static array $cache = [];

    /** A role or permission was saved or deleted: read them again (AppServiceProvider). */
    public static function forget(): void
    {
        self::$cache = [];
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
            // The nutritionist's work is consultations; reviewing recipes and imported foods is
            // the reviewer's (the owner's decisions 1a 2a).
            self::NUTRITIONIST => [self::CONSULTATIONS_PROVIDE],
            self::REVIEWER => [self::IMPORTS_VIEW, self::IMPORTS_REVIEW, self::PRODUCTS_VIEW_ALL, self::NUTRITION_APPROVE],
        ];
    }
}
