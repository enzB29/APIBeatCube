<?php

namespace App\EventListener;

use App\Service\JwtService;
use App\Service\BanService;
use App\Repository\UtilisateurRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class JwtListener
{
    public function __construct(
        private JwtService $jwt,
        private BanService $banService,
        private UtilisateurRepository $utilisateurRepository
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Routes publiques
        if (str_starts_with($path, '/api/auth')) {
            return;
        }

        if (!str_starts_with($path, '/api')) {
            return;
        }

        // ----- JWT -----
        $auth = $request->headers->get('Authorization');
        if (!$auth || !str_starts_with($auth, 'Bearer ')) {
            $event->setResponse(new JsonResponse(['error' => 'Missing token'], 401));
            return;
        }

        $token = substr($auth, 7);
        $payload = $this->jwt->verify($token);

        if (!$payload || !isset($payload['id'])) {
            $event->setResponse(new JsonResponse(['error' => 'Invalid token'], 401));
            return;
        }

        // ----- USER -----
        $user = $this->utilisateurRepository->find($payload['id']);

        if (!$user) {
            $event->setResponse(new JsonResponse(['error' => 'User not found'], 401));
            return;
        }

        // ----- BAN CHECK -----
        $ban = $this->banService->getActiveBan($user);

        if ($ban) {
            $event->setResponse(new JsonResponse([
                'error' => 'Vous êtes banni',
                'reason' => $ban->getReason(),
                'bannedUntil' => $ban->getBannedUntil()?->format('Y-m-d H:i:s'),
            ], 403));
            return;
        }

        // (optionnel) stocker l'utilisateur dans la request si besoin
        $request->attributes->set('user', $user);
    }
}
