<?php

namespace App\Http\Controllers;

use Cloudinary\Configuration\Configuration;
use Cloudinary\Cloudinary;
use Illuminate\Http\Request;


class CloudinaryController extends Controller
{
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        $cloudinary = new Cloudinary(
            Configuration::instance([
                'cloud' => [
                    'cloud_name' => 'dnpqxfirl',
                    'api_key' => '773748585664721',
                    'api_secret' => 'TcCThmlPOPWUhyPb8QNGYT06dFY',
                ],
                'url' => [
                    'secure' => true,
                ],
            ])
        );

        try {
            $result = $cloudinary->uploadApi()->upload(
                $request->file('image')->getRealPath()
            );

            return response()->json(['url' => $result['secure_url']]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Upload failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
