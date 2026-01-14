<?php

namespace App\Service;

use App\Entity\Musique;
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
        private string $tmpDir
    ) {}

    /**
     * @param UploadedFile $file
     * @param string $name
     * @param string $singer
     * @param int $year
     * @return array
     */
    public function handleUpload(UploadedFile $file, string $name, string $singer, int $year): array
    {
        // Vérifier si la musique existe déjà (même name, singer, year)
        $existingMusique = $this->musiqueRepository->findOneBy([
            'name' => $name,
            'singer' => $singer,
            'year' => $year
        ]);

        if ($existingMusique) {
            // La musique existe déjà, on upload quand même le fichier
            $uuid = Uuid::v4()->toRfc4122();
            $filename = $uuid . '.mp3';
            $file->move($this->tmpDir, $filename);

            return [
                'fileId' => $filename,
                'uuid' => $uuid,
                'musiqueId' => $existingMusique->getId(),
                'message' => 'Musique existante utilisee, fichier upload avec nouvel UUID'
            ];
        }

        // La musique n'existe pas, on la crée
        $uuid = Uuid::v4()->toRfc4122();
        $filename = $uuid . '.mp3';

        // Déplacer le fichier dans var/tmp_music
        $file->move($this->tmpDir, $filename);

        // Créer l'entité Musique et persister via le repository
        $musique = new Musique();
        $musique->setName($name);
        $musique->setSinger($singer);
        $musique->setYear($year);
        $musique->setUuid($uuid);

        $this->musiqueRepository->save($musique, true);

        return [
            'fileId' => $filename,
            'uuid' => $uuid,
            'musiqueId' => $musique->getId(),
            'message' => 'Nouvelle musique créée'
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
