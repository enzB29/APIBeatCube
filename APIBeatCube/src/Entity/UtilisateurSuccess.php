<?php

namespace App\Entity;

use App\Repository\UtilisateurSuccessRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UtilisateurSuccessRepository::class)]
class UtilisateurSuccess
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne]
    private ?Success $success = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $obtainedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getSuccess(): ?Success
    {
        return $this->success;
    }

    public function setSuccess(?Success $success): static
    {
        $this->success = $success;

        return $this;
    }

    public function getObtainedAt(): ?\DateTimeImmutable
    {
        return $this->obtainedAt;
    }

    public function setObtainedAt(\DateTimeImmutable $obtainedAt): static
    {
        $this->obtainedAt = $obtainedAt;

        return $this;
    }
}
