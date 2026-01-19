<?php

namespace App\Service;

use App\Entity\Utilisateur;
use App\Entity\UtilisateurBan;
use App\Repository\UtilisateurBanRepository;

class BanService
{
    /**
     * @param UtilisateurBanRepository $banRepository
     */
    public function __construct(
        private UtilisateurBanRepository $banRepository
    ) {}

    /**
     * @param Utilisateur $user
     * @return bool
     */
    public function isUserBanned(Utilisateur $user): bool
    {
        return $this->banRepository->findActiveBan($user) !== null;
    }

    /**
     * @param Utilisateur $user
     * @return UtilisateurBan|null
     */
    public function getActiveBan(Utilisateur $user): ?UtilisateurBan
    {
        return $this->banRepository->findActiveBan($user);
    }

    /**
     * @param Utilisateur $user
     * @param string $reason
     * @param \DateTimeImmutable|null $bannedUntil
     * @return void
     */
    public function banUser(Utilisateur $user, string $reason, ?\DateTimeImmutable $bannedUntil = null, Utilisateur $admin): void {
        // règle métier possible :
        // - empêcher double ban actif
        if ($this->banRepository->findActiveBan($user)) {
            return;
        }

        $this->banRepository->createBan($user, $reason, $bannedUntil, $admin);
    }

    /**
     * @param Utilisateur $user
     * @return void
     */
    public function unbanUser(Utilisateur $user): void
    {
        $this->banRepository->deactivateActiveBan($user);
    }
}
