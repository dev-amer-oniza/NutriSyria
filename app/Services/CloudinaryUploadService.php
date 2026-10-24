<?php


namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;

class CloudinaryUploadService
{
    protected Cloudinary $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary(
            Configuration::instance([
                'cloud' => [
                    'cloud_name' => config('cloudinary.cloud_name'),
                    'api_key' => config('cloudinary.api_key'),
                    'api_secret' => config('cloudinary.api_secret'),
                ],
                'url' => ['secure' => true],
            ])
        );
    }

    public function upload(string $filePath, string $subfolder = 'general'): string
    {
        $base = config('cloudinary.folder');
        $folder = $subfolder ? "{$base}/{$subfolder}" : $base;

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
        // e.g. https://res.cloudinary.com/dnpqxfirl/image/upload/v123456/central/categories/file.jpg
        // public_id = central/categories/file
        preg_match('/\/upload\/(?:v\d+\/)?(.+)\.[a-z]+$/i', $url, $matches);

        if (!empty($matches[1])) {
            $this->cloudinary->uploadApi()->destroy($matches[1]);
        }
    }
}