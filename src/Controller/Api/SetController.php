<?php

namespace App\Controller\Api;

use App\Entity\Card;
use App\Entity\User;
use App\Entity\Set as GameSet;
use App\Entity\GameType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class SetController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    private function extractBearerToken(Request $request): ?string
    {
        $auth = $request->headers->get('Authorization');
        if (!$auth) {
            return null;
        }
        if (0 === stripos($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    #[Route('/me/sets', name: 'api_me_sets', methods: ['GET'])]
    public function listSets(Request $request): JsonResponse
    {
        $token = $this->extractBearerToken($request) ?? $request->query->get('apiToken');
        if (!$token) {
            return $this->json(['error' => 'No token provided'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['apiToken' => $token]);
        if (!$user) {
            return $this->json(['error' => 'Invalid token'], Response::HTTP_UNAUTHORIZED);
        }

        $library = $user->getLibrary();
        if (!$library) {
            return $this->json(['error' => 'Library not found'], Response::HTTP_NOT_FOUND);
        }

        $sets = [];
        foreach ($library->getSets() as $set) {
            $sets[] = [
                'id' => $set->getId(),
                'name' => $set->getName(),
                'gameType' => [
                    'id' => $set->getGameType()->getId(),
                    'nom' => $set->getGameType()->getName(),
                ],
            ];
        }

        return $this->json(['sets' => $sets]);
    }

    #[Route('/me/sets', name: 'api_me_sets_create', methods: ['POST'])]
    public function createSet(Request $request): JsonResponse
    {
        $token = $this->extractBearerToken($request) ?? $request->query->get('apiToken');
        if (!$token) {
            return $this->json(['error' => 'No token provided'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['apiToken' => $token]);
        if (!$user) {
            return $this->json(['error' => 'Invalid token'], Response::HTTP_UNAUTHORIZED);
        }

        $library = $user->getLibrary();
        if (!$library) {
            return $this->json(['error' => 'Library not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? null;
        $gameTypeId = $data['gameTypeId'] ?? null;

        if (!$name || !$gameTypeId) {
            return $this->json(['error' => 'Missing fields: name and gameTypeId required'], Response::HTTP_BAD_REQUEST);
        }

        $gameType = $this->em->getRepository(GameType::class)->find($gameTypeId);
        if (!$gameType) {
            return $this->json(['error' => 'GameType not found'], Response::HTTP_NOT_FOUND);
        }

        $set = new GameSet();
        $set->setName($name);
        $set->setLibrary($library);
        $set->setGameType($gameType);

        $this->em->persist($set);
        $this->em->flush();

        return $this->json([
            'id' => $set->getId(),
            'name' => $set->getName(),
            'gameType' => [
                'id' => $gameType->getId(),
                'nom' => $gameType->getName(),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/me/sets/{setId}', name: 'api_me_set_cards', methods: ['GET'])]
    public function listSetCards(Request $request, int $setId): JsonResponse
    {
        $token = $this->extractBearerToken($request) ?? $request->query->get('apiToken');
        if (!$token) {
            return $this->json(['error' => 'No token provided'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['apiToken' => $token]);
        if (!$user) {
            return $this->json(['error' => 'Invalid token'], Response::HTTP_UNAUTHORIZED);
        }

        $set = $this->em->getRepository(GameSet::class)->find($setId);
        if (!$set) {
            return $this->json(['error' => 'Set not found'], Response::HTTP_NOT_FOUND);
        }

        $library = $user->getLibrary();
        if (!$library || $set->getLibrary()->getId() !== $library->getId()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $cards = [];
        foreach ($set->getCards() as $card) {
            $cards[] = [
                'id' => $card->getId(),
                'nom' => $card->getNom(),
                'extension' => $card->getExtension(),
                'numero' => $card->getNumero(),
                'gameType' => [
                    'id' => $card->getGameType()->getId(),
                    'nom' => $card->getGameType()->getName(),
                ],
            ];
        }

        return $this->json(['cards' => $cards]);
    }

    #[Route('/me/sets/{setId}/card', name: 'api_me_set_add_card', methods: ['POST'])]
    public function addCardToSet(Request $request, int $setId): JsonResponse
    {
        $token = $this->extractBearerToken($request) ?? $request->query->get('apiToken');
        if (!$token) {
            return $this->json(['error' => 'No token provided'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['apiToken' => $token]);
        if (!$user) {
            return $this->json(['error' => 'Invalid token'], Response::HTTP_UNAUTHORIZED);
        }

        $set = $this->em->getRepository(GameSet::class)->find($setId);
        if (!$set) {
            return $this->json(['error' => 'Set not found'], Response::HTTP_NOT_FOUND);
        }

        $library = $user->getLibrary();
        if (!$library || $set->getLibrary()->getId() !== $library->getId()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $cardId = $data['cardId'] ?? null;
        if (!$cardId) {
            return $this->json(['error' => 'Missing field: cardId'], Response::HTTP_BAD_REQUEST);
        }

        $card = $this->em->getRepository(Card::class)->find($cardId);
        if (!$card) {
            return $this->json(['error' => 'Card not found'], Response::HTTP_NOT_FOUND);
        }

        if ($card->getGameType()->getId() !== $set->getGameType()->getId()) {
            return $this->json(['error' => 'Card and set must share the same gameType'], Response::HTTP_BAD_REQUEST);
        }

        $set->addCard($card);
        $this->em->flush();

        return $this->json([
            'id' => $card->getId(),
            'nom' => $card->getNom(),
            'extension' => $card->getExtension(),
            'numero' => $card->getNumero(),
            'gameType' => [
                'id' => $card->getGameType()->getId(),
                'nom' => $card->getGameType()->getName(),
            ],
        ], Response::HTTP_OK);
    }
}
