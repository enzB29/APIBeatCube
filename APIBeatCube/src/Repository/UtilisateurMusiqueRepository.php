<?php

namespace App\Repository;

use App\Entity\UtilisateurMusique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UtilisateurMusiqueRepository extends ServiceEntityRepository
{
    /**
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UtilisateurMusique::class);
    }

    /**
     * @param UtilisateurMusique $entity
     * @param bool $flush
     * @return void
     */
    public function save(UtilisateurMusique $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param int $userId
     * @param int $limit
     * @return array
     */
    // Récupérer les meilleurs scores d'un utilisateur
    public function findBestScoresByUser(int $userId, int $limit = 10): array
    {
        return $this->createQueryBuilder('um')
            ->andWhere('um.utilisateur = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('um.score', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $musiqueId
     * @param int $limit
     * @return array
     */
    // Récupérer les meilleurs scores pour une musique
    public function findBestScoresByMusique(int $musiqueId, int $limit = 10): array
    {
        return $this->createQueryBuilder('um')
            ->andWhere('um.musique = :musiqueId')
            ->setParameter('musiqueId', $musiqueId)
            ->orderBy('um.score', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findBestAccuracyByMusique(int $musiqueId, int $limit = 10): array
    {
        return $this->createQueryBuilder('um')
            ->andWhere('um.musique = :musiqueId')
            ->setParameter('musiqueId', $musiqueId)
            ->orderBy('um.accuracy', 'DESC')
            ->addOrderBy('um.score', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $musiqueId
     * @param int $limit
     * @return array
     */
    public function findBestFullComboByMusique(int $musiqueId, int $limit = 10): array
    {
        return $this->createQueryBuilder('um')
            ->andWhere('um.musique = :musiqueId')
            ->setParameter('musiqueId', $musiqueId)
            ->orderBy('um.fullCombo', 'DESC')
            ->addOrderBy('um.score', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int $userId
     * @param int $musiqueId
     * @return UtilisateurMusique|null
     */
    // Récupérer le meilleur score d'un utilisateur sur une musique
    public function findBestScoreForUserAndMusique(int $userId, int $musiqueId): ?UtilisateurMusique
    {
        return $this->createQueryBuilder('um')
            ->andWhere('um.utilisateur = :userId')
            ->andWhere('um.musique = :musiqueId')
            ->setParameter('userId', $userId)
            ->setParameter('musiqueId', $musiqueId)
            ->orderBy('um.score', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
