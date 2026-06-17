<?php

namespace App\Controller\Api;

use App\Entity\Card;
use App\Entity\GameType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class CardController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/cards', name: 'api_create_card', methods: ['POST'])]
    public function createCard(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? null;
        $extension = $data['extension'] ?? null;
        $number = $data['number'] ?? null;
        $image = $data['image'] ?? null;
        $gameTypeId = $data['gameTypeId'] ?? null;

        if (!$name || !$extension || !$number || !$gameTypeId) {
            return $this->json(['error' => 'Missing fields: name, extension, number and gameTypeId required'], Response::HTTP_BAD_REQUEST);
        }

        $gameType = $this->em->getRepository(GameType::class)->find($gameTypeId);
        if (!$gameType) {
            return $this->json(['error' => 'GameType not found'], Response::HTTP_NOT_FOUND);
        }

        $card = new Card();
        $card->setName($name);
        $card->setExtension($extension);
        $card->setNumber($number);
        $card->setImage($image);
        $card->setGameType($gameType);

        $this->em->persist($card);
        $this->em->flush();

        return $this->json([
            'id' => $card->getId(),
            'name' => $card->getName(),
            'extension' => $card->getExtension(),
            'number' => $card->getNumber(),
            'image' => $card->getImage(),
            'gameType' => [
                'id' => $gameType->getId(),
                'nom' => $gameType->getName(),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/card/{id}', name: 'api_card_get_one', methods: ['GET'])]
    public function getOneCard(int $id): JsonResponse
    {
        $gameType = $this->em->getRepository(Card::class)->find($id);
        if (!$gameType) {
            return $this->json(['error' => 'Game type not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $gameType->getId(),
            'name' => $gameType->getName(),
            'extension' => $gameType->getExtension(),
            'number' => $gameType->getNumber(),
            'image' => $gameType->getImage(),
            'gameType' => [
                'id' => $gameType->getGameType()->getId(),
                'nom' => $gameType->getGameType()->getName(),
            ],
        ]);
    }
}
