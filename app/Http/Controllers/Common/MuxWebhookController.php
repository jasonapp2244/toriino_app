<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Services\MuxService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MuxWebhookController extends Controller
{
    public function handle(Request $request, MuxService $mux): Response
    {
        $rawBody   = $request->getContent();
        $signature = $request->header('Mux-Signature', '');

        if (!$mux->verifyWebhookSignature($rawBody, $signature)) {
            return response('Invalid signature', 401);
        }

        $event = $request->json()->all();
        $type  = $event['type'] ?? null;
        $data  = $event['data'] ?? [];

        match ($type) {
            'video.upload.asset_created' => $this->handleUploadAssetCreated($data),
            'video.asset.ready'          => $this->handleAssetReady($data),
            'video.asset.errored'        => $this->handleAssetErrored($data),
            default                      => null,
        };

        return response('OK', 200);
    }

    private function handleUploadAssetCreated(array $data): void
    {
        // Link the Mux asset ID to the lesson via upload ID
        $uploadId = $data['upload_id'] ?? null;
        $assetId  = $data['id'] ?? null;

        if (!$uploadId || !$assetId) {
            \Log::warning('Mux webhook missing data', ['data' => $data]);
            return;
        }

        Lesson::where('mux_upload_id', $uploadId)->update([
            'mux_asset_id'  => $assetId,
            'video_status'  => 'processing',
        ]);
    }

    private function handleAssetReady(array $data): void
    {
        $assetId    = $data['id'] ?? null;
        $playbackId = $data['playback_ids'][0]['id'] ?? null;
        $duration   = (int) round($data['duration'] ?? 0);

        if (!$assetId) {
            \Log::warning('Mux webhook missing data', ['data' => $data]);
            return;
        }

        Lesson::where('mux_asset_id', $assetId)->update([
            'mux_playback_id'        => $playbackId,
            'video_duration_seconds' => $duration,
            'video_status'           => 'ready',
        ]);
    }

    private function handleAssetErrored(array $data): void
    {
        $assetId = $data['id'] ?? null;

        if (!$assetId) {
            \Log::warning('Mux webhook missing data', ['data' => $data]);
            return;
        }

        Lesson::where('mux_asset_id', $assetId)->update([
            'video_status' => 'error',
        ]);
    }
}
