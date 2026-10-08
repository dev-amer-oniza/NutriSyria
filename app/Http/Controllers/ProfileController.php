<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function changePassword(Request $request)
    {
        $validate = $request->validate([
            'password' => [
                'required',
                'string',
                'min:8', // Minimum length
                //'confirmed',
                /*
                'regex:/[a-z]/', // Must contain at least one lowercase letter
                'regex:/[A-Z]/', // Must contain at least one uppercase letter
                'regex:/[0-9]/', // Must contain at least one number
                'regex:/[@$!%*#?&]/', // Must contain a special character
                */
            ],
        ]);
        $user = User::where('id',auth()->id())->firstOrFail();
        $user->update([
            'password' => Hash::make($validate['password']),
        ]);
        return self::success('password updated successfully');
    }

    public function changeName(Request $request){
        $validate =$request->validate([
            'name' => 'required|string|max:255',
        ]);
        $user = User::where('id',auth()->id())->firstOrFail();
        $user->update([
            'name' => $validate['name'],
        ]);
        return self::success('your name updated successfully');
    }
    
}