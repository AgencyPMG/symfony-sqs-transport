# SQS Symfony Messenger Transport

## Installation

```
composer require pmg/sqs-transport
```
## Bundle Usage

Add the `PMG\SqsTransport\Bundle\PmgSqsTransportBundle` to your application's
kernel. By default the transport bundle will use an `aws.sqs` service when
creating the transport factory and, by extension, the transport instances.
This service name is configurable, but it *should* place nice with the AWS
Bundle.

```php
class AppKernel extends Kernel
{
    // ...

    public function registerBundles()
    {
        $bundles = [
            new \Aws\Symfony\AwsBundle(),
            new \PMG\SqsTransport\Bundle\PmgSqsTransportBundle(),
        ];

        // ...

        return $bundles;
    }
}
```

### `SqsClient` Service Configuration

If you're not using the `AwsBundle` and would like to manually specify a service
that contains an instance of `Aws\Sqs\SqsClient`, some bundle configuration is
necessary.

```yaml
pmg_sqs_transport:
  sqs_client_service: your_sqs_service_id
```

## Messenger Configuration

```yaml
framework:
  # ...
  messenger:
    transports:
      # will create a transport with a queue URL of
      # https://queue.amazonaws.com/80398EXAMPLE/MyQueue
      sqs: sqs://queue.amazonaws.com/80398EXAMPLE/MyQueue
      
      # this will also make an https URL:
      # https://queue.amazonaws.com/80398EXAMPLE/MyQueue
      sqs_https: sqs+https://queue.amazonaws.com/80398EXAMPLE/MyQueue

      # or you may wish to use `http://`, like if running a localstack
      # instance for local dev. Queue url would be http://localhost:4576/queue/MyQueue
      sqs_http: sqs+http://localhost:4576/queue/MyQueue

      # specify how many message to receive at once with query params
      # must be >= 1 and <= 10
      sqs: sqs://queue.amazonaws.com/80398EXAMPLE/MyQueue?receive_count=10

      # specify a wait timeout when making a call to receive messages (in seconds)
      sqs: sqs://queue.amazonaws.com/80398EXAMPLE/MyQueue?receive_wait=10

      # or specify those things in `options`
      sqs:
        dsn: sqs://queue.amazonaws.com/80398EXAMPLE/MyQueue
        options:
          receive_wait: 10
          receive_count: 5
```

## SQS Stamps

- `PMG\SqsTransport\Stamp\SqsReceiptHandleStamp`: added when a message is
  received from SQS via the `get` method. This allows the message to be
  `ack`ed or `reject`ed later.
- `PMG\SqsTransport\Stamp\SqsStringAttributeStamp`: Allows you to add a custom
  [message attribute](https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-message-attributes.html).
  with a `DataType` set to `String` and a string value.
- `PMG\SqsTransport\Stamp\SqsNumberAttributeStamp`: Allows you to add a custom
  [message attribute](https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-message-attributes.html).
  with a `DataType` set to `Number` and a numeric value.

```php
$messageBus->dispatch(new YourMessage(), [
  new SqsStringAttributeStamp('stringAttributeName', 'attributeValue'),
  new SqsNumberAttributeStamp('numberAttributeName', 123),
]);
```
