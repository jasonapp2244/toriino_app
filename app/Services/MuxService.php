<?php

namespace App\Services;

use MuxPhp\Api\DirectUploadsApi;
use MuxPhp\Api\AssetsApi;
use MuxPhp\Configuration;
use MuxPhp\Models\CreateAssetRequest;
use MuxPhp\Models\CreateUploadRequest;
use MuxPhp\Models\InputSettings;
use MuxPhp\Models\PlaybackPolicy;
use GuzzleHttp\Client;

class MuxService
{
    private Configuration $config;

    public function __construct()
    {
        $this->config = Configuration::getDefaultConfiguration()
            ->setUsername(config('services.mux.token_id'))
            ->setPassword(config('services.mux.token_secret'));
    }

    /**
     * Create a direct upload URL for the client to upload video directly to Mux.
     * Returns ['upload_id', 'upload_url'].
     */
    public function createDirectUpload(): array
    {
        $uploadsApi = new DirectUploadsApi(new Client(), $this->config);

        $createUploadRequest = new CreateUploadRequest([
            'timeout'     => 3600,
            'cors_origin' => '*',
            'new_asset_settings' => new CreateAssetRequest([
                'playback_policy' => [PlaybackPolicy::_PUBLIC],
            ]),
        ]);

        $upload = $uploadsApi->createDirectUpload($createUploadRequest);

        return [
            'upload_id'  => $upload->getData()->getId(),
            'upload_url' => $upload->getData()->getUrl(),
        ];
    }

    /**
     * Retrieve asset details (playback ID, duration) after processing.
     */
    public function getAsset(string $assetId): array
    {
        $assetsApi = new AssetsApi(new Client(), $this->config);
        $asset     = $assetsApi->getAsset($assetId);

        $playbackId = null;
        if ($asset->getData()->getPlaybackIds()) {
            $playbackId = $asset->getData()->getPlaybackIds()[0]->getId();
        }

        return [
            'asset_id'               => $asset->getData()->getId(),
            'playback_id'            => $playbackId,
            'status'                 => $asset->getData()->getStatus(),
            'duration_seconds'       => (int) round($asset->getData()->getDuration() ?? 0),
        ];
    }

    /**
     * Delete a Mux asset.
     */
    public function deleteAsset(string $assetId): void
    {
        $assetsApi = new AssetsApi(new Client(), $this->config);
        $assetsApi->deleteAsset($assetId);
    }

    /**
     * Verify a Mux webhook signature.
     * Returns true if valid.
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = config('services.mux.webhook_secret');
        if (!$secret) {
            if (app()->environment('production')) {
                throw new \RuntimeException('MUX_WEBHOOK_SECRET must be configured in production');
            }
            \Log::warning('Mux webhook verification skipped - no secret configured');
            return true;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signature);
    }
}
