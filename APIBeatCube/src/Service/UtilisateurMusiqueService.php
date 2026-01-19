<?php

namespace App\Service;

use App\Entity\UtilisateurMusique;
use App\Repository\UtilisateurMusiqueRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\MusiqueRepository;

class UtilisateurMusiqueService
{
    /**
     * @param UtilisateurMusiqueRepository $utilisateurMusiqueRepository
     * @param UtilisateurRepository $utilisateurRepository
     * @param MusiqueRepository $musiqueRepository
     */
    public function __construct(
        private UtilisateurMusiqueRepository $utilisateurMusiqueRepository,
        private UtilisateurRepository        $utilisateurRepository,
        private MusiqueRepository            $musiqueRepository
    )
    {
    }

    /**
     * @param int $userId
     * @param string $musiqueUuid
     * @param int $score
     * @param float $accuracy
     * @param int $fullCombo
     * @return array
     */
    public function saveScore(int $userId, string $musiqueUuid, int $score, float $accuracy, int $fullCombo): array
    {
        // Récupérer l'utilisateur
        $utilisateur = $this->utilisateurRepository->find($userId);
        if (!$utilisateur) {
            return [
                'success' => false,
                'error' => 'Utilisateur non trouvé'
            ];
        }

        // Récupérer la musique par UUID
        $musique = $this->musiqueRepository->findOneBy(['uuid' => $musiqueUuid]);
        if (!$musique) {
            return [
                'success' => false,
                'error' => 'Musique non trouvée'
            ];
        }

        // Créer l'enregistrement du score
        $utilisateurMusique = new UtilisateurMusique();
        $utilisateurMusique->setUtilisateur($utilisateur);
        $utilisateurMusique->setMusique($musique);
        $utilisateurMusique->setScore($score);
        $utilisateurMusique->setPlayedAt(new \DateTime());
        $utilisateurMusique->setAccuracy($accuracy);
        $utilisateurMusique->setFullCombo($fullCombo);

        $this->utilisateurMusiqueRepository->save($utilisateurMusique, true);

        // Vérifier si c'est un nouveau record personnel
        $bestScore = $this->utilisateurMusiqueRepository->findBestScoreForUserAndMusique($userId, $musique->getId());

        $isNewRecord = $bestScore && $bestScore->getId() === $utilisateurMusique->getId();

        return [
            'success' => true,
            'scoreId' => $utilisateurMusique->getId(),
            'score' => $score,
            'playedAt' => $utilisateurMusique->getPlayedAt()->format('Y-m-d H:i:s'),
            'isNewRecord' => $isNewRecord,
            'musique' => [
                'id' => $musique->getId(),
                'uuid' => $musique->getUuid(),
                'name' => $musique->getName(),
                'singer' => $musique->getSinger()
            ],
            'message' => $isNewRecord ? 'Nouveau record personnel !' : 'Score enregistré'
        ];
    }

    /**
     * @param string $musiqueUuid
     * @param int $limit
     * @return array
     * @throws \Exception
     */
    public function getTopScoresByMusique(string $musiqueUuid, int $limit): array
    {
        $musique = $this->musiqueRepository->findOneBy(['uuid' => $musiqueUuid]);

        if (!$musique) {
            throw new \Exception('Musique introuvable');
        }

        $scores = $this->utilisateurMusiqueRepository->findBestScoresByMusique($musique->getId(), $limit);

        return array_map(function ($userMusique) {
            return [
                'userId' => $userMusique->getUtilisateur()->getId(),
                'pseudo' => $userMusique->getUtilisateur()->getUsername(),
                'score' => $userMusique->getScore(),
                'accuracy' => $userMusique->getAccuracy(),
                'fullCombo' => $userMusique->getFullCombo(),
            ];
        }, $scores);
    }

    /**
     * @param string $musiqueUuid
     * @param int $limit
     * @return array
     * @throws \Exception
     */
    public function getTopAccuracyByMusique(string $musiqueUuid, int $limit): array
    {
        $musique = $this->musiqueRepository->findOneBy(['uuid' => $musiqueUuid]);

        if (!$musique) {
            throw new \Exception('Musique introuvable');
        }

        $accuracy = $this->utilisateurMusiqueRepository->findBestAccuracyByMusique($musique->getId(), $limit);

        return array_map(function ($userMusique) {
            return [
                'userId' => $userMusique->getUtilisateur()->getId(),
                'pseudo' => $userMusique->getUtilisateur()->getUsername(),
                'accuracy' => $userMusique->getAccuracy(),
                'score' => $userMusique->getScore(),
                'fullCombo' => $userMusique->getFullCombo(),
            ];
        }, $accuracy);
    }

    /**
     * @param string $musiqueUuid
     * @param int $limit
     * @return array
     * @throws \Exception
     */
    public function getTopFullComboByMusique(string $musiqueUuid, int $limit): array
    {
        $musique = $this->musiqueRepository->findOneBy(['uuid' => $musiqueUuid]);

        if (!$musique) {
            throw new \Exception('Musique introuvable');
        }

        $accuracy = $this->utilisateurMusiqueRepository->findBestFullComboByMusique($musique->getId(), $limit);

        return array_map(function ($userMusique) {
            return [
                'userId' => $userMusique->getUtilisateur()->getId(),
                'pseudo' => $userMusique->getUtilisateur()->getUsername(),
                'fullCombo' => $userMusique->getFullCombo(),
                'accuracy' => $userMusique->getAccuracy(),
                'score' => $userMusique->getScore(),
            ];
        }, $accuracy);
    }

    /**
     * @param int $userId
     * @return array
     */
    public function getUtilisateurMusiqueByUserId(int $userId): array
    {
        return $this->utilisateurMusiqueRepository->findBy(['utilisateur' => $userId]);
    }

    /**
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getBestScoreByUserId(int $userId, int $limit = 10): array
    {
        return $this->utilisateurMusiqueRepository->findBestScoresByUser($userId, $limit);
    }

    /**
     * @param int $userId
     * @return array
     */
    public function getByUserId(int $userId): array
    {
        return $this->utilisateurMusiqueRepository->findBy(['utilisateur' => $userId]);
    }

    /**
     * @param int $userId
     * @return float
     */
    public function getAverageAccuracyByUserId(int $userId): float
    {
        $scores = $this->utilisateurMusiqueRepository->findBy([
            'utilisateur' => $userId
        ]);

        if (count($scores) === 0) {
            return 0.0;
        }

        $totalAccuracy = 0;

        foreach ($scores as $score) {
            $totalAccuracy += $score->getAccuracy();
        }

        return $totalAccuracy / count($scores);
    }

    /**
     * @param int $userId
     * @return int
     */
    public function getNumberOfFullComboByUserId(int $userId): int
    {
        $scores = $this->utilisateurMusiqueRepository->findBy([
            'utilisateur' => $userId
        ]);

        if (count($scores) === 0) {
            return 0;
        }

        $totalFullCombo = 0;

        foreach ($scores as $score) {
            $totalFullCombo += $score->getFullCombo();
        }

        return $totalFullCombo;
    }

    /**
     * @param int $userId
     * @return int
     */
    public function getTotalScoreByUserId(int $userId): int
    {
        $scores = $this->utilisateurMusiqueRepository->findBy([
            'utilisateur' => $userId
        ]);

        if (count($scores) === 0) {
            return 0;
        }

        $totalScore = 0;

        foreach ($scores as $score) {
            $totalScore += $score->getScore();
        }

        return $totalScore;
    }


    /**
     * Récupère le classement global de tous les utilisateurs
     */
    public function getGlobalLeaderboard(int $limit = 100): array
    {
        $ranking = $this->utilisateurMusiqueRepository->getGlobalRanking();

        $leaderboard = [];
        $position = 1;

        foreach (array_slice($ranking, 0, $limit) as $entry) {
            $leaderboard[] = [
                'rank' => $position,
                'userId' => (int) $entry['userId'],
                'username' => $entry['username'],
                'totalScore' => (int) $entry['totalScore'],
                'gamesPlayed' => (int) $entry['gamesPlayed']
            ];

            $position++;
        }

        return $leaderboard;
    }

    /**
     * Récupère le classement d'un utilisateur spécifique
     */
    public function getUserRanking(int $userId): array
    {
        $utilisateur = $this->utilisateurRepository->find($userId);

        if (!$utilisateur) {
            return [
                'success' => false,
                'error' => 'Utilisateur non trouvé'
            ];
        }

        $rankData = $this->utilisateurMusiqueRepository->getUserGlobalRank($userId);

        if (!$rankData) {
            return [
                'success' => true,
                'userId' => $userId,
                'username' => $utilisateur->getUsername(),
                'rank' => null,
                'totalScore' => 0,
                'gamesPlayed' => 0,
                'message' => 'Aucune partie jouée'
            ];
        }

        return [
            'success' => true,
            'userId' => $userId,
            'username' => $utilisateur->getUsername(),
            'rank' => $rankData['rank'],
            'totalScore' => $rankData['totalScore'],
            'gamesPlayed' => $rankData['gamesPlayed']
        ];
    }
}
