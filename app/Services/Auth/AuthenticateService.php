<?php

namespace App\Services\Auth;


use App\Models\OtpCode;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Str;



class AuthenticateService
{
    public function registerAsCustomer(array $data)
    {
        // Create user with hashed password
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'phone'    => $data['phone']??null,
        ]);

        // assigni user role
        $user->assignRole('customer');


        return $user;

    }

    public function login(array $credentials)
    {

        $user = User::where('email', $credentials['email'])->first();

        // Verify credentials and account status
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['not correct credentials.'],
            ]);
        }
        if (!Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['not correct credentials.'],
            ]);
        }

        // Generate new token with expiration
        $token = $this->createTokenWithExpiration($user);

        return [$user, $token];
    }

    public function logout(Request $request)
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();
    }

    public function refreshToke(Request $request)
    {
        //gate the current token
        $user = $request->user();

        if (!$user) {
            return response()->json(["message" => 'Unauthenticated'], 401);
        }

        //delete all tokens related with the current user
        $user()->currentAccessToken()->delete();

        //create a new token
        $newToken = $user->createToken(
            name: 'auth_token',
            expiresAt: now()->addHours(2000),
        )->plainTextToken;
        return [$newToken];
    }


    public function resetPassword(array $data)
    {
        $user = User::where('email', $data['email'])->firstOrFail();

        if(!$user->hasVerifiedEmail()){
            return null ;
        }

        $user->update([
            'password' => Hash::make($data['new_password']),
        ]);

        $user->notify(new PasswordChangedNotification());

        return $user;
    }

    /**
     * Generate token with expiration time.
     */
    public function createTokenWithExpiration(User $user): string
    {
        $token = $user->createToken('auth_token', ['*']);

        // Save expiration manually
        $token->accessToken->update([
            'expires_at' => Carbon::now()->addHours(2000) // 5 hours token validity
        ]);

        return $token->plainTextToken;
    }

    public function setNewPhoneNumber($data)
    {
        $user = User::where('id',auth()->id())->first();
        $user->update([
            'phone' => $data['phone'],
        ]);
        return $user->phone ;
    }
}
