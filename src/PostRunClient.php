<?php

namespace PostRun;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use PostRun\Exceptions\AuthenticationException;
use PostRun\Exceptions\PostRunException;
use PostRun\Exceptions\ValidationException;

class PostRunClient
{
    protected Client $http;

    protected string $apiKey;

    protected string $endpoint;

    /**
     * @param  array<string, mixed>  $httpOptions  Extra Guzzle client options, e.g. a custom handler.
     */
    public function __construct(string $apiKey, string $endpoint = 'https://postrun.io', array $httpOptions = [])
    {
        $this->apiKey = $apiKey;
        $this->endpoint = rtrim($endpoint, '/');

        $this->http = new Client([
            'base_uri' => $this->endpoint,
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
            ...$httpOptions,
        ]);
    }

    /**
     * Send a single email message.
     *
     * @param array{
     *     to: array{email: string, name?: string}|string,
     *     from?: array{email: string, name?: string},
     *     reply_to?: array{email: string, name?: string},
     *     subject: string,
     *     html?: string,
     *     text?: string,
     *     tags?: string[],
     *     meta?: array<string, mixed>,
     *     priority?: Priority|string,
     *     attachments?: array<array{filename: string, content: string, mime_type?: string}>
     * } $message
     * @return array{message_id: string, status: string, priority: string}
     *
     * @throws PostRunException
     * @throws \InvalidArgumentException When the priority is not a known priority.
     */
    public function send(array $message): array
    {
        $payload = $this->normalizeMessage($message);

        return $this->request('POST', '/api/v1/messages/send', $payload);
    }

    /**
     * Send a batch of emails.
     *
     * @param  array<array{to_email: string, to_name?: string}>  $recipients
     * @param array{
     *     from?: array{email: string, name?: string},
     *     subject: string,
     *     html?: string,
     *     text?: string,
     *     tags?: string[],
     *     meta?: array<string, mixed>,
     *     priority?: Priority|string,
     *     attachments?: array<array{filename: string, content: string, mime_type?: string}>
     * } $message
     * @return array{messages: array, total: int}
     *
     * @throws PostRunException
     * @throws \InvalidArgumentException When the priority is not a known priority.
     */
    public function sendBatch(array $recipients, array $message): array
    {
        if (isset($message['priority'])) {
            $message['priority'] = Priority::fromValue($message['priority'])->value;
        }

        $payload = array_merge($message, [
            'recipients' => $recipients,
        ]);

        return $this->request('POST', '/api/v1/messages/send-batch', $payload);
    }

    /**
     * Get a list of messages.
     *
     * @param array{
     *     to_email?: string,
     *     subject?: string,
     *     status?: string,
     *     date_from?: string,
     *     date_to?: string,
     *     tag?: string,
     *     per_page?: int
     * } $filters
     *
     * @throws PostRunException
     */
    public function listMessages(array $filters = []): array
    {
        return $this->request('GET', '/api/v1/messages', $filters);
    }

    /**
     * Get a single message by UUID.
     *
     * @return array{message: array, stats: array}
     *
     * @throws PostRunException
     */
    public function getMessage(string $messageId): array
    {
        return $this->request('GET', "/api/v1/messages/{$messageId}");
    }

    /**
     * Resend a message.
     *
     * @return array{message_id: string, status: string}
     *
     * @throws PostRunException
     */
    public function resend(string $messageId): array
    {
        return $this->request('POST', "/api/v1/messages/{$messageId}/resend");
    }

    /**
     * List suppressed emails.
     *
     * @return array{suppressions: array, meta: array}
     *
     * @throws PostRunException
     */
    public function listSuppressions(): array
    {
        return $this->request('GET', '/api/v1/suppressions');
    }

    /**
     * Add an email to the suppression list.
     *
     * @param  string  $reason  One of: bounce, complaint, manual
     *
     * @throws PostRunException
     */
    public function addSuppression(string $email, string $reason = 'manual'): array
    {
        return $this->request('POST', '/api/v1/suppressions', [
            'email' => $email,
            'reason' => $reason,
        ]);
    }

    /**
     * Remove an email from the suppression list.
     *
     * @throws PostRunException
     */
    public function removeSuppression(string $email): array
    {
        return $this->request('DELETE', '/api/v1/suppressions/'.urlencode($email));
    }

    // ========================================
    // Webhook Endpoints
    // ========================================

    /**
     * List webhook endpoints.
     *
     * @return array{webhooks: array, meta: array}
     *
     * @throws PostRunException
     */
    public function listWebhooks(): array
    {
        return $this->request('GET', '/api/v1/webhooks');
    }

    /**
     * Create a new webhook endpoint.
     *
     * @param array{
     *     name: string,
     *     url: string,
     *     events: string[],
     *     active?: bool
     * } $data
     * @return array{webhook: array, message: string}
     *
     * @throws PostRunException
     */
    public function createWebhook(array $data): array
    {
        return $this->request('POST', '/api/v1/webhooks', $data);
    }

    /**
     * Get a webhook endpoint.
     *
     * @return array{webhook: array}
     *
     * @throws PostRunException
     */
    public function getWebhook(int $id): array
    {
        return $this->request('GET', "/api/v1/webhooks/{$id}");
    }

    /**
     * Update a webhook endpoint.
     *
     * @param array{
     *     name?: string,
     *     url?: string,
     *     events?: string[],
     *     active?: bool
     * } $data
     * @return array{webhook: array, message: string}
     *
     * @throws PostRunException
     */
    public function updateWebhook(int $id, array $data): array
    {
        return $this->request('PUT', "/api/v1/webhooks/{$id}", $data);
    }

    /**
     * Delete a webhook endpoint.
     *
     * @return array{message: string}
     *
     * @throws PostRunException
     */
    public function deleteWebhook(int $id): array
    {
        return $this->request('DELETE', "/api/v1/webhooks/{$id}");
    }

    /**
     * Regenerate the webhook secret.
     *
     * @return array{webhook: array, message: string}
     *
     * @throws PostRunException
     */
    public function regenerateWebhookSecret(int $id): array
    {
        return $this->request('POST', "/api/v1/webhooks/{$id}/regenerate-secret");
    }

    /**
     * List recent deliveries for a webhook endpoint.
     *
     * @return array{deliveries: array, meta: array}
     *
     * @throws PostRunException
     */
    public function listWebhookDeliveries(int $id): array
    {
        return $this->request('GET', "/api/v1/webhooks/{$id}/deliveries");
    }

    /**
     * Send a test webhook.
     *
     * @return array{message: string}
     *
     * @throws PostRunException
     */
    public function testWebhook(int $id): array
    {
        return $this->request('POST', "/api/v1/webhooks/{$id}/test");
    }

    /**
     * Normalize the message payload.
     */
    protected function normalizeMessage(array $message): array
    {
        $payload = [];

        // Handle 'to' field - can be string, array with email/name, or already normalized
        if (isset($message['to'])) {
            if (is_string($message['to'])) {
                $payload['to_email'] = $message['to'];
            } elseif (is_array($message['to'])) {
                $payload['to_email'] = $message['to']['email'] ?? $message['to'][0]['email'] ?? null;
                $payload['to_name'] = $message['to']['name'] ?? $message['to'][0]['name'] ?? null;
            }
        }
        if (isset($message['to_email'])) {
            $payload['to_email'] = $message['to_email'];
        }
        if (isset($message['to_name'])) {
            $payload['to_name'] = $message['to_name'];
        }

        // Handle 'from' field
        if (isset($message['from'])) {
            if (is_string($message['from'])) {
                $payload['from_email'] = $message['from'];
            } elseif (is_array($message['from'])) {
                $payload['from_email'] = $message['from']['email'] ?? null;
                $payload['from_name'] = $message['from']['name'] ?? null;
            }
        }
        if (isset($message['from_email'])) {
            $payload['from_email'] = $message['from_email'];
        }
        if (isset($message['from_name'])) {
            $payload['from_name'] = $message['from_name'];
        }

        // Handle 'reply_to' field
        if (isset($message['reply_to'])) {
            if (is_string($message['reply_to'])) {
                $payload['reply_to_email'] = $message['reply_to'];
            } elseif (is_array($message['reply_to'])) {
                $payload['reply_to_email'] = $message['reply_to']['email'] ?? null;
                $payload['reply_to_name'] = $message['reply_to']['name'] ?? null;
            }
        }
        if (isset($message['reply_to_email'])) {
            $payload['reply_to_email'] = $message['reply_to_email'];
        }
        if (isset($message['reply_to_name'])) {
            $payload['reply_to_name'] = $message['reply_to_name'];
        }

        // Copy other fields directly
        foreach (['subject', 'html', 'text', 'html_body', 'text_body', 'tags', 'meta', 'metadata', 'attachments'] as $field) {
            if (isset($message[$field])) {
                // Normalize body field names
                if ($field === 'html') {
                    $payload['html_body'] = $message[$field];
                } elseif ($field === 'text') {
                    $payload['text_body'] = $message[$field];
                } elseif ($field === 'meta') {
                    $payload['metadata'] = $message[$field];
                } else {
                    $payload[$field] = $message[$field];
                }
            }
        }

        if (isset($message['priority'])) {
            $payload['priority'] = Priority::fromValue($message['priority'])->value;
        }

        return array_filter($payload, fn ($v) => $v !== null);
    }

    /**
     * Make an API request.
     *
     * @throws PostRunException
     */
    protected function request(string $method, string $uri, array $data = []): array
    {
        try {
            $options = [];

            if ($method === 'GET' && ! empty($data)) {
                $options['query'] = $data;
            } elseif (! empty($data)) {
                $options['json'] = $data;
            }

            $response = $this->http->request($method, $uri, $options);
            $body = (string) $response->getBody();

            return json_decode($body, true) ?? [];
        } catch (GuzzleException $e) {
            $this->handleException($e);
        }
    }

    /**
     * Handle Guzzle exceptions and convert to PostRun exceptions.
     *
     * @throws PostRunException
     */
    protected function handleException(GuzzleException $e): never
    {
        $response = method_exists($e, 'getResponse') ? $e->getResponse() : null;
        $statusCode = $response?->getStatusCode() ?? 0;
        $body = $response ? (string) $response->getBody() : '';
        $data = json_decode($body, true) ?? [];

        $message = $data['message'] ?? $data['error'] ?? $e->getMessage();

        if ($statusCode === 401) {
            throw new AuthenticationException($message, $statusCode, $e);
        }

        if ($statusCode === 422) {
            throw new ValidationException(
                $message,
                $data['errors'] ?? $data['messages'] ?? [],
                $statusCode,
                $e
            );
        }

        throw new PostRunException($message, $statusCode, $e);
    }
}
