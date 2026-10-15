<?php

use Illuminate\Support\Facades\Route;

// Private file downloads — loaded by routes/web.php (session) at /uploads/... and by routes/api.php (token) at /api/v1/uploads/...

// ── Serve uploaded/imported files (private storage, auth required) ──
Route::get('/uploads/{path}', function ($path, \Illuminate\Http\Request $request) {
    // New links append a decorative "/Original Name.ext" segment after the real storage
    // path (e.g. /uploads/up_xxx.jpg/Bussiness-card-01.jpg) purely so a browser's own
    // Save-As picks up the real name too — Chrome's native image viewer, for one, saves
    // by the URL's last path segment and ignores Content-Disposition when you view an
    // image as its own tab and press Ctrl+S. Existing nested storage paths (Bitrix synced
    // files under uploads/bitrix/...) resolve directly and never hit that fallback.
    [$full, $niceFromPath] = \App\Support\Uploads::resolveWithName($path);
    if (!$full) abort(404);

    // The file on disk is stored under a generated id (up_<uniqid>.ext), not the name the user
    // uploaded it as — callers pass the real name via the path above or ?name= so downloads
    // show that instead.
    $niceName = $niceFromPath ?? trim((string) $request->query('name'));
    $niceName = $niceName !== '' ? basename(str_replace(['/', '\\'], '', $niceName)) : basename($full);

    // Anything that can carry a <script> and render in a browser tab (html/svg/xml/...) must
    // download rather than execute inline — otherwise an uploaded file could run script in an
    // authenticated user's session under our own origin (stored XSS via file upload).
    // (svg is not on this list: it is served as an image with a locked-down CSP below, so it shows in <img> and can't run script)
    $renderRisk = ['html','htm','xhtml','shtml','mhtml','xml'];
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    if (in_array($ext, $renderRisk)) {
        return response()->download($full, $niceName, ['Content-Type' => 'application/octet-stream']);
    }
    // setContentDisposition() builds the required ASCII fallback itself; calling HeaderUtils
    // directly threw "filename fallback cannot contain %/non-ASCII" (a 500) for any file whose
    // real name had a % or accented/Urdu characters.
    // Stored files never change under the same URL (each upload gets a unique name), so let the browser keep them:
    // reopening a task or chat shows its images and attachments from the local cache instead of downloading them again.
    // 'private': only this browser may keep it, never a shared cache or CDN (these files are for signed-in people only).
    // Only what a browser can show properly stays inline (images, PDF, audio, video); every other type is a download,
    // so a .sql/.txt/.zip never turns into a tab of text. nosniff: the browser must not guess a more active type.
    $inline = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','svg','avif','ico','pdf','mp3','wav','ogg','m4a','aac','weba','webm','mp4','mov','m4v'], true);
    $file = response()->file($full)->setContentDisposition($inline ? 'inline' : 'attachment', $niceName)
        ->setPrivate()->setMaxAge(2592000)->setImmutable()
        ->setEtag(md5($full . '|' . filemtime($full) . '|' . filesize($full)));
    $file->headers->set('X-Content-Type-Options', 'nosniff');
    if ($ext === 'svg') {
        // an SVG can carry script: as a picture (<img>) it never runs, and opened on its own the CSP below (no script, sandboxed) stops it
        $file->headers->set('Content-Type', 'image/svg+xml');
        $file->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox");
    }
    $file->isNotModified($request);      // browser already has this exact file: answer 304 without sending it again
    return $file;
})->where('path', '.*')->name('uploads.show');
