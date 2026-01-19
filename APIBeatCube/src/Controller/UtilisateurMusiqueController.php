<?php
namespace App\Controller;

use App\Service\UtilisateurMusiqueService;
use App\Service\JwtService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class UtilisateurMusiqueController extends AbstractController
{
    /**
     * @param Request $request
     * @param JwtService $jwt
     * @param UtilisateurMusiqueService $utilisateurMusiqueService
     * @return Response
     */
    #[Route('/score/save', name: 'score_save', methods: ['POST'])]
    public function save(Request $request, JwtService $jwt, UtilisateurMusiqueService $utilisateurMusiqueService): Response
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

        // Récupérer les données
        $data = json_decode($request->getContent(), true);

        $musiqueUuid = $data['musiqueUuid'] ?? null;
        $score = $data['score'] ?? null;
        $accuracy = $data['accuracy'] ?? null;
        $fullCombo = $data['fullCombo'] ?? null;


        // Validation
        if (
            !$musiqueUuid ||
            !is_numeric($score) || $score < 0 ||
            !is_numeric($accuracy) || $accuracy < 0 || $accuracy > 100 ||
            !is_numeric($fullCombo) || $fullCombo < 0
        ) {
            return $this->json([
                'error' => 'musiqueUuid, score, accuracy (0-100) et fullCombo sont requis'
            ], 400);
        }

        // L'userId vient du token JWT (sécurisé)
        $userId = $payload['id'];

        try {
            $result = $utilisateurMusiqueService->saveScore($userId, $musiqueUuid, (int)$score, $accuracy, $fullCombo);

            if (!$result['success']) {
                return $this->json($result, 404);
            }

            return $this->json($result, 201);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de l\'enregistrement du score', 'exception' => $e->getMessage()], 500);
        }
    }

    /**
     * @param string $musiqueUuid
     * @param int $limit
     * @param UtilisateurMusiqueService $utilisateurMusiqueService
     * @return Response
     */
    #[Route('/score/top/{musiqueUuid}/{limit}', name: 'score_top', methods: ['GET'])]
    public function topScores(string $musiqueUuid, int $limit, UtilisateurMusiqueService $utilisateurMusiqueService): Response
    {
        if ($limit <= 0) {
            return $this->json(['error' => 'Le nombre de joueurs demandé doit être supérieur à 0'], 400);
        }

        try {
            $scores = $utilisateurMusiqueService->getTopScoresByMusique($musiqueUuid, $limit);

            return $this->json([
                'musiqueUuid' => $musiqueUuid,
                'limit' => $limit,
                'scores' => $scores
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la récupération du classement',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @param string $musiqueUuid
     * @param int $limit
     * @param UtilisateurMusiqueService $utilisateurMusiqueService
     * @return Response
     */
    #[Route('/accuracy/top/{musiqueUuid}/{limit}', name: 'accuracy_top', methods: ['GET'])]
    public function topAccuracy(string $musiqueUuid, int $limit, UtilisateurMusiqueService $utilisateurMusiqueService): Response
    {
        if ($limit <= 0) {
            return $this->json(['error' => 'Le nombre de joueurs demandé doit être supérieur à 0'], 400);
        }

        try {
            $accuracy = $utilisateurMusiqueService->getTopAccuracyByMusique($musiqueUuid, $limit);

            return $this->json([
                'musiqueUuid' => $musiqueUuid,
                'limit' => $limit,
                'scores' => $accuracy,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la récupération du classement',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @param string $musiqueUuid
     * @param int $limit
     * @param UtilisateurMusiqueService $utilisateurMusiqueService
     * @return Response
     */
    #[Route('/full-combo/top/{musiqueUuid}/{limit}', name: 'full_combo_top', methods: ['GET'])]
    public function topFullCombo(string $musiqueUuid, int $limit, UtilisateurMusiqueService $utilisateurMusiqueService): Response
    {
        if ($limit <= 0) {
            return $this->json(['error' => 'Le nombre de joueurs demandé doit être supérieur à 0'], 400);
        }

        try {
            $accuracy = $utilisateurMusiqueService->getTopFullComboByMusique($musiqueUuid, $limit);

            return $this->json([
                'musiqueUuid' => $musiqueUuid,
                'limit' => $limit,
                'scores' => $accuracy,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Erreur lors de la récupération du classement',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @param int $userId
     * @param UtilisateurMusiqueService $userMusicService
     * @return JsonResponse
     */
    #[Route('/score/{userId}', methods: ['GET'])]
    public function ScoresFromUserId(int $userId, UtilisateurMusiqueService $userMusicService): JsonResponse
    {
        $userMusique = $userMusicService->getByUserId($userId);
        if (!$userMusique) {
            return $this->json(['error' => 'L\'utilisateur n\'a pas effectué de parties.'], 404);
        }

        $result = array_map(function ($um) {
            return [
                'musique' => [
                    'id' => $um->getMusique()->getId(),
                    'uuid' => $um->getMusique()->getUuid(),
                    'name' => $um->getMusique()->getName(),
                ],
                'score' => $um->getScore(),
                'accuracy' => $um->getAccuracy(),
                'fullCombo' => $um->getFullCombo(),
                'playedAt' => $um->getPlayedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $userMusique);

        return $this->json([
            'scores' => $result,
        ]);
    }

    /**
     * @param int $userId
     * @param UtilisateurMusiqueService $userMusicService
     * @return JsonResponse
     */
    #[Route('/games/number-of-games/{userId}', methods: ['GET'])]
    public function NumberOfGames(int $userId, UtilisateurMusiqueService $userMusicService): JsonResponse
    {
        $numberOfGame = count($userMusicService->getUtilisateurMusiqueByUserId($userId));

        return $this->json([
            'numberOfGame' => $numberOfGame,
        ]);
    }

    /**
     * @param int $userId
     * @param UtilisateurMusiqueService $userMusicService
     * @return JsonResponse
     */
    #[Route('/games/best-scores/{userId}', methods: ['GET'])]
    public function BestScoreFromUserId(int $userId, UtilisateurMusiqueService $userMusicService): JsonResponse
    {
        $userMusique = $userMusicService->getBestScoreByUserId($userId);

        $result = array_map(function ($um) {
            return [
                'musique' => [
                    'id' => $um->getMusique()->getId(),
                    'uuid' => $um->getMusique()->getUuid(),
                    'name' => $um->getMusique()->getName(),
                ],
                'utilisateur' => [
                    'id' => $um->getUtilisateur()->getId(),
                    'username' => $um->getUtilisateur()->getUsername(),
                    'email' => $um->getUtilisateur()->getEmail(),
                ],
                'score' => $um->getScore(),
                'playedAt' => $um->getPlayedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $userMusique);

        return $this->json([
            'scores' => $result,
        ]);
    }

    /**
     * @param int $userId
     * @param UtilisateurMusiqueService $utilisateurMusiqueService
     * @return JsonResponse
     */
    #[Route('/games/average-accuracy/{userId}', methods: ['GET'])]
    public function AverageAccuracyByUserId(int $userId, UtilisateurMusiqueService $utilisateurMusiqueService) : JsonResponse
    {
        return $this->json([
            'averageAccuracy' => $utilisateurMusiqueService->getAverageAccuracyByUserId($userId),
        ]);
    }

    /**
     * @param int $userId
     * @param UtilisateurMusiqueService $utilisateurMusiqueService
     * @return JsonResponse
     */
    #[Route('/games/number-full-combo/{userId}', methods: ['GET'])]
    public function NumberOfFullComboByUserId(int $userId, UtilisateurMusiqueService $utilisateurMusiqueService) : JsonResponse
    {
        return $this->json([
            'nomberFullCombo' => $utilisateurMusiqueService->getNumberOfFullComboByUserId($userId),
        ]);
    }
}
