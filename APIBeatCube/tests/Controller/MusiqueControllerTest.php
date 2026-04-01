<?php

namespace App\Tests\Controller;

use App\Entity\Musique;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\JwtService;
use App\Service\MusiqueService;
use App\Service\UploadService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MusiqueControllerTest extends KernelTestCase
{
    private JwtService $jwt;
    private MusiqueService $musiqueService;
    private UploadService $uploadService;
    private UtilisateurRepository $utilisateurRepo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->jwt             = $this->createMock(JwtService::class);
        $this->musiqueService  = $this->createMock(MusiqueService::class);
        $this->uploadService   = $this->createMock(UploadService::class);
        $this->utilisateurRepo = $this->createMock(UtilisateurRepository::class);
    }

    // =========================================================================
    // UPLOAD
    // =========================================================================

    public function testUploadMissingToken(): void
    {
        $r = $this->ctrl()->upload(new Request(), $this->musiqueService, $this->jwt, $this->utilisateurRepo);
        $this->assertEquals(401, $r->getStatusCode());
        $this->assertKey($r, 'error', 'Token manquant');
    }

    public function testUploadInvalidToken(): void
    {
        $req = new Request();
        $req->headers->set('Authorization', 'Bearer bad.token');
        $this->jwt->method('verify')->willReturn(null);
        $r = $this->ctrl()->upload($req, $this->musiqueService, $this->jwt, $this->utilisateurRepo);
        $this->assertEquals(401, $r->getStatusCode());
    }

    public function testUploadNoFile(): void
    {
        $req = new Request([], ['name' => 'Song', 'singer' => 'Artist', 'year' => '2020']);
        $req->headers->set('Authorization', 'Bearer valid');
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $r = $this->ctrl()->upload($req, $this->musiqueService, $this->jwt, $this->utilisateurRepo);
        $this->assertEquals(400, $r->getStatusCode());
        $this->assertKey($r, 'error', 'Aucun fichier uploadé');
    }

    public function testUploadNonMp3File(): void
    {
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $tmpFile = $this->makeTmpFile('.wav');
        $file    = new UploadedFile($tmpFile, 'song.wav', 'audio/wav', null, true);
        $req     = Request::create('/', 'POST', ['name' => 'Song', 'singer' => 'Artist', 'year' => '2020']);
        $req->files->set('file', $file);
        $req->headers->set('Authorization', 'Bearer valid');

        $r = $this->ctrl()->upload($req, $this->musiqueService, $this->jwt, $this->utilisateurRepo);
        $this->assertEquals(400, $r->getStatusCode());
        $this->assertKey($r, 'error', 'Seuls les fichiers .mp3 sont acceptés');
        unlink($tmpFile);
    }

    public function testUploadMissingMetadata(): void
    {
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $tmpFile = $this->makeTmpFile('.mp3');
        $file    = new UploadedFile($tmpFile, 'song.mp3', 'audio/mpeg', null, true);
        $req     = Request::create('/', 'POST', ['name' => 'Song']); // singer + year manquants
        $req->files->set('file', $file);
        $req->headers->set('Authorization', 'Bearer valid');

        $r = $this->ctrl()->upload($req, $this->musiqueService, $this->jwt, $this->utilisateurRepo);
        $this->assertEquals(400, $r->getStatusCode());
        unlink($tmpFile);
    }

    public function testUploadNonNumericYear(): void
    {
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $tmpFile = $this->makeTmpFile('.mp3');
        $file    = new UploadedFile($tmpFile, 'song.mp3', 'audio/mpeg', null, true);
        $req     = Request::create('/', 'POST', ['name' => 'Song', 'singer' => 'Artist', 'year' => 'notanumber']);
        $req->files->set('file', $file);
        $req->headers->set('Authorization', 'Bearer valid');

        $r = $this->ctrl()->upload($req, $this->musiqueService, $this->jwt, $this->utilisateurRepo);
        $this->assertEquals(400, $r->getStatusCode());
        unlink($tmpFile);
    }

    public function testUploadSuccess(): void
    {
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $this->utilisateurRepo->method('findOneBy')->willReturn(new Utilisateur());
        $this->musiqueService->method('handleUpload')
            ->willReturn(['success' => true, 'uuid' => 'abc-123', 'name' => 'Song']);

        $tmpFile = $this->makeTmpFile('.mp3');
        $file    = new UploadedFile($tmpFile, 'song.mp3', 'audio/mpeg', null, true);
        $req     = Request::create('/', 'POST', ['name' => 'Song', 'singer' => 'Artist', 'year' => '2020']);
        $req->files->set('file', $file);
        $req->headers->set('Authorization', 'Bearer valid');

        $r = $this->ctrl()->upload($req, $this->musiqueService, $this->jwt, $this->utilisateurRepo);
        $this->assertEquals(200, $r->getStatusCode());
        $body = json_decode($r->getContent(), true);
        $this->assertTrue($body['success']);
        unlink($tmpFile);
    }

    public function testUploadServiceThrows(): void
    {
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $this->utilisateurRepo->method('findOneBy')->willReturn(new Utilisateur());
        $this->musiqueService->method('handleUpload')->willThrowException(new \Exception('Storage failure'));

        $tmpFile = $this->makeTmpFile('.mp3');
        $file    = new UploadedFile($tmpFile, 'song.mp3', 'audio/mpeg', null, true);
        $req     = Request::create('/', 'POST', ['name' => 'Song', 'singer' => 'Artist', 'year' => '2020']);
        $req->files->set('file', $file);
        $req->headers->set('Authorization', 'Bearer valid');

        $r = $this->ctrl()->upload($req, $this->musiqueService, $this->jwt, $this->utilisateurRepo);
        $this->assertEquals(500, $r->getStatusCode());
        unlink($tmpFile);
    }

    // =========================================================================
    // UPLOAD BY USER ID (admin)
    // =========================================================================

    public function testUploadByUserIdMissingToken(): void
    {
        $r = $this->ctrl()->UploadByUserId(1, new Request(), $this->uploadService, $this->jwt);
        $this->assertEquals(401, $r->getStatusCode());
    }

    public function testUploadByUserIdInvalidToken(): void
    {
        $req = new Request();
        $req->headers->set('Authorization', 'Bearer bad');
        $this->jwt->method('verify')->willReturn(null);
        $r = $this->ctrl()->UploadByUserId(1, $req, $this->uploadService, $this->jwt);
        $this->assertEquals(401, $r->getStatusCode());
    }

    public function testUploadByUserIdForbiddenForNonAdmin(): void
    {
        $req = new Request();
        $req->headers->set('Authorization', 'Bearer valid');
        $this->jwt->method('verify')->willReturn(['id' => 1, 'roles' => ['ROLE_USER']]);
        $r = $this->ctrl()->UploadByUserId(1, $req, $this->uploadService, $this->jwt);
        $this->assertEquals(403, $r->getStatusCode());
    }

    public function testUploadByUserIdEmptyResult(): void
    {
        $req = new Request();
        $req->headers->set('Authorization', 'Bearer valid');
        $this->jwt->method('verify')->willReturn(['id' => 1, 'roles' => ['ROLE_ADMIN']]);
        $this->uploadService->method('getUploadsByUserId')->willReturn([]);
        $r = $this->ctrl()->UploadByUserId(99, $req, $this->uploadService, $this->jwt);
        $this->assertEquals(200, $r->getStatusCode());
        $body = json_decode($r->getContent(), true);
        $this->assertEmpty($body['uploads']);
    }

    public function testUploadByUserIdSuccess(): void
    {
        $req = new Request();
        $req->headers->set('Authorization', 'Bearer valid');
        $this->jwt->method('verify')->willReturn(['id' => 1, 'roles' => ['ROLE_ADMIN']]);
        $this->uploadService->method('getUploadsByUserId')
            ->willReturn([$this->makeUploadMock()]);
        $r = $this->ctrl()->UploadByUserId(1, $req, $this->uploadService, $this->jwt);
        $this->assertEquals(200, $r->getStatusCode());
        $body = json_decode($r->getContent(), true);
        $this->assertCount(1, $body['uploads']);
    }

    // =========================================================================
    // MY UPLOADS
    // =========================================================================

    public function testMyUploadsMissingToken(): void
    {
        $r = $this->ctrl()->MyUploads(new Request(), $this->uploadService, $this->jwt);
        $this->assertEquals(401, $r->getStatusCode());
    }

    public function testMyUploadsInvalidToken(): void
    {
        $req = new Request();
        $req->headers->set('Authorization', 'Bearer bad');
        $this->jwt->method('verify')->willReturn(null);
        $r = $this->ctrl()->MyUploads($req, $this->uploadService, $this->jwt);
        $this->assertEquals(401, $r->getStatusCode());
    }

    public function testMyUploadsEmpty(): void
    {
        $req = new Request();
        $req->headers->set('Authorization', 'Bearer valid');
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $this->uploadService->method('getUploadsByUserId')->willReturn([]);
        $r = $this->ctrl()->MyUploads($req, $this->uploadService, $this->jwt);
        $this->assertEquals(200, $r->getStatusCode());
        $body = json_decode($r->getContent(), true);
        $this->assertEmpty($body['uploads']);
    }

    public function testMyUploadsSuccess(): void
    {
        $req = new Request();
        $req->headers->set('Authorization', 'Bearer valid');
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $this->uploadService->method('getUploadsByUserId')
            ->willReturn([$this->makeUploadMock()]);
        $r = $this->ctrl()->MyUploads($req, $this->uploadService, $this->jwt);
        $this->assertEquals(200, $r->getStatusCode());
        $body = json_decode($r->getContent(), true);
        $this->assertCount(1, $body['uploads']);
    }

    // =========================================================================
    // DOWNLOAD
    // =========================================================================

    /*Code pour faire des tests sur le downoad, il est considéré comme risqué c'est alors que je le commente*/
//    public function testDownloadFileNotFound(): void
//    {
//        $this->expectOutputString('');
//        $r = $this->ctrl()->download('nonexistent_' . uniqid() . '.mp3');
//        $this->assertEquals(404, $r->getStatusCode());
//    }

    // =========================================================================
    // DELETE
    // =========================================================================

    public function testDeleteMissingToken(): void
    {
        $r = $this->ctrl()->delete('uuid-123', new Request(), $this->jwt, $this->musiqueService);
        $this->assertEquals(401, $r->getStatusCode());
    }

    public function testDeleteInvalidToken(): void
    {
        $req = new Request();
        $req->headers->set('Authorization', 'Bearer bad');
        $this->jwt->method('verify')->willReturn(null);
        $r = $this->ctrl()->delete('uuid-123', $req, $this->jwt, $this->musiqueService);
        $this->assertEquals(401, $r->getStatusCode());
    }

    public function testDeleteForbiddenForNonAdmin(): void
    {
        $req = new Request();
        $req->headers->set('Authorization', 'Bearer valid');
        $this->jwt->method('verify')->willReturn(['id' => 1, 'roles' => ['ROLE_USER']]);
        $r = $this->ctrl()->delete('uuid-123', $req, $this->jwt, $this->musiqueService);
        $this->assertEquals(403, $r->getStatusCode());
    }

    public function testDeleteNotFound(): void
    {
        $req = new Request();
        $req->headers->set('Authorization', 'Bearer valid');
        $this->jwt->method('verify')->willReturn(['id' => 1, 'roles' => ['ROLE_ADMIN']]);
        $this->musiqueService->method('deleteMusique')
            ->willReturn(['success' => false, 'error' => 'Not found']);
        $r = $this->ctrl()->delete('uuid-999', $req, $this->jwt, $this->musiqueService);
        $this->assertEquals(404, $r->getStatusCode());
    }

    public function testDeleteSuccess(): void
    {
        $req = new Request();
        $req->headers->set('Authorization', 'Bearer valid');
        $this->jwt->method('verify')->willReturn(['id' => 1, 'roles' => ['ROLE_ADMIN']]);
        $this->musiqueService->method('deleteMusique')->willReturn(['success' => true]);
        $r = $this->ctrl()->delete('uuid-123', $req, $this->jwt, $this->musiqueService);
        $this->assertEquals(200, $r->getStatusCode());
    }

    public function testDeleteServiceThrows(): void
    {
        $req = new Request();
        $req->headers->set('Authorization', 'Bearer valid');
        $this->jwt->method('verify')->willReturn(['id' => 1, 'roles' => ['ROLE_ADMIN']]);
        $this->musiqueService->method('deleteMusique')->willThrowException(new \Exception('DB error'));
        $r = $this->ctrl()->delete('uuid-123', $req, $this->jwt, $this->musiqueService);
        $this->assertEquals(500, $r->getStatusCode());
    }

    // =========================================================================
    // ALL MUSICS
    // =========================================================================

    public function testAllMusicsEmpty(): void
    {
        $this->musiqueService->method('getAllMusique')->willReturn([]);
        $r = $this->ctrl()->allMusics($this->musiqueService);
        $this->assertEquals(200, $r->getStatusCode());
        $body = json_decode($r->getContent(), true);
        $this->assertEmpty($body['allMusics']);
    }

    public function testAllMusicsReturnsList(): void
    {
        $this->musiqueService->method('getAllMusique')->willReturn([
            ['id' => 1, 'name' => 'Song A'],
            ['id' => 2, 'name' => 'Song B'],
        ]);
        $r = $this->ctrl()->allMusics($this->musiqueService);
        $this->assertEquals(200, $r->getStatusCode());
        $body = json_decode($r->getContent(), true);
        $this->assertCount(2, $body['allMusics']);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function ctrl(): \App\Controller\MusiqueController
    {
        $c = new \App\Controller\MusiqueController();
        $c->setContainer(static::getContainer());
        return $c;
    }

    private function makeTmpFile(string $ext): string
    {
        $path = tempnam(sys_get_temp_dir(), 'test') . $ext;
        file_put_contents($path, 'fake content');
        return $path;
    }

    /**
     * Construit un mock UtilisateurMusique avec un objet stdClass
     * pour contourner les problèmes de méthodes manquantes (getUploadAt, etc.)
     */
    private function makeUploadMock(): object
    {
        $musique = new Musique();
        $musique->setName('Test Song');
        $musique->setSinger('Artist');
        $musique->setUuid('uuid-test-' . uniqid());

        $user = new Utilisateur();
        $user->setUsername('bob');
        $user->setEmail('bob@test.com');
        $user->setCreatedAt(new \DateTimeImmutable());

        // Objet anonyme évite createMock() + problèmes de méthodes inconnues
        return new class($musique, $user) {
            public function __construct(
                private Musique $musique,
                private Utilisateur $user
            ) {}
            public function getMusique(): Musique { return $this->musique; }
            public function getUtilisateur(): Utilisateur { return $this->user; }
            public function getUploadAt(): ?\DateTimeImmutable { return new \DateTimeImmutable(); }
        };
    }

    private function assertKey(JsonResponse $r, string $key, string $value): void
    {
        $body = json_decode($r->getContent(), true);
        $this->assertArrayHasKey($key, $body, "Clé '$key' absente de : " . $r->getContent());
        $this->assertEquals($value, $body[$key]);
    }
}
