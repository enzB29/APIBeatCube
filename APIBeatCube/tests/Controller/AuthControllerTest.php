<?php

namespace App\Tests\Controller;

use App\Entity\Utilisateur;
use App\Entity\UtilisateurBan;
use App\Repository\UtilisateurRepository;
use App\Service\BanService;
use App\Service\JwtService;
use App\Service\UtilisateurService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests unitaires AuthController.
 *
 * On utilise KernelTestCase + setContainer() pour résoudre l'erreur
 * "container must not be accessed before initialization" de AbstractController.
 * Les dépendances restent toutes mockées : aucune BDD n'est requise.
 */
class AuthControllerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;
    private UtilisateurRepository $utilisateurRepo;
    private JwtService $jwt;
    private BanService $banService;
    private UtilisateurService $utilisateurService;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em                = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher    = $this->createMock(UserPasswordHasherInterface::class);
        $this->utilisateurRepo   = $this->createMock(UtilisateurRepository::class);
        $this->jwt               = $this->createMock(JwtService::class);
        $this->banService        = $this->createMock(BanService::class);
        $this->utilisateurService = $this->createMock(UtilisateurService::class);
    }

    // =========================================================================
    // SIGNIN
    // =========================================================================

    public function testSigninMissingUsername(): void
    {
        $r = $this->callSignin(['email' => 'test@test.com', 'password' => 'pass']);
        $this->assertEquals(400, $r->getStatusCode());
        $this->assertKey($r, 'error', 'Username, email and password are required');
    }

    public function testSigninMissingEmail(): void
    {
        $r = $this->callSignin(['username' => 'bob', 'password' => 'pass']);
        $this->assertEquals(400, $r->getStatusCode());
    }

    public function testSigninMissingPassword(): void
    {
        $r = $this->callSignin(['username' => 'bob', 'email' => 'bob@test.com']);
        $this->assertEquals(400, $r->getStatusCode());
    }

    public function testSigninEmptyBody(): void
    {
        $r = $this->callSignin([]);
        $this->assertEquals(400, $r->getStatusCode());
    }

    public function testSigninInvalidEmailFormat(): void
    {
        $r = $this->callSignin(['username' => 'bob', 'email' => 'not-an-email', 'password' => 'pass123']);
        $this->assertEquals(400, $r->getStatusCode());
        $this->assertKey($r, 'error', 'Invalid email format');
    }

    public function testSigninUsernameAlreadyTaken(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')
            ->willReturnCallback(fn($c) => isset($c['username']) ? new Utilisateur() : null);
        $this->em->method('getRepository')->willReturn($repo);

        $r = $this->callSignin(['username' => 'bob', 'email' => 'bob@test.com', 'password' => 'pass123']);
        $this->assertEquals(409, $r->getStatusCode());
        $this->assertKey($r, 'error', 'Username already taken');
    }

    public function testSigninEmailAlreadyRegistered(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')
            ->willReturnCallback(fn($c) => isset($c['email']) ? new Utilisateur() : null);
        $this->em->method('getRepository')->willReturn($repo);

        $r = $this->callSignin(['username' => 'bob', 'email' => 'bob@test.com', 'password' => 'pass123']);
        $this->assertEquals(409, $r->getStatusCode());
        $this->assertKey($r, 'error', 'Email already registered');
    }

    public function testSigninSuccess(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->willReturn($repo);
        $this->passwordHasher->method('hashPassword')->willReturn('hashed');
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $r = $this->callSignin(['username' => 'bob', 'email' => 'bob@test.com', 'password' => 'pass123']);
        $this->assertEquals(201, $r->getStatusCode());
        $body = json_decode($r->getContent(), true);
        $this->assertEquals('User created successfully', $body['message']);
        $this->assertEquals('bob', $body['username']);
        $this->assertEquals('bob@test.com', $body['email']);
    }

    // =========================================================================
    // LOGIN
    // =========================================================================

    public function testLoginMissingIdentifier(): void
    {
        $r = $this->callLogin(['password' => 'pass']);
        $this->assertEquals(400, $r->getStatusCode());
    }

    public function testLoginMissingPassword(): void
    {
        $r = $this->callLogin(['identifier' => 'bob']);
        $this->assertEquals(400, $r->getStatusCode());
    }

    public function testLoginUserNotFound(): void
    {
        $this->utilisateurRepo->method('findOneBy')->willReturn(null);
        $r = $this->callLogin(['identifier' => 'unknown', 'password' => 'pass']);
        $this->assertEquals(401, $r->getStatusCode());
        $this->assertKey($r, 'error', 'Invalid credentials');
    }

    public function testLoginWrongPassword(): void
    {
        $this->utilisateurRepo->method('findOneBy')->willReturn(new Utilisateur());
        $this->passwordHasher->method('isPasswordValid')->willReturn(false);
        $r = $this->callLogin(['identifier' => 'bob', 'password' => 'wrong']);
        $this->assertEquals(401, $r->getStatusCode());
    }

    public function testLoginBannedUserTemporary(): void
    {
        $user = $this->buildUser();
        $this->utilisateurRepo->method('findOneBy')->willReturn($user);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);
        $ban = $this->createMock(UtilisateurBan::class);
        $ban->method('getReason')->willReturn('Spam');
        $ban->method('getBannedUntil')->willReturn(new \DateTimeImmutable());

        $this->banService->method('getActiveBan')
            ->willReturn($ban);

        $r = $this->callLogin(['identifier' => 'bob', 'password' => 'pass']);
        $this->assertEquals(403, $r->getStatusCode());
        $body = json_decode($r->getContent(), true);
        $this->assertEquals('Compte banni', $body['error']);
        $this->assertEquals('Spam', $body['reason']);
        $this->assertNotNull($body['bannedUntil']);
    }

    public function testLoginBannedUserPermanent(): void
    {
        $user = $this->buildUser();
        $this->utilisateurRepo->method('findOneBy')->willReturn($user);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);
        $ban = $this->createMock(UtilisateurBan::class);
        $ban->method('getReason')->willReturn('test');
        $ban->method('getBannedUntil')->willReturn(null);

        $this->banService->method('getActiveBan')
            ->willReturn($ban);

        $r = $this->callLogin(['identifier' => 'bob', 'password' => 'pass']);
        $this->assertEquals(403, $r->getStatusCode());
        $body = json_decode($r->getContent(), true);
        $this->assertNull($body['bannedUntil']);
    }

    public function testLoginSuccessWithUsername(): void
    {
        $user = $this->buildUser();
        $this->utilisateurRepo->method('findOneBy')->willReturn($user);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);
        $this->banService->method('getActiveBan')->willReturn(null);
        $this->jwt->method('generate')->willReturn('fake.jwt.token');

        $r = $this->callLogin(['identifier' => 'bob', 'password' => 'pass']);
        $this->assertEquals(200, $r->getStatusCode());
        $body = json_decode($r->getContent(), true);
        $this->assertEquals('fake.jwt.token', $body['token']);
        $this->assertArrayHasKey('user', $body);
    }

    public function testLoginSuccessWithEmail(): void
    {
        $user = $this->buildUser();
        $this->utilisateurRepo->method('findOneBy')->willReturn($user);
        $this->passwordHasher->method('isPasswordValid')->willReturn(true);
        $this->banService->method('getActiveBan')->willReturn(null);
        $this->jwt->method('generate')->willReturn('fake.jwt.token');

        $r = $this->callLogin(['identifier' => 'bob@test.com', 'password' => 'pass']);
        $this->assertEquals(200, $r->getStatusCode());
    }

    // =========================================================================
    // LOGOUT
    // =========================================================================

    public function testLogoutAlwaysReturns200(): void
    {
        $r = $this->callController()->logout(new Request(), $this->jwt);
        $this->assertEquals(200, $r->getStatusCode());
        $this->assertKey($r, 'message', 'Logged out successfully');
    }

    public function testLogoutWithNoToken(): void
    {
        $r = $this->callController()->logout(new Request(), $this->jwt);
        $this->assertEquals(200, $r->getStatusCode());
    }

    // =========================================================================
    // ME
    // =========================================================================

    public function testMeMissingAuthHeader(): void
    {
        $r = $this->callMe(null);
        $this->assertEquals(401, $r->getStatusCode());
        $this->assertKey($r, 'error', 'Token missing');
    }

    public function testMeMalformedAuthHeader(): void
    {
        $r = $this->callMe('NotBearer token');
        $this->assertEquals(401, $r->getStatusCode());
    }

    public function testMeInvalidToken(): void
    {
        $this->jwt->method('verify')->willReturn(null);
        $r = $this->callMe('Bearer invalid.token');
        $this->assertEquals(401, $r->getStatusCode());
        $this->assertKey($r, 'error', 'Invalid token');
    }

    public function testMeUserNotFound(): void
    {
        $this->jwt->method('verify')->willReturn(['id' => 999]);
        $this->utilisateurRepo->method('find')->willReturn(null);
        $r = $this->callMe('Bearer valid.token');
        $this->assertEquals(404, $r->getStatusCode());
        $this->assertKey($r, 'error', 'User not found');
    }

    public function testMeSuccess(): void
    {
        $user = $this->buildUser();
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $this->utilisateurRepo->method('find')->willReturn($user);
        $r = $this->callMe('Bearer valid.token');
        $this->assertEquals(200, $r->getStatusCode());
        $body = json_decode($r->getContent(), true);
        $this->assertEquals('bob', $body['username']);
        $this->assertEquals('bob@test.com', $body['email']);
    }

    // =========================================================================
    // UPDATE USERNAME
    // =========================================================================

    public function testUpdateUsernameMissingToken(): void
    {
        $r = $this->callUpdateUsername(['username' => 'newname'], null);
        $this->assertEquals(401, $r->getStatusCode());
    }

    public function testUpdateUsernameInvalidToken(): void
    {
        $this->jwt->method('verify')->willReturn(null);
        $r = $this->callUpdateUsername(['username' => 'newname'], 'Bearer bad');
        $this->assertEquals(401, $r->getStatusCode());
    }

    public function testUpdateUsernameTooShort(): void
    {
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $r = $this->callUpdateUsername(['username' => 'ab'], 'Bearer valid');
        $this->assertEquals(400, $r->getStatusCode());
        $this->assertKey($r, 'error', 'Le username doit contenir au moins 3 caractères');
    }

    public function testUpdateUsernameConflict(): void
    {
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $this->utilisateurService->method('updateUsername')
            ->willReturn(['success' => false, 'error' => 'Username already taken']);
        $r = $this->callUpdateUsername(['username' => 'existing'], 'Bearer valid');
        $this->assertEquals(409, $r->getStatusCode());
    }

    public function testUpdateUsernameSuccess(): void
    {
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $this->utilisateurService->method('updateUsername')
            ->willReturn(['success' => true, 'username' => 'newbob']);
        $r = $this->callUpdateUsername(['username' => 'newbob'], 'Bearer valid');
        $this->assertEquals(200, $r->getStatusCode());
    }

    public function testUpdateUsernameTrimsWhitespace(): void
    {
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $this->utilisateurService->expects($this->once())
            ->method('updateUsername')
            ->with(1, 'validname')
            ->willReturn(['success' => true]);
        $r = $this->callUpdateUsername(['username' => '  validname  '], 'Bearer valid');
        $this->assertEquals(200, $r->getStatusCode());
    }

    public function testUpdateUsernameServiceThrows(): void
    {
        $this->jwt->method('verify')->willReturn(['id' => 1]);
        $this->utilisateurService->method('updateUsername')
            ->willThrowException(new \Exception('DB error'));
        $r = $this->callUpdateUsername(['username' => 'validname'], 'Bearer valid');
        $this->assertEquals(500, $r->getStatusCode());
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function callController(): \App\Controller\AuthController
    {
        $ctrl = new \App\Controller\AuthController();
        $ctrl->setContainer(static::getContainer());
        return $ctrl;
    }

    private function callSignin(array $data): JsonResponse
    {
        $req = Request::create('/', 'POST', [], [], [], [], json_encode($data));
        return $this->callController()->signin($req, $this->em, $this->passwordHasher);
    }

    private function callLogin(array $data): JsonResponse
    {
        $req = Request::create('/', 'POST', [], [], [], [], json_encode($data));
        return $this->callController()->login($req, $this->utilisateurRepo, $this->passwordHasher, $this->jwt, $this->banService);
    }

    private function callMe(?string $authHeader): JsonResponse
    {
        $req = new Request();
        if ($authHeader !== null) {
            $req->headers->set('Authorization', $authHeader);
        }
        return $this->callController()->me($req, $this->jwt, $this->utilisateurRepo);
    }

    private function callUpdateUsername(array $data, ?string $authHeader): JsonResponse
    {
        $req = Request::create('/', 'PUT', [], [], [], [], json_encode($data));
        if ($authHeader !== null) {
            $req->headers->set('Authorization', $authHeader);
        }
        return $this->callController()->updateUsername($req, $this->jwt, $this->utilisateurService);
    }

    private function buildUser(): Utilisateur
    {
        $user = new Utilisateur();
        $user->setUsername('bob');
        $user->setEmail('bob@test.com');
        $user->setPassword('hashed');
        $user->setCreatedAt(new \DateTimeImmutable('2024-01-01'));
        return $user;
    }

    private function assertKey(JsonResponse $r, string $key, string $value): void
    {
        $body = json_decode($r->getContent(), true);
        $this->assertArrayHasKey($key, $body, "Clé '$key' absente de : " . $r->getContent());
        $this->assertEquals($value, $body[$key]);
    }
}
