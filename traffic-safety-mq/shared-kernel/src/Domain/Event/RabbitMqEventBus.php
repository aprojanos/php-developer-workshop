<?php
namespace SharedKernel\Domain\Event;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;

final class RabbitMqEventBus implements EventBusInterface
{
    /**
     * @var array<class-string<DomainEventInterface>, list<callable>>
     */
    private array $listeners = [];

    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port = 5672,
        private readonly string $username = 'guest',
        private readonly string $password = 'guest',
        private readonly string $vhost = '/',
        private readonly string $exchange = 'domain_events',
        private readonly string $queue = 'domain_events'
    ) {}

    public function dispatch(DomainEventInterface $event): void
    {
        $this->notifyListeners($event);
        $this->publishEvent($event);
    }

    public function addListener(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;

        $this->ensureConsumerInitialized();
    }

    private function notifyListeners(DomainEventInterface $event): void
    {
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            $listener($event);
        }

        foreach ($this->listeners[DomainEventInterface::class] ?? [] as $listener) {
            $listener($event);
        }
    }

    private function publishEvent(DomainEventInterface $event): void
    {
        if (!$this->ensureChannel()) {
            return;
        }

        try {
            $payload = json_encode([
                'event_class' => $event::class,
                'occurred_on' => $event->occurredOn()->format('c'),
                'serialized_event' => base64_encode(serialize($event)),
            ], JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logFailure('Failed to encode event payload', $exception);
            return;
        }

        $message = new AMQPMessage(
            $payload,
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'type' => $event::class,
            ]
        );

        try {
            $this->channel?->basic_publish($message, $this->exchange);
        } catch (\Throwable $exception) {
            $this->logFailure('Failed to publish event to RabbitMQ', $exception);
            $this->closeConnection();
        }
    }

    public function startConsuming(): void
    {
        if (!$this->ensureChannel()) {
            return;
        }

        $this->channel?->basic_qos(null, 1, null);

        $this->channel?->basic_consume(
            queue: $this->queue,
            callback: function (AMQPMessage $message): void {
                $this->handleIncomingMessage($message);
            }
        );

        while ($this->channel !== null && $this->channel->is_consuming()) {
            try {
                $this->channel->wait(null, false, 5.0);
            } catch (AMQPTimeoutException) {
                // Keep the loop alive; allows for periodic shutdown checks.
            } catch (\Throwable $exception) {
                $this->logFailure('RabbitMQ consumer loop failed', $exception);
                break;
            }
        }
    }

    private function handleIncomingMessage(AMQPMessage $message): void
    {
        try {
            $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            $this->logFailure('Failed to decode RabbitMQ message', $exception);
            $message->ack();
            return;
        }

        $serializedEvent = $payload['serialized_event'] ?? null;
        if (!is_string($serializedEvent)) {
            $this->logFailure('RabbitMQ message missing serialized_event field', new \UnexpectedValueException());
            $message->ack();
            return;
        }

        $event = @unserialize(base64_decode($serializedEvent, true), ['allowed_classes' => true]);
        if (!$event instanceof DomainEventInterface) {
            $this->logFailure('RabbitMQ message payload is not a domain event', new \UnexpectedValueException());
            $message->ack();
            return;
        }

        try {
            $this->notifyListeners($event);
            $properties = $message->get_properties();
            $this->logInfo('RabbitMQ event handled', [
                'event' => $event::class,
                'message_id' => $properties['message_id'] ?? null,
            ]);
            $message->ack();
        } catch (\Throwable $exception) {
            $this->logFailure('Listener execution failed for RabbitMQ event', $exception);
            $message->nack(false, false);
        }
    }

    private function ensureConsumerInitialized(): void
    {
        if ($this->consumerInitialized || !$this->ensureChannel()) {
            return;
        }

        $this->consumerInitialized = true;
    }

    private bool $consumerInitialized = false;

    private function ensureChannel(): bool
    {
        if ($this->channel !== null && $this->channel->is_open()) {
            return true;
        }

        $this->closeConnection();

        try {
            $this->connection = new AMQPStreamConnection(
                host: $this->host,
                port: $this->port,
                user: $this->username,
                password: $this->password,
                vhost: $this->vhost
            );

            $this->channel = $this->connection->channel();
            $this->channel->exchange_declare(
                exchange: $this->exchange,
                type: 'fanout',
                passive: false,
                durable: true,
                auto_delete: false
            );
            $this->channel->queue_declare(
                queue: $this->queue,
                passive: false,
                durable: true,
                exclusive: false,
                auto_delete: false
            );
            $this->channel->queue_bind($this->queue, $this->exchange);

            return true;
        } catch (\Throwable $exception) {
            $this->logFailure('Failed to establish RabbitMQ connection', $exception);
            $this->closeConnection();

            return false;
        }
    }

    private function closeConnection(): void
    {
        if ($this->channel !== null) {
            try {
                $this->channel->close();
            } catch (\Throwable) {
                // Intentionally ignored.
            } finally {
                $this->channel = null;
            }
        }

        if ($this->connection !== null) {
            try {
                $this->connection->close();
            } catch (\Throwable) {
                // Intentionally ignored.
            } finally {
                $this->connection = null;
            }
        }
    }

    private function logFailure(string $message, \Throwable $exception): void
    {
        error_log(sprintf(
            '[RabbitMqEventBus] %s: %s',
            $message,
            $exception->getMessage()
        ));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function logInfo(string $message, array $context = []): void
    {
        $contextString = $context !== [] ? json_encode($context) : '';
        error_log(sprintf(
            '[RabbitMqEventBus] %s%s',
            $message,
            $contextString !== false && $contextString !== '' ? ' ' . $contextString : ''
        ));
    }

    public function __destruct()
    {
        $this->closeConnection();
    }
}

