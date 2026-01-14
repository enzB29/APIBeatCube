<?php
namespace App\Controller;

use App\Repository\MusiqueRepository;
use App\Service\UtilisateurMusiqueService;
use App\Service\JwtService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/score')]
class UtilisateurMusiqueController extends AbstractController
{
    /**
     * @param Request $request
     * @param JwtService $jwt
     * @param UtilisateurMusiqueService $utilisateurMusiqueService
     * @return Response
     */
    #[Route('/save', name: 'score_save', methods: ['POST'])]
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
    #[Route('/top/{musiqueUuid}/{limit}', name: 'score_top', methods: ['GET'])]
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
}
