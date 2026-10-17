<?php

namespace PostRun\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PostRun\PostRunClient;
use PostRun\Priority;
use Psr\Http\Message\RequestInterface;

class PostRunClientTest extends TestCase
{
    /**
     * @var list<array{request: RequestInterface}>
     */
    private array $history = [];

    private function client(): PostRunClient
    {
        $stack = HandlerStack::create(new MockHandler([
            new Response(201, [], json_encode(['message_id' => 'msg-1', 'status' => 'queued', 'priority' => 'high'])),
        ]));
        $stack->push(Middleware::history($this->history));

        return new PostRunClient('test-key', 'https://postrun.test', ['handler' => $stack]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sentPayload(): array
    {
        return json_decode((string) $this->history[0]['request']->getBody(), true);
    }

    public function test_send_includes_a_priority_given_as_an_enum(): void
    {
        $this->client()->send([
            'to' => 'user@example.com',
            'subject' => 'Reset your password',
            'html' => '<p>Reset</p>',
            'priority' => Priority::High,
        ]);

        $this->assertSame('high', $this->sentPayload()['priority']);
    }

    public function test_send_includes_a_priority_given_as_a_string(): void
    {
        $this->client()->send([
            'to' => 'user@example.com',
            'subject' => 'Weekly digest',
            'html' => '<p>Digest</p>',
            'priority' => 'LOW',
        ]);

        $this->assertSame('low', $this->sentPayload()['priority']);
    }

    public function test_send_leaves_the_priority_out_when_none_is_given(): void
    {
        $this->client()->send([
            'to' => 'user@example.com',
            'subject' => 'Hello',
            'html' => '<p>Hello</p>',
        ]);

        $this->assertArrayNotHasKey('priority', $this->sentPayload());
    }

    public function test_send_rejects_an_unknown_priority_without_calling_the_api(): void
    {
        $client = $this->client();

        try {
            $client->send([
                'to' => 'user@example.com',
                'subject' => 'Hello',
                'priority' => 'urgent',
            ]);
            $this->fail('Expected an InvalidArgumentException');
        } catch (InvalidArgumentException) {
            $this->assertSame([], $this->history);
        }
    }

    public function test_send_batch_includes_the_priority(): void
    {
        $this->client()->sendBatch(
            [['to_email' => 'one@example.com'], ['to_email' => 'two@example.com']],
            ['subject' => 'Newsletter', 'html' => '<p>News</p>', 'priority' => Priority::Low],
        );

        $payload = $this->sentPayload();
        $this->assertSame('low', $payload['priority']);
        $this->assertCount(2, $payload['recipients']);
    }

    public function test_send_includes_custom_headers(): void
    {
        $headers = [
            'List-Unsubscribe' => '<https://example.com/u/abc>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ];

        $this->client()->send([
            'to' => 'user@example.com',
            'subject' => 'Weekly digest',
            'html' => '<p>Digest</p>',
            'headers' => $headers,
        ]);

        $this->assertSame($headers, $this->sentPayload()['headers']);
    }

    public function test_send_leaves_headers_out_when_none_are_given(): void
    {
        $this->client()->send(['to' => 'user@example.com', 'subject' => 'Hello']);

        $this->assertArrayNotHasKey('headers', $this->sentPayload());
    }

    public function test_send_batch_includes_shared_and_recipient_headers(): void
    {
        $this->client()->sendBatch(
            [
                ['to_email' => 'one@example.com', 'headers' => ['List-Unsubscribe' => '<https://example.com/u/one>']],
                ['to_email' => 'two@example.com'],
            ],
            ['subject' => 'Newsletter', 'html' => '<p>News</p>', 'headers' => ['X-Campaign' => 'launch']],
        );

        $payload = $this->sentPayload();
        $this->assertSame(['X-Campaign' => 'launch'], $payload['headers']);
        $this->assertSame(['List-Unsubscribe' => '<https://example.com/u/one>'], $payload['recipients'][0]['headers']);
    }

    public function test_requests_still_authenticate_with_custom_http_options(): void
    {
        $this->client()->send(['to' => 'user@example.com', 'subject' => 'Hello']);

        $request = $this->history[0]['request'];
        $this->assertSame('Bearer test-key', $request->getHeaderLine('Authorization'));
        $this->assertSame('https://postrun.test/api/v1/messages/send', (string) $request->getUri());
    }
}
