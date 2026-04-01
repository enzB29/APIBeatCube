<?php

namespace App\Controller;

use App\Repository\UtilisateurRepository;
use App\Service\BanService;
use App\Service\JwtService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/utilisateur')]
class UtilisateurController extends AbstractController
{
    /**
     * @param UtilisateurRepository $repo
     * @param BanService $banService
     * @return JsonResponse
     */
    #[Route('/allusers', methods: ['GET'])]
    public function AllUsers(UtilisateurRepository $repo, BanService $banService): JsonResponse
    {
        $users = $repo->findAll();

        $userToReturn = [];

        foreach ($users as $user) {
            $banned = $banService->isUserBanned($user);
            $userToReturn[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'isBanned' => $banned,
            ];
        }

        return $this->json([
            'count' => count($userToReturn),
            'users' => $userToReturn,
        ]);
    }

    /**
     * @param int $id
     * @param int $adminId
     * @param Request $request
     * @param UtilisateurRepository $userRepo
     * @param BanService $banService
     * @param JwtService $jwt
     * @return JsonResponse
     * @throws \DateMalformedStringException
     */
    #[Route('/ban/{id}/admin/{adminId}', methods: ['POST'])]
    public function ban(int $id, int $adminId, Request $request, UtilisateurRepository $userRepo, BanService $banService, JwtService $jwt): JsonResponse {
        // Vérifier le token
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->json(['error' => 'Token manquant'], 401);
        }

        $token = $matches[1];
        $payload = $jwt->verify($token);

        if (!$payload) {
            return $this->json(['error' => 'Token invalide'], 401);
        }

        // Vérifier si l'utilisateur a le rôle ROLE_ADMIN
        $roles = $payload['roles'] ?? [];
        if (!in_array('ROLE_ADMIN', $roles)) {
            return $this->json(['error' => 'Accès refusé. Seuls les administrateurs peuvent supprimer des musiques.'], 403);
        }

        $user = $userRepo->find($id);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        $admin = $userRepo->find($adminId);

        $data = json_decode($request->getContent(), true);

        $reason = $data['reason'] ?? 'Violation des règles';
        $days = $data['days'] ?? null;

        $bannedUntil = $days
            ? (new \DateTimeImmutable())->modify("+$days days")
            : null;

        $banService->banUser($user, $reason, $admin, $bannedUntil);

        return $this->json([
            'message' => 'Utilisateur banni',
            'userId' => $user->getId(),
            'bannedUntil' => $bannedUntil?->format('Y-m-d H:i:s'),
            'bannedReason' => $reason,
            'bannedBy' => $admin->getUsername(),
        ], 201);
    }

    /**
     * @param int $id
     * @param Request $request
     * @param UtilisateurRepository $userRepo
     * @param BanService $banService
     * @param JwtService $jwt
     * @return JsonResponse
     */
    #[Route('/unban/{id}', methods: ['POST'])]
    public function unban(int $id, Request $request, UtilisateurRepository $userRepo, BanService $banService, JwtService $jwt): JsonResponse {
        // Vérifier le token
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->json(['error' => 'Token manquant'], 401);
        }

        $token = $matches[1];
        $payload = $jwt->verify($token);

        if (!$payload) {
            return $this->json(['error' => 'Token invalide'], 401);
        }

        // Vérifier si l'utilisateur a le rôle ROLE_ADMIN
        $roles = $payload['roles'] ?? [];
        if (!in_array('ROLE_ADMIN', $roles)) {
            return $this->json(['error' => 'Accès refusé. Seuls les administrateurs peuvent supprimer des musiques.'], 403);
        }

        $user = $userRepo->find($id);
        if (!$user) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        $banService->unbanUser($user);

        return $this->json([
            'message' => 'Ban levé',
            'userId' => $user->getId(),
        ]);
    }
}
