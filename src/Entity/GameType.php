<?php

namespace App\Entity;

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

    public function __construct()
    {
        $this->set = new ArrayCollection();
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
}
