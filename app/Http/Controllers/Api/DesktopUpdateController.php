<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DesktopUpdateController extends Controller
{
    public function show(): JsonResponse
    {
        $manifest = $this->manifest();

        if ($manifest === null) {
            return response()->json([
                'version' => (string) config('deployment.version', '0.1.0'),
                'notes' => null,
            ]);
        }

        return response()->json([
            'version' => $manifest['version'],
            'notes' => $manifest['notes'],
            'sha256' => $manifest['sha256'],
            'url' => url('/api/updates/desktop/package'),
        ]);
    }

    public function package(): BinaryFileResponse
    {
        $path = (string) config('deployment.updates.package_path');
        abort_unless(is_file($path), 404);

        return response()->download($path, 'kospal-desktop.zip');
    }

    /**
     * @return array{version: string, notes: string|null, sha256: string}|null
     */
    private function manifest(): ?array
    {
        $path = (string) config('deployment.updates.manifest_path');

        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) File::get($path), true);

        if (! is_array($decoded) || ! is_string($decoded['version'] ?? null) || ! is_string($decoded['sha256'] ?? null)) {
            return null;
        }

        return [
            'version' => $decoded['version'],
            'notes' => is_string($decoded['notes'] ?? null) ? $decoded['notes'] : null,
            'sha256' => $decoded['sha256'],
        ];
    }
}
