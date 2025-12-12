# PostRun PHP SDK

PHP SDK for the PostRun email delivery API.

## Installation

```bash
composer require postrun/postrun-php
```

## Usage

### Initialize the Client

```php
use PostRun\PostRunClient;

$client = new PostRunClient(
    apiKey: 'your-domain-api-key',
    endpoint: 'https://your-postrun-instance.com'
);
```

### Send an Email

```php
$result = $client->send([
    'to' => ['email' => 'recipient@example.com', 'name' => 'John Doe'],
    'subject' => 'Hello from PostRun!',
    'html' => '<h1>Hello!</h1><p>This is a test email.</p>',
    'text' => 'Hello! This is a test email.',
    'tags' => ['welcome', 'onboarding'],
    'meta' => ['user_id' => 123],
]);

echo "Message sent! ID: " . $result['message_id'];
```

### Send with Attachments

```php
$result = $client->send([
    'to' => 'recipient@example.com',
    'subject' => 'Invoice Attached',
    'html' => '<p>Please find your invoice attached.</p>',
    'attachments' => [
        [
            'filename' => 'invoice.pdf',
            'content' => base64_encode(file_get_contents('/path/to/invoice.pdf')),
            'mime_type' => 'application/pdf',
        ],
    ],
]);
```

### Send Batch Emails

```php
$result = $client->sendBatch(
    recipients: [
        ['to_email' => 'user1@example.com', 'to_name' => 'User 1'],
        ['to_email' => 'user2@example.com', 'to_name' => 'User 2'],
    ],
    message: [
        'subject' => 'Newsletter',
        'html' => '<h1>Monthly Newsletter</h1>',
    ]
);

echo "Sent {$result['total']} messages";
```

### Get Message Status

```php
$message = $client->getMessage('message-uuid');

echo "Status: " . $message['message']['status'];
echo "Opens: " . $message['stats']['opens'];
echo "Clicks: " . $message['stats']['clicks'];
```

### List Messages

```php
$messages = $client->listMessages([
    'status' => 'delivered',
    'date_from' => '2024-01-01',
    'per_page' => 50,
]);
```

### Resend a Message

```php
$result = $client->resend('message-uuid');
```

### Manage Suppressions

```php
// List suppressions
$suppressions = $client->listSuppressions();

// Add to suppression list
$client->addSuppression('unsubscribed@example.com', 'manual');

// Remove from suppression list
$client->removeSuppression('resubscribed@example.com');
```

## Error Handling

```php
use PostRun\Exceptions\PostRunException;
use PostRun\Exceptions\ValidationException;
use PostRun\Exceptions\AuthenticationException;

try {
    $client->send([...]);
} catch (AuthenticationException $e) {
    // Invalid API key
    echo "Authentication failed: " . $e->getMessage();
} catch (ValidationException $e) {
    // Validation errors
    echo "Validation failed: " . $e->getMessage();
    print_r($e->getErrors());
} catch (PostRunException $e) {
    // Other API errors
    echo "API error: " . $e->getMessage();
}
```

## License

MIT
