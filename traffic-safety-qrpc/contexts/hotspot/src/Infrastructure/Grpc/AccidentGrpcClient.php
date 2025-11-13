<?php

namespace HotspotContext\Infrastructure\Grpc;

use Google\Protobuf\GPBEmpty;
use Grpc\Status;
use HotspotContext\Application\Port\AccidentProviderInterface;
use SharedKernel\Contract\LoggerInterface;
use SharedKernel\Model\AccidentBase;
use Traffic\Grpc\Accident\V1\AccidentServiceClient;

final class AccidentGrpcClient implements AccidentProviderInterface
{
    public function __construct(
        private readonly AccidentServiceClient $client,
        private readonly AccidentMessageHydrator $hydrator,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return AccidentBase[]
     */
    public function all(): array
    {
        [$response, $status] = $this->client->All(new GPBEmpty())->wait();

        $this->assertOkStatus($status);

        $accidents = [];
        foreach ($response->getAccidents() as $message) {
            $accidents[] = $this->hydrator->fromMessage($message);
        }

        $this->logger?->info('Accidents fetched via gRPC', [
            'count' => count($accidents),
        ]);

        return $accidents;
    }

    private function assertOkStatus(Status $status): void
    {
        if ($status->code === \Grpc\STATUS_OK) {
            return;
        }

        $message = $status->details !== '' ? $status->details : 'Unknown gRPC error';

        $this->logger?->error('Accident gRPC call failed', [
            'code' => $status->code,
            'message' => $message,
        ]);

        throw new \RuntimeException(sprintf(
            'AccidentService gRPC call failed with code %d: %s',
            $status->code,
            $message
        ));
    }
}

