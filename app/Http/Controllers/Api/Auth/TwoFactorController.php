<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Minimal TOTP-style 2FA. Generates a base32 secret, asks the user to enter it
 * into their authenticator app (Google Authenticator, Authy), then verifies a
 * 6-digit code. This is a clean foundation; in production you'd typically
 * pair it with the PHP "pragmarx/google2fa" package for QR codes + RFC 6238
 * verification. Here we ship the schema + endpoints; the code-verification
 * helper is intentionally simple so the flow is testable.
 */
class TwoFactorController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'message' => '2FA status',
            'data'    => [
                'enabled'      => !is_null($user->two_factor_confirmed_at),
                'confirmed_at' => $user->two_factor_confirmed_at,
            ],
        ]);
    }

    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->generateBase32Secret();
        $user->two_factor_secret = Crypt::encryptString($secret);
        $user->two_factor_confirmed_at = null;
        $user->save();

        $issuer  = config('app.name', 'POS');
        $label   = rawurlencode($user->email);
        $otpauth = "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}";

        return response()->json([
            'success' => true,
            'message' => '2FA secret generated. Scan the QR or paste the secret into your authenticator app, then confirm with a 6-digit code.',
            'data'    => [
                'secret'  => $secret,            // for display once
                'otpauth' => $otpauth,           // QR content
            ],
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);
        $user = $request->user();

        if (!$user->two_factor_secret) {
            return response()->json(['success'=>false,'message'=>'Enable 2FA first.','data'=>null], 422);
        }

        $secret = Crypt::decryptString($user->two_factor_secret);
        if (!$this->verifyTotp($secret, $request->code)) {
            return response()->json(['success'=>false,'message'=>'Invalid 2FA code.','data'=>null], 422);
        }

        $codes = $this->generateRecoveryCodes(8);
        $user->two_factor_recovery_codes = Crypt::encryptString(json_encode($codes));
        $user->two_factor_confirmed_at   = now();
        $user->save();

        Audit::log('user.two_factor_enabled', $user);

        return response()->json([
            'success' => true,
            'message' => '2FA enabled. Save these recovery codes safely — you can each one once if you lose your device.',
            'data'    => ['recovery_codes' => $codes],
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);
        $user = $request->user();

        if (!\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            return response()->json(['success'=>false,'message'=>'Password is incorrect.','data'=>null], 422);
        }

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        Audit::log('user.two_factor_disabled', $user);

        return response()->json(['success'=>true,'message'=>'2FA disabled.','data'=>null]);
    }

    /** RFC-6238-compatible TOTP verify (30s step, ±1 window). */
    private function verifyTotp(string $secret, string $code): bool
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) return false;
        $timeStep = floor(time() / 30);
        for ($i = -1; $i <= 1; $i++) {
            if ($this->totpAt($secret, (int)$timeStep + $i) === $code) return true;
        }
        return false;
    }

    private function totpAt(string $secret, int $timeStep): string
    {
        $key = $this->base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeStep);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $part = substr($hash, $offset, 4);
        $value = unpack('N', $part)[1] & 0x7FFFFFFF;
        return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $b32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = rtrim(strtoupper($b32), '=');
        $binary = '';
        foreach (str_split($b32) as $ch) {
            $pos = strpos($alphabet, $ch);
            if ($pos === false) continue;
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) $bytes .= chr(bindec($byte));
        }
        return $bytes;
    }

    private function generateBase32Secret(int $length = 32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    private function generateRecoveryCodes(int $count): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(5).'-'.Str::random(5));
        }
        return $codes;
    }
}
