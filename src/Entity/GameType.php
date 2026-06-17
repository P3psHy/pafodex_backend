<?php

namespace App\Entity;

use App\Entity\Card;
use App\Repository\GameTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameTypeRepository::class)]
class GameType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, Set>
     */
    #[ORM\OneToMany(targetEntity: Set::class, mappedBy: 'gameType')]
    private Collection $set;

    /**
     * @var Collection<int, Card>
     */
    #[ORM\OneToMany(targetEntity: Card::class, mappedBy: 'gameType', orphanRemoval: true)]
    private Collection $cards;

    #[ORM\Column(length: 255)]
    private ?string $abbreviated = null;

    public function __construct()
    {
        $this->set = new ArrayCollection();
        $this->cards = new ArrayCollection();
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

    /**
     * @return Collection<int, Set>
     */
    public function getSet(): Collection
    {
        return $this->set;
    }

    public function addSet(Set $set): static
    {
        if (!$this->set->contains($set)) {
            $this->set->add($set);
            $set->setGameType($this);
        }

        return $this;
    }

    public function removeSet(Set $set): static
    {
        if ($this->set->removeElement($set)) {
            // set the owning side to null (unless already changed)
            if ($set->getGameType() === $this) {
                $set->setGameType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Card>
     */
    public function getCards(): Collection
    {
        return $this->cards;
    }

    public function addCard(Card $card): static
    {
        if (!$this->cards->contains($card)) {
            $this->cards->add($card);
            $card->setGameType($this);
        }

        return $this;
    }

    public function removeCard(Card $card): static
    {
        if ($this->cards->removeElement($card)) {
            if ($card->getGameType() === $this) {
                $card->setGameType(null);
            }
        }

        return $this;
    }

    public function getAbbreviated(): ?string
    {
        return $this->abbreviated;
    }

    public function setAbbreviated(string $abbreviated): static
    {
        $this->abbreviated = $abbreviated;

        return $this;
    }
}
