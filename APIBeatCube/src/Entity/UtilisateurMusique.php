<?php

namespace App\Entity;

use App\Repository\UtilisateurMusiqueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UtilisateurMusiqueRepository::class)]
class UtilisateurMusique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(targetEntity: Musique::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Musique $musique = null;

    #[ORM\Column]
    private ?int $score = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $playedAt = null;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Utilisateur|null
     */
    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    /**
     * @param Utilisateur|null $utilisateur
     * @return $this
     */
    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    /**
     * @return Musique|null
     */
    public function getMusique(): ?Musique
    {
        return $this->musique;
    }

    /**
     * @param Musique|null $musique
     * @return $this
     */
    public function setMusique(?Musique $musique): static
    {
        $this->musique = $musique;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getScore(): ?int
    {
        return $this->score;
    }

    /**
     * @param int $score
     * @return $this
     */
    public function setScore(int $score): static
    {
        $this->score = $score;
        return $this;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getPlayedAt(): ?\DateTimeInterface
    {
        return $this->playedAt;
    }

    /**
     * @param \DateTimeInterface $playedAt
     * @return $this
     */
    public function setPlayedAt(\DateTimeInterface $playedAt): static
    {
        $this->playedAt = $playedAt;
        return $this;
    }
}
