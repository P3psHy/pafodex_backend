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
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $extension = null;

    #[ORM\Column(length: 255)]
    private ?string $number = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\ManyToOne(inversedBy: 'cards')]
    #[ORM\JoinColumn(nullable: false)]
    private ?GameType $gameType = null;

    /**
     * @var Collection<int, GameSet>
     */
    #[ORM\ManyToMany(targetEntity: GameSet::class, mappedBy: 'cards')]
    private Collection $sets;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $uuid = null;

    /**
     * @var Collection<int, LibraryCard>
     */
    #[ORM\OneToMany(targetEntity: LibraryCard::class, mappedBy: 'card', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $libraryCards;

    public function __construct()
    {
        $this->sets = new ArrayCollection();
        $this->libraryCards = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

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

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(?string $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * @return Collection<int, LibraryCard>
     */
    public function getLibraryCards(): Collection
    {
        return $this->libraryCards;
    }

    public function addLibraryCard(LibraryCard $libraryCard): static
    {
        if (!$this->libraryCards->contains($libraryCard)) {
            $this->libraryCards->add($libraryCard);
            $libraryCard->setCard($this);
        }

        return $this;
    }

    public function removeLibraryCard(LibraryCard $libraryCard): static
    {
        if ($this->libraryCards->removeElement($libraryCard)) {
            if ($libraryCard->getCard() === $this) {
                $libraryCard->setCard(null);
            }
        }

        return $this;
    }
}
