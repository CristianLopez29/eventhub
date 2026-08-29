<?php

declare(strict_types=1);

namespace App\Tests\EventIntegration\Unit\Infrastructure\Listeners;

use App\EventIntegration\Infrastructure\Listeners\DocsAccessListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class DocsAccessListenerTest extends TestCase
{
    private const string USERNAME = 'docs';
    private const string PASSWORD = 's3cret';

    public function test_should_leave_the_request_untouched_when_the_path_is_not_the_documentation(): void
    {
        $event = $this->requestFor('/events');

        $this->listener('prod')($event);

        self::assertNull($event->getResponse());
    }

    public function test_should_leave_the_documentation_open_under_a_local_environment(): void
    {
        $event = $this->requestFor('/api/doc');

        $this->listener('dev')($event);

        self::assertNull($event->getResponse());
    }

    public function test_should_fail_closed_when_protection_is_on_and_no_password_is_configured(): void
    {
        $event = $this->requestFor('/api/doc');

        $this->listener('prod', password: '')($event);

        self::assertNotNull($event->getResponse());
        self::assertSame(503, $event->getResponse()->getStatusCode());
    }

    public function test_should_challenge_with_basic_auth_when_credentials_are_missing(): void
    {
        $event = $this->requestFor('/api/doc.json');

        $this->listener('prod')($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(401, $response->getStatusCode());
        self::assertStringStartsWith('Basic realm=', (string) $response->headers->get('WWW-Authenticate'));
    }

    public function test_should_reject_a_wrong_password(): void
    {
        $event = $this->requestFor('/api/doc', user: self::USERNAME, password: 'wrong');

        $this->listener('prod')($event);

        self::assertNotNull($event->getResponse());
        self::assertSame(401, $event->getResponse()->getStatusCode());
    }

    public function test_should_let_the_request_through_when_the_credentials_match(): void
    {
        $event = $this->requestFor('/api/doc', user: self::USERNAME, password: self::PASSWORD);

        $this->listener('prod')($event);

        self::assertNull($event->getResponse());
    }

    private function listener(string $environment, string $password = self::PASSWORD): DocsAccessListener
    {
        return new DocsAccessListener(self::USERNAME, $password, $environment);
    }

    private function requestFor(
        string $path,
        ?string $user = null,
        ?string $password = null,
    ): RequestEvent {
        $server = $user === null ? [] : ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => (string) $password];

        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create($path, 'GET', [], [], [], $server),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
