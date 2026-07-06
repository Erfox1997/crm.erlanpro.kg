<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WappiOutboundMediaController extends Controller
{
    public function show(string $filename): BinaryFileResponse
    {
        $filename = basename($filename);

        if (! preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            abort(404);
        }

        $path = storage_path('app/public/messenger/outbound/'.$filename);

        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => $this->mimeTypeFor($filename),
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    protected function mimeTypeFor(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'ogg', 'opus' => 'audio/ogg',
            'mp3', 'mpeg' => 'audio/mpeg',
            'm4a', 'mp4' => 'audio/mp4',
            'webm' => 'audio/webm',
            'wav' => 'audio/wav',
            'aac' => 'audio/aac',
            default => 'application/octet-stream',
        };
    }
}
