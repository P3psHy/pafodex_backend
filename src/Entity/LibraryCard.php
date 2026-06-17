<?php

namespace App\Entity;

use App\Repository\LibraryCardRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LibraryCardRepository::class)]
#[ORM\Table(name: 'card_library')]
class LibraryCard
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'libraryCards')]
    #[ORM\JoinColumn(name: 'card_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Card $card = null;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'libraryCards')]
    #[ORM\JoinColumn(name: 'library_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Library $library = null;

    #[ORM\Column(name: 'number_card', options: ['default' => 1])]
    private int $numberCard = 1;

    #[ORM\Column(name: 'is_favorite', options: ['default' => false])]
    private bool $isFavorite = false;

    public function getCard(): ?Card
    {
        return $this->card;
    }

    public function setCard(?Card $card): static
    {
        $this->card = $card;

        return $this;
    }

    public function getLibrary(): ?Library
    {
        return $this->library;
    }

    public function setLibrary(?Library $library): static
    {
        $this->library = $library;

        return $this;
    }

    public function getNumberCard(): int
    {
        return $this->numberCard;
    }

    public function setNumberCard(int $numberCard): static
    {
        $this->numberCard = $numberCard;

        return $this;
    }

    public function isFavorite(): bool
    {
        return $this->isFavorite;
    }

    public function setIsFavorite(bool $isFavorite): static
    {
        $this->isFavorite = $isFavorite;

        return $this;
    }
}
