<?php

namespace App\Service;

use App\Entity\Success;
use App\Entity\Utilisateur;
use App\Entity\UtilisateurSuccess;
use App\Repository\UtilisateurSuccessRepository;

class UtilisateurSuccessService
{
    public function __construct(
        private UtilisateurSuccessRepository $utilisateurSuccessRepository,
        private UtilisateurService $utilisateurService,
        private SuccessService $successService,
    )
    {
    }

    public function getUtilisateurSuccessesByUserId(int $user_id) : array
    {
        return $this->utilisateurSuccessRepository->findBy(['utilisateur' => $user_id]);
    }

    public function getUtilisateurSuccessBySuccessesId(int $id): array
    {
        return $this->utilisateurSuccessRepository->findBy(['success' => $id]);
    }

    public function saveUtilisateurSuccess(int $userId, int $successId): array
    {
        // Récupérer l'utilisateur
        $utilisateur = $this->utilisateurService->getUserById($userId);
        if (!$utilisateur) {
            return [
                'success' => false,
                'error' => 'Utilisateur non trouvé'
            ];
        }

        // Récupérer le succès
        $success = $this->successService->getSuccessById($successId);
        if (!$success) {
            return [
                'success' => false,
                'error' => 'Succès non trouvé'
            ];
        }

        // Vérifier si l'utilisateur a déjà obtenu ce succès
        $existingUserSuccess = $this->utilisateurSuccessRepository->findOneBy([
            'utilisateur' => $utilisateur,
            'success' => $success
        ]);

        if ($existingUserSuccess) {
            return [
                'success' => false,
                'error' => 'Succès déjà obtenu',
                'obtainedAt' => $existingUserSuccess->getObtainedAt()->format('Y-m-d H:i:s')
            ];
        }

        // Créer l'enregistrement du succès
        $utilisateurSuccess = new UtilisateurSuccess();
        $utilisateurSuccess->setUtilisateur($utilisateur);
        $utilisateurSuccess->setSuccess($success);
        $utilisateurSuccess->setObtainedAt(new \DateTimeImmutable());

        $this->utilisateurSuccessRepository->save($utilisateurSuccess, true);

        return [
            'success' => true,
            'message' => 'Succès débloqué !',
            'successId' => $success->getId(),
            'successName' => $success->getName(),
            'successDescription' => $success->getDescription(),
            'obtainedAt' => $utilisateurSuccess->getObtainedAt()->format('Y-m-d H:i:s')
        ];
    }

}
