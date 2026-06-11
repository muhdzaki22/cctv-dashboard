<?php

namespace App\Console\Commands;

use App\Services\NvrAuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class NvrAuthenticateCommand extends Command
{
    protected $signature = 'nvr:authenticate';
    protected $description = 'Authenticate with NVR and store access token';

    public function __construct(private NvrAuthService $nvrAuth)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Authenticating with NVR...');

        try {
            // Step 1: Get nonce
            $this->info('Step 1: Getting nonce from NVR...');
            $nonce = $this->nvrAuth->getNonce();
            $this->info("✓ Nonce received: {$nonce}");

            // Step 2: Get access token
            $this->info('Step 2: Getting access token...');
            $tokenData = $this->nvrAuth->getAccessToken($nonce);
            $this->info("✓ Access token received");
            $this->info("  Token type: {$tokenData['token_type']}");
            $this->info("  Expires in: {$tokenData['expires_in']} seconds");

            // Step 3: Store tokens in cache
            $this->info('Step 3: Storing tokens in cache...');
            Cache::put('nvr_access_token', $tokenData['access_token'], now()->addSeconds($tokenData['expires_in']));
            Cache::put('nvr_refresh_token', $tokenData['refresh_token'], now()->addHours(24));
            Cache::put('nvr_token_expiry', now()->addSeconds($tokenData['expires_in']), now()->addHours(24));

            $this->info("✓ Tokens stored in cache successfully");

            // Test the token
            $this->info('Step 4: Testing token...');
            $process = $this->nvrAuth->startRecordingSearch($tokenData['access_token']);
            $this->info("✓ Token working! Process ID: {$process}");

            // Get some recording results
            $this->info('Step 5: Fetching recording results...');
            $today = date('Ymd');
            $results = $this->nvrAuth->getRecordingResults($tokenData['access_token'], $process, $today);
            $recordings = $results['results'] ?? [];
            $this->info("✓ Found " . count($recordings) . " recordings for today");

            // Display recording details
            if (!empty($recordings)) {
                $this->newLine();
                $this->info('📹 Recording Details (Malaysia Time):');
                foreach ($recordings as $index => $recording) {
                    $startTime = date('Y-m-d H:i:s', $recording['start_time']);
                    $endTime = date('Y-m-d H:i:s', $recording['end_time']);
                    $duration = $recording['end_time'] - $recording['start_time'];

                    // Convert to Malaysia time (UTC+8)
                    $startMY = \Carbon\Carbon::createFromTimestamp($recording['start_time'], 'UTC')->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s');
                    $endMY = \Carbon\Carbon::createFromTimestamp($recording['end_time'], 'UTC')->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s');

                    $this->line("  " . ($index + 1) . ". Start: {$startMY} (MY)");
                    $this->line("     End:   {$endMY} (MY)");
                    $this->line("     Duration: {$duration} seconds");
                    if ($index < count($recordings) - 1) {
                        $this->newLine();
                    }
                }
            }

            $this->newLine();
            $this->info('✅ NVR authentication completed successfully!');
            $this->info('Tokens are stored in cache and will expire in ' . $tokenData['expires_in'] . ' seconds.');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Authentication failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
