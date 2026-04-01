<?php

namespace App\Tests\Controller;

use App\Controller\UtilisateurController;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\BanService;
use App\Service\JwtService;
use App\Service\UtilisateurService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for UtilisateurController
 */
class UtilisateurControllerTest extends KernelTestCase
{
    private MockObject $userRepo;
    private MockObject $banService;
    private MockObject $jwt;

    private UtilisateurService $utilisateurService;

    private function ctrl(): UtilisateurController
    {
        $controller = new UtilisateurController(
            $this->utilisateurService,
            $this->banService,
            $this->jwt
        );

        $controller->setContainer(static::getContainer());

        return $controller;
    }
    protected function setUp(): void
    {
        $this->utilisateurService = $this->createMock(UtilisateurService::class);
        $this->userRepo   = $this->createMock(UtilisateurRepository::class);
        $this->banService = $this->createMock(BanService::class);
        $this->jwt        = $this->createMock(JwtService::class);
    }

    // =========================================================================
    // ALL USERS TESTS
    // =========================================================================

    public function testAllUsersReturnsEmptyList(): void
    {
        $controller = $this->ctrl();
        $this->userRepo->method('findAll')->willReturn([]);

        $response = $controller->AllUsers($this->userRepo, $this->banService);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals(0, $body['count']);
        $this->assertEmpty($body['users']);
    }

    public function testAllUsersReturnsList(): void
    {
        $controller = $this->ctrl();

        $user1 = $this->buildUser(1, 'alice', 'alice@test.com');
        $user2 = $this->buildUser(2, 'bob', 'bob@test.com');

        $this->userRepo->method('findAll')->willReturn([$user1, $user2]);
        $this->banService->method('isUserBanned')->willReturn(false);

        $response = $controller->AllUsers($this->userRepo, $this->banService);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals(2, $body['count']);
        $this->assertCount(2, $body['users']);
    }

    public function testAllUsersShowsBannedStatus(): void
    {
        $controller = $this->ctrl();
        $user = $this->buildUser(1, 'alice', 'alice@test.com');
        $this->userRepo->method('findAll')->willReturn([$user]);
        $this->banService->method('isUserBanned')->willReturn(true);

        $response = $controller->AllUsers($this->userRepo, $this->banService);

        $body = json_decode($response->getContent(), true);
        $this->assertTrue($body['users'][0]['isBanned']);
    }

    public function testAllUsersContainsExpectedFields(): void
    {
        $controller = $this->ctrl();
        $user = $this->buildUser(1, 'alice', 'alice@test.com');
        $this->userRepo->method('findAll')->willReturn([$user]);
        $this->banService->method('isUserBanned')->willReturn(false);

        $response = $controller->AllUsers($this->userRepo, $this->banService);

        $body = json_decode($response->getContent(), true);
        $u = $body['users'][0];
        $this->assertArrayHasKey('id', $u);
        $this->assertArrayHasKey('username', $u);
        $this->assertArrayHasKey('email', $u);
        $this->assertArrayHasKey('isBanned', $u);
    }

    // =========================================================================
    // BAN TESTS
    // =========================================================================

    public function testBanMissingToken(): void
    {
        $controller = $this->ctrl();
        $request    = new Request();

        $response = $controller->ban(1, 2, $request, $this->userRepo, $this->banService, $this->jwt);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testBanInvalidToken(): void
    {
        $controller = $this->ctrl();
        $request    = new Request();
        $request->headers->set('Authorization', 'Bearer bad');

        $this->jwt->method('verify')->willReturn(null);

        $response = $controller->ban(1, 2, $request, $this->userRepo, $this->banService, $this->jwt);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testBanForbiddenForNonAdmin(): void
    {
        $controller = $this->ctrl();
        $request    = new Request();
        $request->headers->set('Authorization', 'Bearer valid');

        $this->jwt->method('verify')->willReturn(['id' => 1, 'roles' => ['ROLE_USER']]);

        $response = $controller->ban(1, 2, $request, $this->userRepo, $this->banService, $this->jwt);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testBanUserNotFound(): void
    {
        $controller = $this->ctrl();
        $request    = $this->makeRequest([]);
        $request->headers->set('Authorization', 'Bearer valid');

        $this->jwt->method('verify')->willReturn(['id' => 99, 'roles' => ['ROLE_ADMIN']]);
        $this->userRepo->method('find')->willReturn(null);

        $response = $controller->ban(999, 99, $request, $this->userRepo, $this->banService, $this->jwt);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertJsonKey($response->getContent(), 'error', 'Utilisateur introuvable');
    }

    public function testBanWithDefaultReason(): void
    {
        $controller = $this->ctrl();
        $request    = $this->makeRequest([]);
        $request->headers->set('Authorization', 'Bearer valid');

        $admin = $this->buildUser(2, 'admin', 'admin@test.com');
        $user  = $this->buildUser(1, 'alice', 'alice@test.com');

        $this->jwt->method('verify')->willReturn(['id' => 2, 'roles' => ['ROLE_ADMIN']]);
        $this->userRepo->method('find')->willReturnCallback(fn($id) => $id === 1 ? $user : $admin);

        $this->banService->expects($this->once())
            ->method('banUser')
            ->with($user, 'Violation des règles', $admin, null );

        $response = $controller->ban(1, 2, $request, $this->userRepo, $this->banService, $this->jwt);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testBanWithCustomReasonAndDays(): void
    {
        $controller = $this->ctrl();
        $request    = $this->makeRequest(['reason' => 'Spam', 'days' => 7]);
        $request->headers->set('Authorization', 'Bearer valid');

        $admin = $this->buildUser(2, 'admin', 'admin@test.com');
        $user  = $this->buildUser(1, 'alice', 'alice@test.com');

        $this->jwt->method('verify')->willReturn(['id' => 2, 'roles' => ['ROLE_ADMIN']]);
        $this->userRepo->method('find')->willReturnCallback(fn($id) => $id === 1 ? $user : $admin);

        $response = $controller->ban(1, 2, $request, $this->userRepo, $this->banService, $this->jwt);

        $this->assertEquals(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('Spam', $body['bannedReason']);
        $this->assertNotNull($body['bannedUntil']);
    }

    public function testBanPermanent(): void
    {
        $controller = $this->ctrl();
        $request    = $this->makeRequest(['reason' => 'Permanent', 'days' => null]);
        $request->headers->set('Authorization', 'Bearer valid');

        $admin = $this->buildUser(2, 'admin', 'admin@test.com');
        $user  = $this->buildUser(1, 'alice', 'alice@test.com');

        $this->jwt->method('verify')->willReturn(['id' => 2, 'roles' => ['ROLE_ADMIN']]);
        $this->userRepo->method('find')->willReturnCallback(fn($id) => $id === 1 ? $user : $admin);

        $response = $controller->ban(1, 2, $request, $this->userRepo, $this->banService, $this->jwt);

        $body = json_decode($response->getContent(), true);
        $this->assertNull($body['bannedUntil']);
    }

    public function testBanResponseContainsExpectedFields(): void
    {
        $controller = $this->ctrl();
        $request    = $this->makeRequest(['reason' => 'Bad behaviour', 'days' => 3]);
        $request->headers->set('Authorization', 'Bearer valid');

        $admin = $this->buildUser(2, 'admin', 'admin@test.com');
        $user  = $this->buildUser(1, 'alice', 'alice@test.com');

        $this->jwt->method('verify')->willReturn(['id' => 2, 'roles' => ['ROLE_ADMIN']]);
        $this->userRepo->method('find')->willReturnCallback(fn($id) => $id === 1 ? $user : $admin);

        $response = $controller->ban(1, 2, $request, $this->userRepo, $this->banService, $this->jwt);

        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $body);
        $this->assertArrayHasKey('userId', $body);
        $this->assertArrayHasKey('bannedUntil', $body);
        $this->assertArrayHasKey('bannedReason', $body);
        $this->assertArrayHasKey('bannedBy', $body);
        $this->assertEquals('admin', $body['bannedBy']);
    }

    // =========================================================================
    // UNBAN TESTS
    // =========================================================================

    public function testUnbanMissingToken(): void
    {
        $controller = $this->ctrl();
        $request    = new Request();

        $response = $controller->unban(1, $request, $this->userRepo, $this->banService, $this->jwt);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUnbanInvalidToken(): void
    {
        $controller = $this->ctrl();
        $request    = new Request();
        $request->headers->set('Authorization', 'Bearer bad');

        $this->jwt->method('verify')->willReturn(null);

        $response = $controller->unban(1, $request, $this->userRepo, $this->banService, $this->jwt);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUnbanForbiddenForNonAdmin(): void
    {
        $controller = $this->ctrl();
        $request    = new Request();
        $request->headers->set('Authorization', 'Bearer valid');

        $this->jwt->method('verify')->willReturn(['id' => 1, 'roles' => ['ROLE_USER']]);

        $response = $controller->unban(1, $request, $this->userRepo, $this->banService, $this->jwt);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUnbanUserNotFound(): void
    {
        $controller = $this->ctrl();
        $request    = new Request();
        $request->headers->set('Authorization', 'Bearer valid');

        $this->jwt->method('verify')->willReturn(['id' => 1, 'roles' => ['ROLE_ADMIN']]);
        $this->userRepo->method('find')->willReturn(null);

        $response = $controller->unban(999, $request, $this->userRepo, $this->banService, $this->jwt);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUnbanSuccess(): void
    {
        $controller = $this->ctrl();
        $request    = new Request();
        $request->headers->set('Authorization', 'Bearer valid');

        $user = $this->buildUser(1, 'alice', 'alice@test.com');
        $this->jwt->method('verify')->willReturn(['id' => 2, 'roles' => ['ROLE_ADMIN']]);
        $this->userRepo->method('find')->willReturn($user);
        $this->banService->expects($this->once())->method('unbanUser')->with($user);

        $response = $controller->unban(1, $request, $this->userRepo, $this->banService, $this->jwt);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertEquals('Ban levé', $body['message']);
    }

    public function testUnbanResponseContainsUserId(): void
    {
        $controller = $this->ctrl();
        $request    = new Request();
        $request->headers->set('Authorization', 'Bearer valid');

        $user = $this->buildUser(1, 'alice', 'alice@test.com');
        $this->jwt->method('verify')->willReturn(['id' => 2, 'roles' => ['ROLE_ADMIN']]);
        $this->userRepo->method('find')->willReturn($user);

        $response = $controller->unban(1, $request, $this->userRepo, $this->banService, $this->jwt);

        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('userId', $body);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function makeRequest(array $data): Request
    {
        return Request::create('/', 'POST', [], [], [], [], json_encode($data));
    }

    private function buildUser(int $id, string $username, string $email): Utilisateur
    {
        $user = new Utilisateur();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPassword('hashed');
        $user->setCreatedAt(new \DateTimeImmutable());

        // Inject id via reflection (Doctrine-style entity)
        $ref  = new \ReflectionClass($user);
        if ($ref->hasProperty('id')) {
            $prop = $ref->getProperty('id');
            $prop->setAccessible(true);
            $prop->setValue($user, $id);
        }

        return $user;
    }

    private function assertJsonKey(string $json, string $key, string $value): void
    {
        $data = json_decode($json, true);
        $this->assertArrayHasKey($key, $data);
        $this->assertEquals($value, $data[$key]);
    }
}
