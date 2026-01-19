<?php

namespace App\Service;

use App\Entity\Musique;
use App\Entity\Utilisateur;
use App\Repository\MusiqueRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

class MusiqueService
{
    /**
     * @param MusiqueRepository $musiqueRepository
     * @param string $tmpDir
     */
    public function __construct(
        private MusiqueRepository $musiqueRepository,
        private string $tmpDir,
        private UploadService $uploadService
    ) {}

    /**
     * @param UploadedFile $file
     * @param string $name
     * @param string $singer
     * @param int $year
     * @param Utilisateur $user
     * @return array
     */
    public function handleUpload(
        UploadedFile $file,
        string $name,
        string $singer,
        int $year,
        Utilisateur $user
    ): array {
        // Générer UUID pour le fichier (toujours unique)
        $uuid = Uuid::v4()->toRfc4122();
        $filename = $uuid . '.mp3';

        // Déplacer le fichier
        $file->move($this->tmpDir, $filename);

        // Vérifier si la musique existe déjà
        $musique = $this->musiqueRepository->findOneBy([
            'name' => $name,
            'singer' => $singer,
            'year' => $year
        ]);

        $isNewMusique = false;

        if (!$musique) {
            // La musique n'existe pas → création
            $musique = new Musique();
            $musique->setName($name);
            $musique->setSinger($singer);
            $musique->setYear($year);
            $musique->setUuid($uuid);

            $this->musiqueRepository->save($musique, true);
            $isNewMusique = true;
        }

        // 🔹 LOG UPLOAD (TOUJOURS)
        $this->uploadService->logUpload($user, $musique);

        return [
            'fileId' => $filename,
            'uuid' => $uuid,
            'musiqueId' => $musique->getId(),
            'message' => $isNewMusique
                ? 'Nouvelle musique créée'
                : 'Musique existante utilisée, fichier upload avec nouvel UUID'
        ];
    }

    /**
     * @param string $identifier
     * @return array
     */
    public function deleteMusique(string $identifier): array
    {
        // Chercher par ID ou UUID
        if (is_numeric($identifier)) {
            $musique = $this->musiqueRepository->find((int)$identifier);
        } else {
            $musique = $this->musiqueRepository->findOneBy(['uuid' => $identifier]);
        }

        if (!$musique) {
            return [
                'success' => false,
                'error' => 'Musique non trouvée'
            ];
        }

        // Supprimer le fichier associé s'il existe
        $filename = $musique->getUuid() . '.mp3';
        $filepath = $this->tmpDir . '/' . $filename;

        $fileDeleted = false;
        if (file_exists($filepath)) {
            unlink($filepath);
            $fileDeleted = true;
        }

        // Supprimer l'entrée en base
        $musiqueId = $musique->getId();
        $musiqueName = $musique->getName();

        $this->musiqueRepository->remove($musique, true);

        return [
            'success' => true,
            'musiqueId' => $musiqueId,
            'name' => $musiqueName,
            'fileDeleted' => $fileDeleted,
            'message' => 'Musique supprimée avec succès'
        ];
    }
}
