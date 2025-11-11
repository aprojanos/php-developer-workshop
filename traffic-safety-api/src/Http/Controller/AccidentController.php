<?php

declare(strict_types=1);

namespace App\Http\Controller;

use AccidentContext\Domain\Factory\AccidentFactory;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Http\Serializer\DomainSerializer;
use SharedKernel\DTO\AccidentSearchDTO;

final class AccidentController extends BaseController
{
    public function register(Router $router): void
    {
        $router->add('GET', '/api/accidents', fn(Request $request): Response => $this->listAccidents());
        $router->add('GET', '/api/accidents/total-estimated-cost', fn(Request $request): Response => $this->totalEstimatedCost());
        $router->add('GET', '/api/accidents/{id}', fn(Request $request): Response => $this->getAccident($request));
        $router->add('POST', '/api/accidents', fn(Request $request): Response => $this->createAccident($request));
        $router->add('PUT', '/api/accidents/{id}', fn(Request $request): Response => $this->updateAccident($request));
        $router->add('DELETE', '/api/accidents/{id}', fn(Request $request): Response => $this->deleteAccident($request));
        $router->add('POST', '/api/accidents/calculate-total-cost', fn(Request $request): Response => $this->calculateTotalCost($request));
        $router->add('POST', '/api/accidents/search', fn(Request $request): Response => $this->searchAccidents($request));
    }

    private function listAccidents(): Response
    {
        $accidents = $this->container->getAccidentService()->all();

        return $this->json([
            'data' => DomainSerializer::accidents($accidents),
        ]);
    }

    private function getAccident(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $accident = $this->container->getAccidentService()->findById($id);

        if ($accident === null) {
            throw new HttpException('Accident not found.', 404);
        }

        return $this->json(DomainSerializer::accident($accident));
    }

    private function createAccident(Request $request): Response
    {
        $payload = $request->getBody();
        if (!isset($payload['id'])) {
            throw new HttpException('Field "id" is required.', 422);
        }

        $accident = AccidentFactory::create($payload);
        $service = $this->container->getAccidentService();
        $service->create($accident);

        $created = $service->findById($accident->id);

        return $this->created($created !== null ? DomainSerializer::accident($created) : [
            'id' => $accident->id,
            'status' => 'created',
        ]);
    }

    private function updateAccident(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $payload = $request->getBody();
        $payload['id'] = $id;

        $accident = AccidentFactory::create($payload);

        $service = $this->container->getAccidentService();
        $service->update($accident);

        $updated = $service->findById($id);

        return $this->json($updated !== null ? DomainSerializer::accident($updated) : ['id' => $id]);
    }

    private function deleteAccident(Request $request): Response
    {
        $id = (int)$this->requireRouteParam($request, 'id');
        $this->container->getAccidentService()->delete($id);

        return $this->noContent();
    }

    private function totalEstimatedCost(): Response
    {
        $total = $this->container->getAccidentService()->totalEstimatedCost();

        return $this->json(['totalEstimatedCost' => $total]);
    }

    private function calculateTotalCost(Request $request): Response
    {
        $data = $this->requireJsonArray($request, 'accidents');
        $accidents = array_map(static fn(array $item) => AccidentFactory::create($item), $data);

        $total = $this->container->getAccidentService()->calculateTotalCost($accidents);

        return $this->json(['totalCost' => $total]);
    }

    private function searchAccidents(Request $request): Response
    {
        $payload = $request->getBody();
        $searchDto = AccidentSearchDTO::fromArray($payload);

        $results = $this->container->getAccidentService()->search($searchDto);

        return $this->json([
            'data' => DomainSerializer::accidents($results),
            'count' => count($results),
        ]);
    }
}

