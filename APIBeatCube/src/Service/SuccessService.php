<?php

namespace App\Service;

use App\Entity\Success;
use App\Repository\SuccessRepository;

class SuccessService
{
 public function __construct(
     private SuccessRepository $successRepository,
 )
 {
 }

 public function getAllSuccess(): array
 {
     return $this->successRepository->findAll();
 }

 public function getSuccessById(int $id): ?Success
 {
     return $this->successRepository->find($id);
 }
}
