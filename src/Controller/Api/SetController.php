<?php

namespace App\Controller\Api;

use App\Entity\Card;
use App\Entity\User;
use App\Entity\Set as GameSet;
use App\Entity\GameType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    private function getAuthenticatedUser(): User|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        return $user;
    }

    private function setToArray(GameSet $set): array
    {
        return [
            'id' => $set->getId(),
            'name' => $set->getName(),
            'color' => $set->getColor(),
            'gameType' => [
                'id' => $set->getGameType()->getId(),
                'nom' => $set->getGameType()->getName(),
            ],
        ];
    }

    #[Route('/me/sets', name: 'api_me_sets', methods: ['GET'])]
    public function listSets(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
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
                'color' => $set->getColor(),
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
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $library = $user->getLibrary();
        if (!$library) {
            return $this->json(['error' => 'Library not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $name = $data['name'] ?? null;
        $gameTypeId = $data['gameTypeId'] ?? null;
        $color = $data['color'] ?? '#FFFFFF';

        if (!$name || !$gameTypeId) {
            return $this->json(['error' => 'Missing fields: name and gameTypeId required'], Response::HTTP_BAD_REQUEST);
        }

        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return $this->json(['error' => 'Invalid color format, expected hex like #RRGGBB'], Response::HTTP_BAD_REQUEST);
        }

        $gameType = $this->em->getRepository(GameType::class)->find($gameTypeId);
        if (!$gameType) {
            return $this->json(['error' => 'GameType not found'], Response::HTTP_NOT_FOUND);
        }

        $set = new GameSet();
        $set->setName($name);
        $set->setColor($color);
        $set->setLibrary($library);
        $set->setGameType($gameType);

        $this->em->persist($set);
        $this->em->flush();

        return $this->json([
            'id' => $set->getId(),
            'name' => $set->getName(),
            'color' => $set->getColor(),
            'gameType' => [
                'id' => $gameType->getId(),
                'nom' => $gameType->getName(),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/me/sets/{setId}', name: 'api_me_set_cards', methods: ['GET'])]
    public function listSetCards(Request $request, int $setId): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $set = $this->em->getRepository(GameSet::class)->find($setId);
        if (!$set) {
            return $this->json(['error' => 'Set not found'], Response::HTTP_NOT_FOUND);
        }

        $library = $user->getLibrary();
        if (!$library || $set->getLibrary()->getId() !== $library->getId()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 25;

        $query = $this->em->getRepository(Card::class)->createQueryBuilder('c')
            ->join('c.sets', 's')
            ->where('s.id = :setId')
            ->setParameter('setId', $setId)
            ->orderBy('c.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        $paginator = new Paginator($query, true);
        $total = count($paginator);
        $pages = (int) ceil($total / $limit);

        $cards = [];
        foreach ($paginator as $card) {
            $cards[] = [
                'id' => $card->getId(),
                'name' => $card->getName(),
                'extension' => $card->getExtension(),
                'number' => $card->getNumber(),
                'image' => $card->getImage(),
                'gameType' => [
                    'id' => $card->getGameType()->getId(),
                    'nom' => $card->getGameType()->getName(),
                ],
            ];
        }

        return $this->json([
            'cards' => $cards,
            'pagination' => [
                'page' => $page,
                'perPage' => $limit,
                'total' => $total,
                'pages' => $pages,
            ],
        ]);
    }

    #[Route('/me/sets/{setId}', name: 'api_me_set_update', methods: ['PUT'])]
    public function updateSet(Request $request, int $setId): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
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
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('name', $data)) {
            if (!$data['name']) {
                return $this->json(['error' => 'Name cannot be empty'], Response::HTTP_BAD_REQUEST);
            }
            $set->setName($data['name']);
        }

        if (array_key_exists('color', $data)) {
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['color'])) {
                return $this->json(['error' => 'Invalid color format, expected hex like #RRGGBB'], Response::HTTP_BAD_REQUEST);
            }
            $set->setColor($data['color']);
        }

        if (array_key_exists('gameTypeId', $data)) {
            $gameType = $this->em->getRepository(GameType::class)->find($data['gameTypeId']);
            if (!$gameType) {
                return $this->json(['error' => 'GameType not found'], Response::HTTP_NOT_FOUND);
            }

            foreach ($set->getCards() as $card) {
                if ($card->getGameType()->getId() !== $gameType->getId()) {
                    return $this->json(['error' => 'Cannot change gameType while set contains cards from another gameType'], Response::HTTP_BAD_REQUEST);
                }
            }

            $set->setGameType($gameType);
        }

        $this->em->flush();

        return $this->json($this->setToArray($set));
    }

    #[Route('/me/sets/{setId}', name: 'api_me_set_delete', methods: ['DELETE'])]
    public function deleteSet(Request $request, int $setId): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $set = $this->em->getRepository(GameSet::class)->find($setId);
        if (!$set) {
            return $this->json(['error' => 'Set not found'], Response::HTTP_NOT_FOUND);
        }

        $library = $user->getLibrary();
        if (!$library || $set->getLibrary()->getId() !== $library->getId()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        foreach ($set->getCards() as $card) {
            $set->removeCard($card);
        }

        $this->em->remove($set);
        $this->em->flush();

        return $this->json(['message' => 'Set deleted successfully']);
    }

    #[Route('/me/sets/{setId}/card', name: 'api_me_set_add_card', methods: ['POST'])]
    public function addCardToSet(Request $request, int $setId): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
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
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

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
            'name' => $card->getName(),
            'extension' => $card->getExtension(),
            'number' => $card->getNumber(),
            'image' => $card->getImage(),
            'gameType' => [
                'id' => $card->getGameType()->getId(),
                'nom' => $card->getGameType()->getName(),
            ],
        ], Response::HTTP_OK);
    }

    #[Route('/me/sets/{setId}/card/{cardId}', name: 'api_me_set_remove_card', methods: ['DELETE'])]
    public function removeCardFromSet(Request $request, int $setId, int $cardId): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $set = $this->em->getRepository(GameSet::class)->find($setId);
        if (!$set) {
            return $this->json(['error' => 'Set not found'], Response::HTTP_NOT_FOUND);
        }

        $library = $user->getLibrary();
        if (!$library || $set->getLibrary()->getId() !== $library->getId()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $card = $this->em->getRepository(Card::class)->find($cardId);
        if (!$card) {
            return $this->json(['error' => 'Card not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$set->getCards()->contains($card)) {
            return $this->json(['error' => 'Card is not in this set'], Response::HTTP_NOT_FOUND);
        }

        $set->removeCard($card);
        $this->em->flush();

        return $this->json(['message' => 'Card removed from set successfully']);
    }
}
