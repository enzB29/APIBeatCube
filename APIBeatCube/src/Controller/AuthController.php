<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
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

        $em->persist($user);
        $em->flush();

        return $this->json([
            'message' => 'User created successfully',
            'username' => $user->getUsername(),
            'email' => $user->getEmail()
        ], 201);
    }

    #[Route('/login', methods: ['POST'])]
    public function login(Request $request, UtilisateurRepository $repo, UserPasswordHasherInterface $hasher, JwtService $jwt): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $identifier = $data['identifier'] ?? null; // Peut être username OU email
        $password = $data['password'] ?? null;

        if (!$identifier || !$password) {
            return $this->json(['error' => 'Identifier (username or email) and password required'], 400);
        }

        // Détecter si c'est un email ou un username
        $user = null;
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            // C'est un email
            $user = $repo->findOneBy(['email' => $identifier]);
        } else {
            // C'est un username
            $user = $repo->findOneBy(['username' => $identifier]);
        }

        // Vérifier les credentials
        if (!$user || !$hasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid credentials'], 401);
        }

        // Générer le token
        $token = $jwt->generate([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
        ]);

        return $this->json([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail()
            ]
        ]);
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(Request $request, JwtService $jwt): JsonResponse {
        return $this->json([
            'message' => 'Logged out successfully'
        ]);
    }

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
            'email' => $user->getEmail()
        ]);
    }
}
