<?php

namespace App\Entity;

use App\Repository\LibraryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LibraryRepository::class)]
class Library
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'library', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /**
     * @var Collection<int, Set>
     */
    #[ORM\OneToMany(targetEntity: Set::class, mappedBy: 'library', orphanRemoval: true)]
    private Collection $sets;

    /**
     * @var Collection<int, LibraryCard>
     */
    #[ORM\OneToMany(targetEntity: LibraryCard::class, mappedBy: 'library', cascade: ['persist', 'remove'], orphanRemoval: true)]
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, Set>
     */
    public function getSets(): Collection
    {
        return $this->sets;
    }

    public function addSet(Set $set): static
    {
        if (!$this->sets->contains($set)) {
            $this->sets->add($set);
            $set->setLibrary($this);
        }

        return $this;
    }

    public function removeSet(Set $set): static
    {
        if ($this->sets->removeElement($set)) {
            // set the owning side to null (unless already changed)
            if ($set->getLibrary() === $this) {
                $set->setLibrary(null);
            }
        }

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
            $libraryCard->setLibrary($this);
        }

        return $this;
    }

    public function removeLibraryCard(LibraryCard $libraryCard): static
    {
        if ($this->libraryCards->removeElement($libraryCard)) {
            if ($libraryCard->getLibrary() === $this) {
                $libraryCard->setLibrary(null);
            }
        }

        return $this;
    }


}
