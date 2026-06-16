<?php

namespace App\Controller\Api;

use App\Entity\Card;
use App\Entity\GameType;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
        $nom = $data['nom'] ?? null;
        $extension = $data['extension'] ?? null;
        $numero = $data['numero'] ?? null;
        $gameTypeId = $data['gameTypeId'] ?? null;

        if (!$nom || !$extension || !$numero || !$gameTypeId) {
            return $this->json(['error' => 'Missing fields: nom, extension, numero and gameTypeId required'], Response::HTTP_BAD_REQUEST);
        }

        $gameType = $this->em->getRepository(GameType::class)->find($gameTypeId);
        if (!$gameType) {
            return $this->json(['error' => 'GameType not found'], Response::HTTP_NOT_FOUND);
        }

        $card = new Card();
        $card->setNom($nom);
        $card->setExtension($extension);
        $card->setNumero($numero);
        $card->setGameType($gameType);

        $this->em->persist($card);
        $this->em->flush();

        return $this->json([
            'id' => $card->getId(),
            'nom' => $card->getNom(),
            'extension' => $card->getExtension(),
            'numero' => $card->getNumero(),
            'gameType' => [
                'id' => $gameType->getId(),
                'nom' => $gameType->getName(),
            ],
        ], Response::HTTP_CREATED);
    }
}
