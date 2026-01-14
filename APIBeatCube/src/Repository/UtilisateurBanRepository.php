<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use App\Entity\UtilisateurBan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UtilisateurBan>
 */
class UtilisateurBanRepository extends ServiceEntityRepository
{
    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UtilisateurBan::class);
    }

    /**
     * @param Utilisateur $user
     * @param string $reason
     * @param \DateTimeImmutable|null $bannedUntil
     * @return void
     */
    public function createBan(Utilisateur $user, string $reason, ?\DateTimeImmutable $bannedUntil, Utilisateur $admin): void {
        $ban = new UtilisateurBan();
        $ban->setUser($user);
        $ban->setReason($reason);
        $ban->setBannedAt(new \DateTimeImmutable());
        $ban->setBannedUntil($bannedUntil);
        $ban->setIsActive(true);
        $ban->setBannedBy($admin);

        $this->getEntityManager()->persist($ban);
        $this->getEntityManager()->flush();
    }

    /**
     * @param Utilisateur $user
     * @return void
     */
    public function deactivateActiveBan(Utilisateur $user): void
    {
        $ban = $this->findOneBy([
            'user' => $user,
            'isActive' => true,
        ]);

        if ($ban) {
            $ban->setIsActive(false);
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param Utilisateur $user
     * @return UtilisateurBan|null
     */
    public function findActiveBan(Utilisateur $user): ?UtilisateurBan
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.user = :user')
            ->andWhere('b.isActive = true')
            ->andWhere('(b.bannedUntil IS NULL OR b.bannedUntil > :now)')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    //    /**
    //     * @return UtilisateurBan[] Returns an array of UtilisateurBan objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?UtilisateurBan
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
