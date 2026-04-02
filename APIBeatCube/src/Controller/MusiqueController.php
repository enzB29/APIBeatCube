<?php

namespace App\Controller;

use App\Repository\UtilisateurRepository;
use App\Service\JwtService;
use App\Service\MusiqueService;
use App\Service\UploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;


#[Route('/api/musique')]
class MusiqueController extends AbstractController
{
    private string $tmpDir;

    /**
     *
     */
    public function __construct()
    {
        $this->tmpDir = __DIR__ . '/../../var/tmp_music'; // dossier temporaire
        if (!file_exists($this->tmpDir)) {
            mkdir($this->tmpDir, 0777, true);
        }
    }

    /**
     * @param Request $request
     * @param MusiqueService $musiqueService
     * @param JwtService $jwt
     * @param UtilisateurRepository $utilisateurRepository
     * @return Response
     */
    #[Route('/upload', name: 'musique_upload', methods: ['POST'])]
    public function upload(Request $request, MusiqueService $musiqueService, JwtService $jwt, UtilisateurRepository $utilisateurRepository): Response
    {
        // --- JWT ---
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->json(['error' => 'Token manquant'], 401);
        }

        $payload = $jwt->verify($matches[1]);
        if (!$payload) {
            return $this->json(['error' => 'Token invalide'], 401);
        }

        $file = $request->files->get('file');
        $name = $request->request->get('name');
        $singer = $request->request->get('singer');
        $year = $request->request->get('year');

        if (!$file) {
            return $this->json(['error' => 'Aucun fichier uploadé'], 400);
        }
        if ($file->getClientOriginalExtension() !== 'mp3') {
            return $this->json(['error' => 'Seuls les fichiers .mp3 sont acceptés'], 400);
        }
        if (!$name || !$singer || !$year || !is_numeric($year)) {
            return $this->json(['error' => 'name, singer et year sont requis et year doit être un entier'], 400);
        }

        $user = $utilisateurRepository->findOneBy(['id' => $payload['id']]);
        try {
            $result = $musiqueService->handleUpload($file, $name, $singer, (int)$year, $user);

            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de l\'upload', 'exception' => $e->getMessage()], 500);
        }
    }

    #[Route('/admin/uploadBy/{userId}', name: 'music_uploadBy_UserId', methods: ['GET'])]
    public function UploadByUserId(int $userId,Request $request, UploadService $uploadService, JwtService $jwt): Response
    {
        // Vérifier le token
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->json(['error' => 'Token manquant'], 401);
        }

        $token = $matches[1];
        $payload = $jwt->verify($token);

        if (!$payload) {
            return $this->json(['error' => 'Token invalide'], 401);
        }

        // Vérifier si l'utilisateur a le rôle ROLE_ADMIN
        $roles = $payload['roles'] ?? [];
        if (!in_array('ROLE_ADMIN', $roles)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $uploads = $uploadService->getUploadsByUserId($userId);

        $result = array_map(function ($um) {
            return [
                'musique' => [
                    'id' => $um->getMusique()->getId(),
                    'uuid' => $um->getMusique()->getUuid(),
                    'name' => $um->getMusique()->getName(),
                    'singer' => $um->getMusique()->getSinger(),
                ],
                'utilisateur' => [
                    'id' => $um->getUtilisateur()->getId(),
                    'username' => $um->getUtilisateur()->getUsername(),
                    'email' => $um->getUtilisateur()->getEmail(),
                ],
                'uploadAt' => $um->getUploadAt()?->format('Y-m-d H:i:s'),
            ];
        }, $uploads);

        return $this->json([
            'uploads' => $result,
        ]);
    }

    #[Route('/my-uploads', name: 'music_MyUpload', methods: ['GET'])]
    public function MyUploads(Request $request, UploadService $uploadService, JwtService $jwt): Response
    {
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->json(['error' => 'Token manquant'], 401);
        }

        $token = $matches[1];
        $payload = $jwt->verify($token);

        if (!$payload) {
            return $this->json(['error' => 'Token invalide'], 401);
        }

        $id = $payload['id'];
        $uploads = $uploadService->getUploadsByUserId($id);

        $result = [];

        foreach ($uploads as $um) {
            $musique = $um->getMusique();

            if (!$musique) {
                continue;
            }

            // Vérifier que le fichier existe encore dans /var/tmp_music
            $filePath = '/var/tmp_music/' . $musique->getUuid();
            if (!file_exists($filePath)) {
                continue;
            }

            $result[] = [
                'musique' => [
                    'id'     => $musique->getId(),
                    'uuid'   => $musique->getUuid(),
                    'name'   => $musique->getName(),
                    'singer' => $musique->getSinger(),
                ],
                'uploadAt' => $um->getUploadAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json([
            'uploads' => $result,
        ]);
    }

    /**
     * @param string $fileId
     * @return Response
     */
    #[Route('/download/{fileId}', methods: ['GET'])]
    public function download(string $fileId): Response
    {
        // Nettoyer TOUS les buffers de sortie
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $filePath = $this->tmpDir . '/' . basename($fileId);

        if (!file_exists($filePath)) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $response = new BinaryFileResponse($filePath);

        // Headers essentiels pour un fichier binaire
        $response->headers->set('Content-Type', 'audio/mpeg'); // Spécifie le type MIME
        $response->headers->set('Content-Length', filesize($filePath)); // Taille exacte
        $response->headers->set('Accept-Ranges', 'bytes'); // Support du streaming

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($fileId)
        );

        // Suppression automatique après envoi
        $response->deleteFileAfterSend(true);

        return $response;
    }

    /**
     * @param string $identifier
     * @param Request $request
     * @param JwtService $jwt
     * @param MusiqueService $musiqueService
     * @return Response
     */
    #[Route('/delete/{identifier}', name: 'musique_delete', methods: ['DELETE'])]
    public function delete(string $identifier, Request $request, JwtService $jwt, MusiqueService $musiqueService): Response
    {
        // Vérifier le token
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->json(['error' => 'Token manquant'], 401);
        }

        $token = $matches[1];
        $payload = $jwt->verify($token);

        if (!$payload) {
            return $this->json(['error' => 'Token invalide'], 401);
        }

        // Vérifier si l'utilisateur a le rôle ROLE_ADMIN
        $roles = $payload['roles'] ?? [];
        if (!in_array('ROLE_ADMIN', $roles)) {
            return $this->json(['error' => 'Accès refusé. Seuls les administrateurs peuvent supprimer des musiques.'], 403);
        }

        try {
            $result = $musiqueService->deleteMusique($identifier);

            if (!$result['success']) {
                return $this->json($result, 404);
            }

            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression', 'exception' => $e->getMessage()], 500);
        }
    }

    /**
     * @param MusiqueService $musiqueService
     * @return Response
     */
    #[Route('/all-musics', methods: ['GET'])]
    public function allMusics(MusiqueService $musiqueService): Response
    {
        return $this->json([
            'allMusics' => $musiqueService->getAllMusique()
        ]);
    }
}
