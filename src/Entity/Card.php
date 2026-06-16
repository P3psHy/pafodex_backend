<?php

namespace App\Entity;

use App\Entity\Set as GameSet;
use App\Repository\CardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CardRepository::class)]
class Card
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $extension = null;

    #[ORM\Column(length: 255)]
    private ?string $numero = null;

    #[ORM\ManyToOne(inversedBy: 'cards')]
    #[ORM\JoinColumn(nullable: false)]
    private ?GameType $gameType = null;

    /**
     * @var Collection<int, GameSet>
     */
    #[ORM\ManyToMany(targetEntity: GameSet::class, mappedBy: 'cards')]
    private Collection $sets;

    public function __construct()
    {
        $this->sets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): static
    {
        $this->extension = $extension;

        return $this;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getGameType(): ?GameType
    {
        return $this->gameType;
    }

    public function setGameType(?GameType $gameType): static
    {
        $this->gameType = $gameType;

        return $this;
    }

    /**
     * @return Collection<int, GameSet>
     */
    public function getSets(): Collection
    {
        return $this->sets;
    }

    public function addSet(GameSet $set): static
    {
        if (!$this->sets->contains($set)) {
            $this->sets->add($set);
        }

        return $this;
    }

    public function removeSet(GameSet $set): static
    {
        $this->sets->removeElement($set);

        return $this;
    }
}
