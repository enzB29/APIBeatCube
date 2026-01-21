<?php

namespace App\Controller;

use App\Service\SuccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class SuccessController extends AbstractController
{
    #[Route('/all-successes', name: 'app_all_success')]
    public function AllSuccesses(SuccessService $successService): Response
    {
        return $this->json([
            'successes' => $successService->getAllSuccess(),
        ]);
    }
}
