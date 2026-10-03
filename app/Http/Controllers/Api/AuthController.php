<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AuthController extends Controller
{
    private const RESET_CODE_TTL_MINUTES = 15;

    /** POST /api/v1/auth/register  (selalu membuat akun role "user") */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'], // di-hash otomatis oleh cast 'hashed'
            'role' => UserRole::User,
        ]);

        return ApiResponse::success(
            $this->authPayload($user, $data['device_name'] ?? null),
            'Registrasi berhasil.',
            201
        );
    }

    /** POST /api/v1/auth/login */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return ApiResponse::error('Email atau kata sandi salah.', 401);
        }

        if (! $user->is_active) {
            return ApiResponse::error('Akun Anda dinonaktifkan. Hubungi admin.', 403);
        }

        if ($user->isAdmin()) {
            return ApiResponse::error('Akun admin hanya dapat login melalui panel web.', 403);
        }

        return ApiResponse::success(
            $this->authPayload($user, $data['device_name'] ?? null),
            'Login berhasil.'
        );
    }

    /** POST /api/v1/auth/logout */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logout berhasil.');
    }

    /** GET /api/v1/auth/me */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('technicianProfile');

        return ApiResponse::success((new UserResource($user))->resolve());
    }

    /**
     * POST /api/v1/auth/forgot-password
     * Selalu membalas sukses agar penyerang tidak bisa menebak email mana yang terdaftar.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');

        $user = User::where('email', $email)->where('is_active', true)->first();

        if ($user) {
            $code = (string) random_int(100000, 999999);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                ['token' => Hash::make($code), 'created_at' => now()]
            );

            try {
                Mail::raw(
                    "Halo {$user->name},\n\n"
                    ."Kode reset kata sandi reaple.id Anda: {$code}\n"
                    .'Kode berlaku '.self::RESET_CODE_TTL_MINUTES." menit.\n\n"
                    ."Jika Anda tidak merasa meminta ini, abaikan email ini.",
                    fn ($message) => $message->to($user->email)->subject('Kode Reset Kata Sandi reaple.id')
                );
            } catch (Throwable $e) {
                Log::error('Gagal mengirim email reset sandi', ['email' => $email, 'error' => $e->getMessage()]);
            }
        }

        return ApiResponse::success(null, 'Jika email terdaftar, kode verifikasi telah dikirim.');
    }

    /** POST /api/v1/auth/reset-password */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $invalid = ApiResponse::error('Kode tidak valid atau sudah kedaluwarsa.', 422);

        $record = DB::table('password_reset_tokens')->where('email', $data['email'])->first();

        if (
            ! $record
            || ! $record->created_at
            || Carbon::parse($record->created_at)->addMinutes(self::RESET_CODE_TTL_MINUTES)->isPast()
            || ! Hash::check($data['code'], $record->token)
        ) {
            return $invalid;
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return $invalid;
        }

        DB::transaction(function () use ($user, $data) {
            $user->forceFill(['password' => $data['password']])->save();
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
            $user->tokens()->delete(); // paksa login ulang di semua perangkat
        });

        return ApiResponse::success(null, 'Kata sandi berhasil diubah. Silakan login.');
    }

    /** Buat token Sanctum dan susun payload respons login/register. */
    private function authPayload(User $user, ?string $deviceName): array
    {
        $deviceName = $deviceName ?: 'mobile';

        // Satu token per nama perangkat, supaya tabel token tidak menumpuk.
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => (new UserResource($user->load('technicianProfile')))->resolve(),
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }
}