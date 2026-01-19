<?php

namespace App\Entity;

use App\Repository\UtilisateurBanRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UtilisateurBanRepository::class)]
class UtilisateurBan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn]
    private ?Utilisateur $user = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $bannedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $bannedUntil = null;

    #[ORM\Column(length: 255)]
    private ?string $reason = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn]
    private ?Utilisateur $bannedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?Utilisateur
    {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getBannedAt(): ?\DateTimeImmutable
    {
        return $this->bannedAt;
    }

    public function setBannedAt(\DateTimeImmutable $bannedAt): static
    {
        $this->bannedAt = $bannedAt;

        return $this;
    }

    public function getBannedUntil(): ?\DateTimeImmutable
    {
        return $this->bannedUntil;
    }

    public function setBannedUntil(?\DateTimeImmutable $bannedUntil): static
    {
        $this->bannedUntil = $bannedUntil;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getBannedBy(): ?Utilisateur
    {
        return $this->bannedBy;
    }

    public function setBannedBy(?Utilisateur $bannedBy): static
    {
        $this->bannedBy = $bannedBy;

        return $this;
    }
}
