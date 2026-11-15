<?php

namespace App\Filament\Clusters\ImportReview\Pages;

use App\Filament\Clusters\ImportReview\ImportReviewCluster;
use App\Filament\Clusters\ImportReview\Resources\ImportBatches\ImportBatchResource;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * "إضافة أغذية": one entry point for the three ways food data comes in. Every way ends the same:
 * a batch waiting in the review queue. Each card shows only to who may open its page.
 */
class AddFoods extends Page
{
    protected string $view = 'filament.clusters.import-review.pages.add-foods';

    protected static ?string $cluster = ImportReviewCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static string|\BackedEnum|null $activeNavigationIcon = Heroicon::PlusCircle;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'إضافة أغذية';

    protected static ?string $title = 'إضافة أغذية';

    protected static ?string $slug = 'add-foods';

    public static function canAccess(): bool
    {
        return UploadImportBatch::canAccess() || SourceMatchingTools::canAccess() || UsdaSearch::canAccess();
    }

    /** The matching and upload pages are steps of this one, so it stays highlighted on them. */
    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [static::getRouteName(), SourceMatchingTools::getRouteName(), UploadImportBatch::getRouteName()];
    }

    public function getSubheading(): ?string
    {
        return 'اختر الطريقة حسب ما عندك. كل طريقة تنتهي بدفعة في طابور المراجعة، ولا يُستعمل أي رقم قبل اعتماده.';
    }

    /** @return list<array{key:string, title:string, when:string, how:list<string>, url:string, action:string, icon:string}> */
    public function getWays(): array
    {
        $ways = [];

        if (UsdaSearch::canAccess()) {
            $ways[] = [
                'key' => 'one',
                'title' => 'غذاء واحد',
                'when' => 'غذاء أو اثنان تعرف اسمهما بالإنكليزية.',
                'how' => ['ابحث في USDA مباشرة', 'شاهد الأرقام', 'اضغط «أضف للقاعدة»'],
                'url' => UsdaSearch::getUrl(),
                'action' => 'ابحث في USDA',
                'icon' => 'heroicon-o-magnifying-glass',
            ];
        }
        if (SourceMatchingTools::canAccess()) {
            $ways[] = [
                'key' => 'list',
                'title' => 'قائمة أغذية (مطابقة)',
                'when' => 'قائمة أغذية سورية تريد مطابقتها مع USDA دفعة واحدة.',
                'how' => ['ارفع ملف الأغذية المطلوبة', 'راجع المسودة وضع yes على الاختيار الصحيح', 'ارفع المسودة لتُبنى الدفعة'],
                'url' => SourceMatchingTools::getUrl(),
                'action' => 'ابدأ المطابقة',
                'icon' => 'heroicon-o-arrows-right-left',
            ];
        }
        if (UploadImportBatch::canAccess()) {
            $ways[] = [
                'key' => 'files',
                'title' => 'ملفات جاهزة',
                'when' => 'عندك ملفات السجلات والقيم (والحصص) جاهزة، من أداة المطابقة على جهازك أو من مصدر محلي.',
                'how' => ['ارفع الملفات', 'افحصها', 'احفظ وافتح المراجعة'],
                'url' => UploadImportBatch::getUrl(),
                'action' => 'ارفع الملفات',
                'icon' => 'heroicon-o-arrow-up-tray',
            ];
        }

        return $ways;
    }

    public function getBatchesUrl(): string
    {
        return ImportBatchResource::getUrl('index');
    }
}
