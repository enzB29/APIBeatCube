<?php
namespace App\Controller;

use App\Entity\UtilisateurMusique;
use App\Repository\UtilisateurMusiqueRepository;
use App\Repository\UtilisateurRepository;
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

        // Validation
        if (!$musiqueUuid || !is_numeric($score) || $score < 0) {
            return $this->json(['error' => 'musiqueUuid et score (entier positif) sont requis'], 400);
        }

        // L'userId vient du token JWT (sécurisé)
        $userId = $payload['id'];

        try {
            $result = $utilisateurMusiqueService->saveScore($userId, $musiqueUuid, (int)$score);

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

    #[Route('/score/myscores', methods: ['GET'])]
    public function myScores(UtilisateurMusiqueRepository $repo, JwtService $jwt, Request $request): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->json(['error' => 'Token manquant'], 401);
        }

        $token = $matches[1];
        $payload = $jwt->verify($token);

        if (!$payload) {
            return $this->json(['error' => 'Token invalide'], 401);
        }

        $id = $payload['id'];

        $userMusique = $repo->findBy(['utilisateur' => $id]);

        $result = array_map(function ($um) {
            return [
                'musique' => [
                    'id' => $um->getMusique()->getId(),
                    'uuid' => $um->getMusique()->getUuid(),
                    'name' => $um->getMusique()->getName(),
                ],
                'score' => $um->getScore(),
                'playedAt' => $um->getPlayedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $userMusique);

        return $this->json([
            'scores' => $result,
        ]);
    }

    #[Route('/score/admin/{userId}', methods: ['GET'])]
    public function ScoresFromUserIdForAdmin(int $userId, Request $request, JwtService $jwt, UtilisateurMusiqueRepository $userMusicRepo): JsonResponse
    {
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
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $userMusique = $userMusicRepo->findBy(['utilisateur' => $userId]);
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
                'playedAt' => $um->getPlayedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $userMusique);

        return $this->json([
            'scores' => $result,
        ]);
    }

    #[Route('/games/my-number-of-games', methods: ['GET'])]
    public function NumberOfGames(Request $request, JwtService $jwt, UtilisateurMusiqueService $userMusicService): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->json(['error' => 'Token manquant'], 401);
        }

        $token = $matches[1];
        $payload = $jwt->verify($token);

        if (!$payload) {
            return $this->json(['error' => 'Token invalide'], 401);
        }

        $id = $payload['id'];

        $numberOfGame = count($userMusicService->getUtilisateurMusiqueByUserId($id));

        return $this->json([
            'numberOfGame' => $numberOfGame,
        ]);
    }
}
