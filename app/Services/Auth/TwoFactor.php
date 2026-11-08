<?php

namespace App\Services\Auth;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactor
{
    public function __construct(private Google2FA $engine) {}

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    public function qrSvg(User $user, string $secret, string $issuer): string
    {
        $url = $this->engine->getQRCodeUrl($issuer, $user->email ?: $user->mobile ?: $user->name, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    /**
     * Verify a TOTP code; each code is accepted only once per user.
     */
    public function verify(string $secret, string $code, ?int $userId = null): bool
    {
        $code = preg_replace('/\s+/', '', $code);

        if (! preg_match('/^\d{6}$/', $code) || ! $this->engine->verifyKey($secret, $code, 1)) {
            return false;
        }

        if ($userId !== null) {
            return Cache::add('2fa:used:'.$userId.':'.$code, true, 120);
        }

        return true;
    }

    /**
     * @return Collection<int, string>
     */
    public function recoveryCodes(int $count = 8): Collection
    {
        return collect(range(1, $count))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)));
    }

    public function useRecoveryCode(User $user, string $code): bool
    {
        $code = Str::lower(trim($code));
        $codes = collect($user->two_factor_recovery_codes ?? []);

        if ($code === '' || ! $codes->contains($code)) {
            return false;
        }

        $user->forceFill(['two_factor_recovery_codes' => $codes->reject(fn ($c) => $c === $code)->values()->all()])->save();

        return true;
    }
}
