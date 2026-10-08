<?php


namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use App\Helpers\TenantHelper;

class CloudinaryUploadService
{
    protected Cloudinary $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary(
            Configuration::instance([
                'cloud' => [
                    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
                    'api_key' => env('CLOUDINARY_API_KEY'),
                    'api_secret' => env('CLOUDINARY_API_SECRET'),
                ],
                'url' => ['secure' => true],
            ])
        );
    }

    public function upload(string $filePath, string $subfolder = 'general'): string
    {
        $folder = TenantHelper::cloudinaryFolder($subfolder);

        $result = $this->cloudinary->uploadApi()->upload($filePath, [
            'folder' => $folder,
            'resource_type' => 'image',
            'use_filename' => true,
            'unique_filename' => true,
        ]);

        return $result['secure_url'];
    }

    public function delete(string $url): void
    {
        // Extract public_id from the URL
        // e.g. https://res.cloudinary.com/dnpqxfirl/image/upload/v123456/tenants/42/categories/file.jpg
        // public_id = tenants/42/categories/file
        preg_match('/\/upload\/(?:v\d+\/)?(.+)\.[a-z]+$/i', $url, $matches);

        if (!empty($matches[1])) {
            $this->cloudinary->uploadApi()->destroy($matches[1]);
        }
    }
}