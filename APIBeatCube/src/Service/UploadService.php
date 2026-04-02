<?php

namespace App\Service;

use App\Entity\Upload;
use App\Entity\Utilisateur;
use App\Entity\Musique;
use App\Repository\UploadRepository;

class UploadService
{
    public function __construct(
        private UploadRepository $uploadRepository
    ) {}

    public function logUpload(Utilisateur $user, Musique $musique): void
    {
        $upload = new Upload();
        $upload->setUtilisateur($user);
        $upload->setMusique($musique);
        $upload->setUploadAt(new \DateTimeImmutable());

        $this->uploadRepository->save($upload);
    }

    public function getUploadsByUserId(int $userId): array
    {
        return $this->uploadRepository->createQueryBuilder('u')
            ->where('u.utilisateur = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }
}
