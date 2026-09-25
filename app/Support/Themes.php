<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

/** Per-user background themes (Bitrix "Themes"): a preset gradient, or the user's own uploaded picture. */
class Themes
{
    /** key => CSS `background` value (all dark enough for the white UI text) */
    public const PRESETS = [
        'sunset'   => 'linear-gradient(160deg,#1b1147 0%,#7a2a6e 45%,#f2733a 100%)',
        'ocean'    => 'linear-gradient(160deg,#04122f 0%,#0a4a8c 55%,#17b6c9 100%)',
        'aurora'   => 'radial-gradient(ellipse at 20% 20%,#1fd6a0 0,transparent 45%),radial-gradient(ellipse at 80% 30%,#7b3ff2 0,transparent 50%),#071331',
        'forest'   => 'linear-gradient(160deg,#06251a 0%,#0f5d3a 60%,#7cc36a 100%)',
        'midnight' => 'linear-gradient(160deg,#0a0d1f 0%,#151b3d 60%,#26305f 100%)',
        'rose'     => 'linear-gradient(160deg,#2b0f2e 0%,#8a2b63 55%,#f08aa0 100%)',
        'ember'    => 'linear-gradient(160deg,#1a0d0a 0%,#7d2b12 55%,#f0a23a 100%)',
        'graphite' => 'linear-gradient(160deg,#111418 0%,#2a3038 60%,#4b5561 100%)',
        'violet'   => 'linear-gradient(135deg,#1d0f4f 0%,#5b2bd1 60%,#a86bff 100%)',
        'teal'     => 'linear-gradient(160deg,#04252b 0%,#0b6b73 60%,#3fd0c9 100%)',
        'sky'      => 'linear-gradient(160deg,#0c2b6b 0%,#2a7de1 60%,#7cc4ff 100%)',
    ];

    /** The CSS background for a user, or null for the default animated look. */
    public static function css(?User $user): ?string
    {
        if (!$user || !$user->theme) return null;
        if ($user->theme === 'custom' && $user->theme_image && Storage::disk('public')->exists($user->theme_image)) {
            return "linear-gradient(rgba(6,10,40,.35),rgba(6,10,40,.35)),url('" . asset('storage/' . $user->theme_image) . "') center/cover no-repeat fixed";
        }
        return self::PRESETS[$user->theme] ?? null;
    }

    /** <style> content that swaps the layout's built-in background for the chosen one. */
    public static function styleRules(?string $css): string
    {
        if (!$css) return '';
        return '#bg-canvas{background:' . $css . ' !important}'
             . '#bg-canvas .bg-base,#bg-canvas .bg-orb3,#bg-canvas .bg-orb4,#bg-canvas::before,#bg-canvas::after{display:none !important}';
    }

    /** Big picture → max 1920px wide WebP (keeps custom wallpapers light). */
    public static function toWebp(string $binary): ?string
    {
        if (!function_exists('imagewebp') || !($src = @imagecreatefromstring($binary))) return null;
        $w = imagesx($src); $h = imagesy($src);
        $k = min(1, 1920 / max($w, $h));
        $dst = imagecreatetruecolor((int) round($w * $k), (int) round($h * $k));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
        ob_start(); imagewebp($dst, null, 80); $out = ob_get_clean();
        imagedestroy($src); imagedestroy($dst);
        return $out ?: null;
    }
}
