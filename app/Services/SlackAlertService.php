<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackAlertService
{
    protected ?string $token;
    protected ?string $channel;

    public function __construct()
    {
        $this->token = config('services.slack.notifications.bot_user_oauth_token');
        $this->channel = config('services.slack.notifications.channel');
    }

    /**
     * Send an alert message to Slack.
     */
    public function alert(string $message, array $context = []): bool
    {
        if (empty($this->token) || empty($this->channel)) {
            Log::warning('SlackAlertService: Slack not configured', [
                'has_token' => !empty($this->token),
                'has_channel' => !empty($this->channel),
            ]);
            return false;
        }

        try {
            $blocks = $this->buildAlertBlocks($message, $context);

            $response = Http::withToken($this->token)
                ->post('https://slack.com/api/chat.postMessage', [
                    'channel' => $this->channel,
                    'text' => $message, // Fallback for notifications
                    'blocks' => $blocks,
                ]);

            if (!$response->successful() || !($response->json('ok') ?? false)) {
                Log::error('SlackAlertService: Failed to send message', [
                    'status' => $response->status(),
                    'error' => $response->json('error') ?? $response->body(),
                ]);
                return false;
            }

            Log::info('SlackAlertService: Alert sent successfully');
            return true;

        } catch (\Exception $e) {
            Log::error('SlackAlertService: Exception sending alert', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send a critical alert (sync failure).
     */
    public function syncFailure(string $jobName, ?string $error, array $context = []): bool
    {
        $message = ":rotating_light: *{$jobName} Failed*";

        return $this->alert($message, array_merge([
            'Error' => $error ?? 'Unknown error',
            'Environment' => app()->environment(),
            'Time' => now()->format('Y-m-d H:i:s T'),
        ], $context));
    }

    /**
     * Build Slack Block Kit blocks for the alert.
     */
    protected function buildAlertBlocks(string $message, array $context): array
    {
        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $message,
                ],
            ],
        ];

        if (!empty($context)) {
            $fields = [];
            foreach ($context as $key => $value) {
                $fields[] = [
                    'type' => 'mrkdwn',
                    'text' => "*{$key}:*\n" . (is_array($value) ? json_encode($value) : $value),
                ];
            }

            $blocks[] = [
                'type' => 'section',
                'fields' => array_slice($fields, 0, 10), // Slack limit
            ];
        }

        $blocks[] = [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => ':basketball: Basketball Spy Alert',
                ],
            ],
        ];

        return $blocks;
    }
}
