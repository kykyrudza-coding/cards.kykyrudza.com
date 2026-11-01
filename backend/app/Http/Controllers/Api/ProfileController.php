<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserAvatar;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    private const int MAX_AVATAR_BYTES = 262144;

    private const int MIN_AVATAR_SIDE = 32;

    private const int MAX_AVATAR_SIDE = 1024;

    /** @var array<string, string> */
    private const array AVATAR_MIMES = ['image/jpeg' => 'jpeg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:24', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($validated);

        return new UserResource($user);
    }

    /**
     * Accepts a (client-cropped) square image as a base64 data URL. The bytes
     * are verified from their real content, never from the claimed type.
     */
    public function updateAvatar(Request $request)
    {
        $validated = $request->validate(['image' => ['required', 'string']]);

        if (! preg_match('#^data:image/[a-z+.-]+;base64,(.+)$#is', $validated['image'], $matches)) {
            throw ValidationException::withMessages(['image' => ['The image must be a base64 data URL.']]);
        }

        $bytes = base64_decode($matches[1], true);
        if ($bytes === false || $bytes === '') {
            throw ValidationException::withMessages(['image' => ['The image data is invalid.']]);
        }
        if (strlen($bytes) > self::MAX_AVATAR_BYTES) {
            throw ValidationException::withMessages(['image' => ['The image is too large (max 256 KB).']]);
        }

        $info = @getimagesizefromstring($bytes);
        $mime = $info['mime'] ?? null;
        if (! $info || ! isset(self::AVATAR_MIMES[$mime])) {
            throw ValidationException::withMessages(['image' => ['Use a JPEG, PNG or WebP image.']]);
        }
        [$width, $height] = $info;
        if (min($width, $height) < self::MIN_AVATAR_SIDE || max($width, $height) > self::MAX_AVATAR_SIDE) {
            throw ValidationException::withMessages(['image' => ['The image must be between 32 and 1024 pixels.']]);
        }

        $user = $request->user();
        $avatar = UserAvatar::updateOrCreate(
            ['user_id' => $user->id],
            ['mime' => $mime, 'data' => base64_encode($bytes)],
        );

        // The version query makes each upload a fresh, cacheable URL.
        $user->update(['avatar' => "/api/users/{$user->id}/avatar?v=".$avatar->updated_at->timestamp]);

        return new UserResource($user);
    }

    public function destroyAvatar(Request $request)
    {
        $user = $request->user();

        UserAvatar::where('user_id', $user->id)->delete();
        $user->update(['avatar' => null]);

        return new UserResource($user);
    }

    public function showAvatar(User $user)
    {
        $avatar = UserAvatar::find($user->id);
        abort_if($avatar === null, 404);

        return response(base64_decode($avatar->data), 200, [
            'Content-Type' => $avatar->mime,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
