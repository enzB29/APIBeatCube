<?php

namespace App\Tests\Controller;

use App\Entity\Success;
use App\Entity\Utilisateur;
use App\Service\JwtService;
use App\Service\SuccessService;
use App\Service\UtilisateurSuccessService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

// =============================================================================
// SuccessController
// =============================================================================

class SuccessControllerTest extends KernelTestCase
{
    private SuccessService $successService;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->successService = $this->createMock(SuccessService::class);
    }

    public function testAllSuccessesReturnsEmptyList(): void
    {
        $this->successService->method('getAllSuccess')->willReturn([]);
        $r    = $this->ctrl()->AllSuccesses($this->successService);
        $body = json_decode($r->getContent(), true);
        $this->assertEquals(200, $r->getStatusCode());
        $this->assertArrayHasKey('successes', $body);
        $this->assertEmpty($body['successes']);
    }

    public function testAllSuccessesReturnsList(): void
    {
        $this->successService->method('getAllSuccess')->willReturn([
            ['id' => 1, 'name' => 'First Blood', 'description' => 'Play your first game'],
            ['id' => 2, 'name' => 'Perfectionist', 'description' => '100% accuracy'],
        ]);
        $r    = $this->ctrl()->AllSuccesses($this->successService);
        $body = json_decode($r->getContent(), true);
        $this->assertEquals(200, $r->getStatusCode());
        $this->assertCount(2, $body['successes']);
    }

    public function testAllSuccessesHasSuccessesKey(): void
    {
        $this->successService->method('getAllSuccess')->willReturn([
            ['id' => 1, 'name' => 'Test', 'description' => 'Desc'],
        ]);
        $body = json_decode($this->ctrl()->AllSuccesses($this->successService)->getContent(), true);
        $this->assertArrayHasKey('successes', $body);
        $this->assertIsArray($body['successes']);
    }

    private function ctrl(): \App\Controller\SuccessController
    {
        $c = new \App\Controller\SuccessController();
        $c->setContainer(static::getContainer());
        return $c;
    }
}

// =============================================================================
// UtilisateurSuccessController
// =============================================================================

class UtilisateurSuccessControllerTest extends KernelTestCase
{
    private UtilisateurSuccessService $successService;
    private JwtService $jwt;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->successService = $this->createMock(UtilisateurSuccessService::class);
        $this->jwt            = $this->createMock(JwtService::class);
    }

    // =========================================================================
    // SUCCESS BY USER ID
    // =========================================================================

    public function testSuccessByUserIdEmpty(): void
    {
        $this->successService->method('getUtilisateurSuccessesByUserId')->willReturn([]);
        $r    = $this->ctrl()->SuccessByUserId(1, $this->successService);
        $body = json_decode($r->getContent(), true);
        $this->assertEquals(200, $r->getStatusCode());
        $this->assertEmpty($body['successes']);
    }

    public function testSuccessByUserIdReturnsList(): void
    {
        $us = $this->buildUserSuccessObject(1, 'alice', 1, 'First Blood', 'Play first game');
        $this->successService->method('getUtilisateurSuccessesByUserId')->willReturn([$us]);
        $r    = $this->ctrl()->SuccessByUserId(1, $this->successService);
        $body = json_decode($r->getContent(), true);
        $this->assertCount(1, $body['successes']);
    }

    public function testSuccessByUserIdResponseStructure(): void
    {
        $us = $this->buildUserSuccessObject(1, 'alice', 1, 'First Blood', 'Play first game');
        $this->successService->method('getUtilisateurSuccessesByUserId')->willReturn([$us]);
        $r    = $this->ctrl()->SuccessByUserId(1, $this->successService);
        $body = json_decode($r->getContent(), true);
        $item = $body['successes'][0];
        $this->assertArrayHasKey('utilisateur', $item);
        $this->assertArrayHasKey('success', $item);
        $this->assertArrayHasKey('obtained_at', $item);
        $this->assertEquals('alice', $item['utilisateur']['username']);
        $this->assertEquals('First Blood', $item['success']['name']);
    }

    // =========================================================================
    // USERS BY SUCCESS ID
    // =========================================================================

    public function testUsersBySuccessIdEmpty(): void
    {
        $this->successService->method('getUtilisateurSuccessBySuccessesId')->willReturn([]);
        $r    = $this->ctrl()->UsersBySuccessId(1, $this->successService);
        $body = json_decode($r->getContent(), true);
        $this->assertEmpty($body['successes']);
    }

    public function testUsersBySuccessIdReturnsList(): void
    {
        $us = $this->buildUserSuccessObject(1, 'alice', 1, 'Veteran', 'Play 100 games');
        $this->successService->method('getUtilisateurSuccessBySuccessesId')->willReturn([$us]);
        $r    = $this->ctrl()->UsersBySuccessId(1, $this->successService);
        $body = json_decode($r->getContent(), true);
        $this->assertCount(1, $body['successes']);
    }

    public function testUsersBySuccessIdMultipleUsers(): void
    {
        $us1 = $this->buildUserSuccessObject(1, 'alice', 1, 'Veteran', 'Play 100 games');
        $us2 = $this->buildUserSuccessObject(2, 'bob', 1, 'Veteran', 'Play 100 games');
        $this->successService->method('getUtilisateurSuccessBySuccessesId')->willReturn([$us1, $us2]);
        $r    = $this->ctrl()->UsersBySuccessId(1, $this->successService);
        $body = json_decode($r->getContent(), true);
        $this->assertCount(2, $body['successes']);
    }

    // =========================================================================
    // SAVE SUCCESS
    // =========================================================================

    public function testSaveSuccessMissingToken(): void
    {
        $r = $this->ctrl()->saveSuccessForConnectedUser(1, new Request(), $this->successService, $this->jwt);
        $this->assertEquals(401, $r->getStatusCode());
    }

    public function testSaveSuccessInvalidToken(): void
    {
        $req = new Request();
        $req->headers->set('Authorization', 'Bearer bad');
        $this->jwt->method('verify')->willReturn(null);
        $r = $this->ctrl()->saveSuccessForConnectedUser(1, $req, $this->successService, $this->jwt);
        $this->assertEquals(401, $r->getStatusCode());
    }

    public function testSaveSuccessUserNotFound(): void
    {
        $req = $this->makeReq();
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $this->successService->method('saveUtilisateurSuccess')
            ->willReturn(['success' => false, 'error' => 'Utilisateur non trouvé']);
        $r = $this->ctrl()->saveSuccessForConnectedUser(1, $req, $this->successService, $this->jwt);
        $this->assertEquals(404, $r->getStatusCode());
    }

    public function testSaveSuccessSuccessNotFound(): void
    {
        $req = $this->makeReq();
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $this->successService->method('saveUtilisateurSuccess')
            ->willReturn(['success' => false, 'error' => 'Succès non trouvé']);
        $r = $this->ctrl()->saveSuccessForConnectedUser(999, $req, $this->successService, $this->jwt);
        $this->assertEquals(404, $r->getStatusCode());
    }

    public function testSaveSuccessAlreadyObtained(): void
    {
        $req = $this->makeReq();
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $this->successService->method('saveUtilisateurSuccess')
            ->willReturn(['success' => false, 'error' => 'Succès déjà obtenu']);
        $r = $this->ctrl()->saveSuccessForConnectedUser(1, $req, $this->successService, $this->jwt);
        $this->assertEquals(409, $r->getStatusCode());
    }

    public function testSaveSuccessOk(): void
    {
        $req = $this->makeReq();
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $this->successService->method('saveUtilisateurSuccess')
            ->willReturn(['success' => true, 'successId' => 1]);
        $r = $this->ctrl()->saveSuccessForConnectedUser(1, $req, $this->successService, $this->jwt);
        $this->assertEquals(201, $r->getStatusCode());
    }

    public function testSaveSuccessUnknownErrorReturns400(): void
    {
        $req = $this->makeReq();
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $this->successService->method('saveUtilisateurSuccess')
            ->willReturn(['success' => false, 'error' => 'Some unknown error']);
        $r = $this->ctrl()->saveSuccessForConnectedUser(1, $req, $this->successService, $this->jwt);
        $this->assertEquals(400, $r->getStatusCode());
    }

    public function testSaveSuccessServiceThrows(): void
    {
        $req = $this->makeReq();
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $this->successService->method('saveUtilisateurSuccess')
            ->willThrowException(new \Exception('Unexpected error'));
        $r = $this->ctrl()->saveSuccessForConnectedUser(1, $req, $this->successService, $this->jwt);
        $this->assertEquals(500, $r->getStatusCode());
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function ctrl(): \App\Controller\UtilisateurSuccessController
    {
        $c = new \App\Controller\UtilisateurSuccessController();
        $c->setContainer(static::getContainer());
        return $c;
    }

    private function makeReq(): Request
    {
        $req = new Request();
        $req->headers->set('Authorization', 'Bearer valid');
        return $req;
    }

    /**
     * Objet anonyme pour simuler un UtilisateurSuccess
     * sans dépendre de la vraie classe (noms de méthodes variables).
     */
    private function buildUserSuccessObject(
        int $userId, string $username,
        int $successId, string $successName, string $successDesc
    ): object {
        $user = new Utilisateur();
        $user->setUsername($username);
        $user->setEmail($username . '@test.com');
        $user->setCreatedAt(new \DateTimeImmutable());

        $ref = new \ReflectionClass($user);
        if ($ref->hasProperty('id')) {
            $p = $ref->getProperty('id');
            $p->setAccessible(true);
            $p->setValue($user, $userId);
        }

        $success = new Success();
        $success->setName($successName);
        $success->setDescription($successDesc);

        $refS = new \ReflectionClass($success);
        if ($refS->hasProperty('id')) {
            $p = $refS->getProperty('id');
            $p->setAccessible(true);
            $p->setValue($success, $successId);
        }

        return new class($user, $success) {
            public function __construct(
                private Utilisateur $u,
                private Success $s
            ) {}
            public function getUtilisateur(): Utilisateur { return $this->u; }
            public function getSuccess(): Success { return $this->s; }
            public function getObtainedAt(): \DateTimeImmutable { return new \DateTimeImmutable(); }
        };
    }
}
