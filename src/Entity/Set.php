<?php

namespace App\Entity;

use App\Repository\SetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SetRepository::class)]
class Set
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'sets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Library $library = null;

    #[ORM\ManyToOne(inversedBy: 'set')]
    #[ORM\JoinColumn(nullable: false)]
    private ?GameType $gameType = null;

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

    public function getLibrary(): ?Library
    {
        return $this->library;
    }

    public function setLibrary(?Library $library): static
    {
        $this->library = $library;

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
}
