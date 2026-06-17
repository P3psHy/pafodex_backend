<?php

namespace App\Controller\Api;

use App\Entity\Card;
use App\Entity\Library;
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
        $libraryId = $data['libraryId'] ?? null;


        if (!$name || !$extension || !$number || !$gameTypeId || !$libraryId) {
            return $this->json(['error' => 'Missing fields: name, extension, number, gameTypeId and libraryId required'], Response::HTTP_BAD_REQUEST);
        }

        $uuid = strtolower($name . '_' . $number . '_' . $extension);

        $gameType = $this->em->getRepository(GameType::class)->find($gameTypeId);
        if (!$gameType) {
            return $this->json(['error' => 'GameType not found'], Response::HTTP_NOT_FOUND);
        }

        $card = $this->em->getRepository(Card::class)->findOneBy([
            'uuid' => $uuid,
        ]);


        if ($card) {

            $library = $this->em->getReference(Library::class, $libraryId);

            if ($card->getLibraries()->contains($library)) {
                return $this->json(
                    ['error' => 'Card already exists in library'],
                    Response::HTTP_CONFLICT
                );
            }

            $card->addLibrary($library);
            $this->em->flush();

            return $this->json([
                'id' => $card->getId(),
                'name' => $card->getName(),
                'extension' => $card->getExtension(),
                'number' => $card->getNumber(),
                'image' => $card->getImage(),
                'uuid' => $card->getUuid(),
                'gameType' => [
                    'id' => $gameType->getId(),
                    'nom' => $gameType->getName(),
                ],
            ], Response::HTTP_OK);
        }else{

            $card = new Card();
            $card->setName($name);
            $card->setExtension($extension);
            $card->setNumber($number);
            $card->setImage($image);
            $card->setGameType($gameType);
            $card->setUuid($uuid);

            $card->addLibrary($this->em->getReference(Library::class, $libraryId));
            $this->em->persist($card);
            $this->em->flush();

            return $this->json([
                'id' => $card->getId(),
                'name' => $card->getName(),
                'extension' => $card->getExtension(),
                'number' => $card->getNumber(),
                'image' => $card->getImage(),
                'uuid' => $card->getUuid(),
                'gameType' => [
                    'id' => $gameType->getId(),
                    'nom' => $gameType->getName(),
                ],
            ], Response::HTTP_CREATED);

        }


    }

    #[Route('/card/{id}', name: 'api_card_get_one', methods: ['GET'])]
    public function getOneCard(int $id): JsonResponse
    {
        $card = $this->em->getRepository(Card::class)->find($id);
        if (!$card) {
            return $this->json(['error' => 'Card not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $card->getId(),
            'name' => $card->getName(),
            'extension' => $card->getExtension(),
            'number' => $card->getNumber(),
            'image' => $card->getImage(),
            'gameType' => [
                'id' => $card->getGameType()->getId(),
                'nom' => $card->getGameType()->getName(),
            ],
        ]);
    }
}
