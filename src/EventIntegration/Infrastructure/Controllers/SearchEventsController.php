<?php

declare(strict_types=1);

namespace App\EventIntegration\Infrastructure\Controllers;

use App\EventIntegration\Application\DTOs\SearchEventsInput;
use App\EventIntegration\Application\Transformers\EventTransformer;
use App\EventIntegration\Application\UseCases\SearchEvents;
use DateTimeImmutable;
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
