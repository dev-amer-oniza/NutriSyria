<?php

namespace App\Http\Controllers;

use App\Services\CloudinaryUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Uploads one image to Cloudinary. Not routed yet; the keys come from config/cloudinary.php
 * (.env), never from code.
 */
class CloudinaryController extends Controller
{
    public function uploadImage(Request $request, CloudinaryUploadService $cloudinary): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        try {
            return response()->json(['url' => $cloudinary->upload($request->file('image')->getRealPath())]);
        } catch (\Exception $e) {
            report($e);

            return response()->json(['message' => 'Upload failed'], 500);
        }
    }
}
