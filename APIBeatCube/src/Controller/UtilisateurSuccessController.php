<?php

namespace App\Controller;

use App\Service\JwtService;
use App\Service\SuccessService;
use App\Service\UtilisateurService;
use App\Service\UtilisateurSuccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api')]
final class UtilisateurSuccessController extends AbstractController
{
    /**
     * @param int $userId
     * @param UtilisateurSuccessService $successService
     * @return JsonResponse
     */
    #[Route('/user/success/{userId}', name: 'success_by_userId', methods: ['GET'])]
    public function SuccessByUserId(int $userId, UtilisateurSuccessService $successService): JsonResponse
    {
        $us = $successService->getUtilisateurSuccessesByUserId($userId);

        $result = array_map(function ($userSuccess) {
            return [
                'utilisateur' => [
                    'id' => $userSuccess->getUtilisateur()->getId(),
                    'username' => $userSuccess->getUtilisateur()->getUsername(),
                    'email' => $userSuccess->getUtilisateur()->getEmail(),
                ],
                'success' => [
                    'id' => $userSuccess->getSuccess()->getId(),
                    'name' => $userSuccess->getSuccess()->getName(),
                    'description' => $userSuccess->getSuccess()->getDescription(),
                ],
                'obtained_at' => $userSuccess->getObtainedAt(),
            ];
        }, $us);

        return $this->json([
            'successes' => $result,
        ]);
    }

    /**
     * @param int $successId
     * @param UtilisateurSuccessService $successService
     * @return JsonResponse
     */
    #[Route('/success/{successId}', name: 'user_by_successId', methods: ['GET'])]
    public function UsersBySuccessId(int $successId, UtilisateurSuccessService $successService): JsonResponse
    {
        $us = $successService->getUtilisateurSuccessBySuccessesId($successId);

        $result = array_map(function ($userSuccess) {
            return [
                'utilisateur' => [
                    'id' => $userSuccess->getUtilisateur()->getId(),
                    'username' => $userSuccess->getUtilisateur()->getUsername(),
                    'email' => $userSuccess->getUtilisateur()->getEmail(),
                ],
                'success' => [
                    'id' => $userSuccess->getSuccess()->getId(),
                    'name' => $userSuccess->getSuccess()->getName(),
                    'description' => $userSuccess->getSuccess()->getDescription(),
                ],
                'obtained_at' => $userSuccess->getObtainedAt(),
            ];
        }, $us);

        return $this->json([
            'successes' => $result,
        ]);
    }

    #[Route('/success/save/{successId}', name: 'success_save', methods: ['POST'])]
    public function saveSuccessForConnectedUser(int $successId, Request $request, UtilisateurSuccessService $userSuccessService, JwtService $jwt): JsonResponse
    {
        // Vérifier le token JWT
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->json(['error' => 'Token manquant'], 401);
        }

        $token = $matches[1];
        $payload = $jwt->verify($token);

        if (!$payload) {
            return $this->json(['error' => 'Token invalide'], 401);
        }

        // L'userId vient du token JWT (sécurisé)
        $userId = $payload['id'];

        try {
            // Le service gère la récupération de l'utilisateur et du succès
            $result = $userSuccessService->saveUtilisateurSuccess($userId, $successId);
            if (!$result['success']) {
                // Différencier les codes d'erreur selon le type
                $statusCode = match($result['error']) {
                    'Utilisateur non trouvé', 'Succès non trouvé' => 404,
                    'Succès déjà obtenu' => 409, // Conflict
                    default => 400
                };

                return $this->json($result, $statusCode);
            }

            return $this->json($result, 201);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de l\'enregistrement du succès',
                'exception' => $e->getMessage()
            ], 500);
        }
    }
}
