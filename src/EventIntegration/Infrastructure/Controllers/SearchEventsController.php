<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Controllers;

use App\EventIntegration\Application\DTOs\SearchEventsInput;
use App\EventIntegration\Application\Transformers\EventTransformer;
use App\EventIntegration\Application\UseCases\SearchEvents;
use DateTimeImmutable;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class SearchEventsController
{
    public function __construct(
        private SearchEvents $searchEvents,
        private EventTransformer $transformer
    ) {
    }

    #[OA\Get(
        path: '/events',
        summary: 'Search events by date range',
        tags: ['Events'],
        parameters: [
            new OA\Parameter(
                name: 'starts_at',
                in: 'query',
                required: true,
                description: 'Range start (inclusive), format YYYY-MM-DDTHH:mm:ss',
                schema: new OA\Schema(type: 'string', example: '2021-07-01T00:00:00')
            ),
            new OA\Parameter(
                name: 'ends_at',
                in: 'query',
                required: true,
                description: 'Range end (inclusive), format YYYY-MM-DDTHH:mm:ss',
                schema: new OA\Schema(type: 'string', example: '2021-07-31T23:59:59')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of matching events',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'events',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '3fa85f64-5717-4562-b3fc-2c963f66afa6'),
                                            new OA\Property(property: 'title', type: 'string', example: 'Concert at the Park'),
                                            new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2021-07-15'),
                                            new OA\Property(property: 'start_time', type: 'string', example: '20:00:00'),
                                            new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2021-07-15'),
                                            new OA\Property(property: 'end_time', type: 'string', example: '23:00:00'),
                                            new OA\Property(property: 'min_price', type: 'number', format: 'float', nullable: true, example: 15.0),
                                            new OA\Property(property: 'max_price', type: 'number', format: 'float', nullable: true, example: 50.0),
                                        ],
                                        type: 'object'
                                    )
                                ),
                            ],
                            type: 'object'
                        ),
                        new OA\Property(property: 'error', type: 'string', nullable: true, example: null),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid or missing query parameters',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'error',
                            properties: [
                                new OA\Property(property: 'code', type: 'string', example: 'INVALID_DATE_FORMAT'),
                                new OA\Property(property: 'message', type: 'string', example: 'Invalid date format. Expected: YYYY-MM-DDTHH:mm:ss'),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Missing or invalid JWT token'),
        ]
    )]
    #[Route('/events', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $startsAtParam = $request->query->get('starts_at');
        $endsAtParam = $request->query->get('ends_at');

        if (!is_string($startsAtParam) || !is_string($endsAtParam)) {
            return new JsonResponse(
                [
                    'error' => [
                        'code' => 'INVALID_PARAMETERS',
                        'message' => 'Missing required query parameters: starts_at and ends_at',
                    ],
                    'data' => null,
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        $startsAt = $this->parseDateTime($startsAtParam);
        $endsAt = $this->parseDateTime($endsAtParam);

        if ($startsAt === null || $endsAt === null) {
            return new JsonResponse(
                [
                    'error' => [
                        'code' => 'INVALID_DATE_FORMAT',
                        'message' => 'Invalid date format. Expected: YYYY-MM-DDTHH:mm:ss',
                    ],
                    'data' => null,
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        $input = new SearchEventsInput($startsAt, $endsAt);
        $events = $this->searchEvents->search($input);

        return new JsonResponse(
            $this->transformer->transformCollection($events),
            Response::HTTP_OK
        );
    }

    private function parseDateTime(string $value): ?DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $value);

        if ($parsed === false) {
            return null;
        }

        $errors = DateTimeImmutable::getLastErrors();

        if ($errors === false) {
            return $parsed;
        }

        if ($errors['error_count'] > 0 || $errors['warning_count'] > 0) {
            return null;
        }

        if ($parsed->format('Y-m-d\TH:i:s') !== $value) {
            return null;
        }

        return $parsed;
    }
}
