<?php

namespace Tests\Unit;

use App\Filament\Support\ImageUpload;
use Filament\Forms\Components\FileUpload;
use Tests\TestCase;

/** Picture fields take a phone photo: shrunk in the browser, up to 10 MB (the owner's report). */
class ImageUploadTest extends TestCase
{
    public function test_every_picture_field_is_shrunk_before_it_is_sent(): void
    {
        $field = ImageUpload::apply(FileUpload::make('img'));
        $this->assertSame(ImageUpload::MAX_KB, $field->getMaxSize());
        $this->assertSame('1600', $field->getImageResizeTargetWidth());
        $this->assertSame('1600', $field->getImageResizeTargetHeight());
        $this->assertContains('image/jpeg', $field->getAcceptedFileTypes());
    }

    public function test_the_upload_rules_take_jpeg_too(): void
    {
        $this->assertStringContainsString('jpeg', implode('|', config('livewire.temporary_file_upload.rules')));
    }
}
