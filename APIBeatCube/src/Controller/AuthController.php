<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\BanService;
use App\Service\JwtService;
use App\Service\UtilisateurService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    /**
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param UserPasswordHasherInterface $passwordHasher
     * @return JsonResponse
     */
    #[Route('/signin', name: 'api_signin', methods: ['POST'])]
    public function signin(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $username = $data['username'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        // Validation
        if (!$username || !$email || !$password) {
            return $this->json(['error' => 'Username, email and password are required'], 400);
        }

        // Validation basique de l'email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email format'], 400);
        }

        // Vérifier si l'username existe déjà
        $existingUsername = $em->getRepository(Utilisateur::class)->findOneBy(['username' => $username]);
        if ($existingUsername) {
            return $this->json(['error' => 'Username already taken'], 409);
        }

        // Vérifier si l'email existe déjà
        $existingEmail = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
        if ($existingEmail) {
            return $this->json(['error' => 'Email already registered'], 409);
        }

        // Créer utilisateur
        $user = new Utilisateur();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setCreatedAt(new \DateTimeImmutable('now'));

        $em->persist($user);
        $em->flush();

        return $this->json([
            'message' => 'User created successfully',
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
        ], 201);
    }

    /**
     * @param Request $request
     * @param UtilisateurRepository $repo
     * @param UserPasswordHasherInterface $hasher
     * @param JwtService $jwt
     * @param BanService $banService
     * @return JsonResponse
     */
    #[Route('/login', methods: ['POST'])]
    public function login(Request $request, UtilisateurRepository $repo, UserPasswordHasherInterface $hasher, JwtService $jwt, BanService $banService): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $identifier = $data['identifier'] ?? null;
        $password = $data['password'] ?? null;

        if (!$identifier || !$password) {
            return $this->json(['error' => 'Identifier (username or email) and password required'], 400);
        }

        $user = null;
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = $repo->findOneBy(['email' => $identifier]);
        } else {
            $user = $repo->findOneBy(['username' => $identifier]);
        }

        if (!$user || !$hasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid credentials'], 401);
        }

        $ban = $banService->getActiveBan($user);

        if ($ban) {
            return $this->json([
                'error' => 'Compte banni',
                'reason' => $ban->getReason(),
                'bannedUntil' => $ban->getBannedUntil()?->format('Y-m-d H:i:s'),
            ], 403);
        }

        $token = $jwt->generate([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
        ]);

        return $this->json([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * @param Request $request
     * @param JwtService $jwt
     * @return JsonResponse
     */
    #[Route('/logout', methods: ['POST'])]
    public function logout(Request $request, JwtService $jwt): JsonResponse {
        return $this->json([
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * @param Request $request
     * @param JwtService $jwt
     * @param UtilisateurRepository $repo
     * @return JsonResponse
     */
    #[Route('/me', methods: ['GET'])]
    public function me(Request $request, JwtService $jwt, UtilisateurRepository $repo): JsonResponse {
        $authHeader = $request->headers->get('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->json(['error' => 'Token missing'], 401);
        }

        $token = $matches[1];
        $payload = $jwt->verify($token);

        if (!$payload) {
            return $this->json(['error' => 'Invalid token'], 401);
        }

        $user = $repo->find($payload['id']);

        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        return $this->json([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Mettre à jour le username
     */
    #[Route('/update-username', name: 'profil_update_username', methods: ['PUT', 'PATCH'])]
    public function updateUsername(Request $request, JwtService $jwt, UtilisateurService $utilisateurService): JsonResponse
    {
        // Vérifier le token JWT
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->json(['error' => 'Token manquant'], 401);
        }

        $token = $matches[1];
        $payload = $jwt->verify($token);

        if (!$payload) {
            return $this->json(['error' => 'Token invalide'], 401);
        }

        $userId = $payload['id'];

        // Récupérer les données
        $data = json_decode($request->getContent(), true);
        $newUsername = $data['username'] ?? null;

        // Validation
        if (!$newUsername || strlen(trim($newUsername)) < 3) {
            return $this->json(['error' => 'Le username doit contenir au moins 3 caractères'], 400);
        }

        try {
            $result = $utilisateurService->updateUsername($userId, trim($newUsername));

            if (!$result['success']) {
                return $this->json($result, 409);
            }

            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour du username', 'exception' => $e->getMessage()], 500);
        }
    }
}
