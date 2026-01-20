<?php

namespace App\Service;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;

class UtilisateurService
{
    public function __construct(
        private UtilisateurRepository $utilisateurRepository
    ) {}

    public function updateUsername(int $userId, string $newUsername): array
    {
        // Récupérer l'utilisateur
        $utilisateur = $this->utilisateurRepository->findOneBy(['id' => $userId]);

        if (!$utilisateur) {
            return [
                'success' => false,
                'error' => 'Utilisateur non trouvé'
            ];
        }

        // Vérifier si le nouveau username est différent de l'ancien
        if ($utilisateur->getUsername() === $newUsername) {
            return [
                'success' => false,
                'error' => 'Le nouveau username est identique à l\'ancien'
            ];
        }

        // Vérifier si le username est déjà pris par un autre utilisateur
        $existingUser = $this->utilisateurRepository->findOneBy(['username' => $newUsername]);

        if ($existingUser && $existingUser->getId() !== $userId) {
            return [
                'success' => false,
                'error' => 'Ce username est déjà pris'
            ];
        }

        // Mettre à jour le username
        $oldUsername = $utilisateur->getUsername();
        $utilisateur->setUsername($newUsername);

        $this->utilisateurRepository->save($utilisateur, true);

        return [
            'success' => true,
            'message' => 'Username mis à jour avec succès',
            'oldUsername' => $oldUsername,
            'newUsername' => $newUsername,
            'userId' => $userId
        ];
    }

    public function getUserById(int $userId): ?Utilisateur
    {
        return $this->utilisateurRepository->findOneBy(['id' => $userId]);
    }
}
