<?php

namespace App\Http\Controllers;

use App\Support\Themes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ThemeController extends Controller
{
    public function set(Request $request)
    {
        $request->validate(['theme' => 'required|string|max:30']);
        $user  = Auth::user();
        $theme = $request->theme;

        if ($theme === 'default') {
            $user->forceFill(['theme' => null])->save();
        } elseif ($theme === 'custom' && $user->theme_image) {
            $user->forceFill(['theme' => 'custom'])->save();
        } elseif (isset(Themes::PRESETS[$theme])) {
            $user->forceFill(['theme' => $theme])->save();
        } else {
            return response()->json(['ok' => false], 422);
        }

        return response()->json(['ok' => true, 'css' => Themes::css($user->fresh())]);
    }

    public function custom(Request $request)
    {
        $request->validate(['image' => 'required|image|max:8192']);
        $user = Auth::user();
        $webp = Themes::toWebp($request->file('image')->get());
        if (!$webp) return response()->json(['ok' => false, 'message' => 'Could not read that picture.'], 422);

        $path = 'themes/' . $user->id . '-' . substr(md5($webp), 0, 8) . '.webp';
        Storage::disk('public')->put($path, $webp);
        if ($user->theme_image && $user->theme_image !== $path) Storage::disk('public')->delete($user->theme_image);
        $user->forceFill(['theme' => 'custom', 'theme_image' => $path])->save();

        return response()->json(['ok' => true, 'css' => Themes::css($user->fresh())]);
    }
}
