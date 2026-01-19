<?php

namespace App\Entity;

use App\Repository\UploadRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UploadRepository::class)]
class Upload
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne]
    private ?Musique $musique = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $uploadAt = null;

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

    public function getMusique(): ?Musique
    {
        return $this->musique;
    }

    public function setMusique(?Musique $musique): static
    {
        $this->musique = $musique;

        return $this;
    }

    public function getUploadAt(): ?\DateTimeImmutable
    {
        return $this->uploadAt;
    }

    public function setUploadAt(\DateTimeImmutable $uploadAt): static
    {
        $this->uploadAt = $uploadAt;

        return $this;
    }
}
