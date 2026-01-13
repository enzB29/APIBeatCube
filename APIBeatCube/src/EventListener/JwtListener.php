<?php

namespace App\EventListener;

use App\Service\JwtService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class JwtListener
{
    public function __construct(private JwtService $jwt) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/api/auth')) {
            return;
        }

        if (!str_starts_with($path, '/api')) {
            return;
        }

        $auth = $request->headers->get('Authorization');
        if (!$auth || !str_starts_with($auth, 'Bearer ')) {
            $event->setResponse(new JsonResponse(['error' => 'Missing token'], 401));
            return;
        }

        $token = substr($auth, 7);
        $payload = $this->jwt->verify($token);

        if (!$payload) {
            $event->setResponse(new JsonResponse(['error' => 'Invalid token'], 401));
        }
    }
}
