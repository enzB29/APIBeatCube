<?php

namespace App\Controller;

use App\Repository\UtilisateurRepository;
use App\Service\JwtService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/utilisateur')]
class UtilisateurController extends AbstractController
{
    #[Route('/allusers', methods: ['GET'])]
    public function AllUsers(Request $request, JwtService $jwt, UtilisateurRepository $repo): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization');

        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->json(['error' => 'Token missing'], 401);
        }

        $token = $matches[1];
        $payload = $jwt->verify($token);

        if (!$payload) {
            return $this->json(['error' => 'Invalid token'], 401);
        }

        $users = $repo->findAll();

        $userToReturn = [];

        foreach ($users as $user) {
            $userToReturn[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
            ];
        }

        return $this->json([
            'count' => count($userToReturn),
            'users' => $userToReturn,
        ]);
    }
}
