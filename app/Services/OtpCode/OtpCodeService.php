<?php

namespace App\Services\OtpCode;
use App\Models\OtpCode;
use App\Models\User;
use App\Notifications\SendOtpNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use App\Exceptions\OtpSendFailedException;
use Throwable;


class OtpCodeService
{
    public function sendOtp(array $data)
    {
        try {
            $email = $data['email'];


            $otpCode = random_int(100000, 999999);
            $expiresAt = now()->addMinutes(10);

            OtpCode::updateOrCreate(
                [
                    'email' => $email,
                    'purpose' => 'verfiy' ,
                ],
                [
                    'otp_hash' => Hash::make($otpCode),
                    'expires_at' => $expiresAt,
                ]
            );

            Notification::route('mail', $email)
                ->notify(new SendOtpNotification($otpCode));

        } catch (Throwable $e) {
            throw new OtpSendFailedException();
        }
    }

    public function verifyOtp(array $data)
    {
        $email = $data['email'];
        $code = $data['otp_code'];


        $otp = OtpCode::where('email', $email)
            ->where('expires_at', '>', now())
            ->first();


        if (!$otp || !Hash::check($code, $otp->otp_hash)) {
            return null;
        }


        $otp->delete();

        $user = User::where('email', $email)->first();

        //verfiy user email
        $user->update(['email_verified_at' => now()]);

        return $user;

    }

}
