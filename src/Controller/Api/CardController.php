<?php

namespace App\Controller\Api;

use App\Entity\Card;
use App\Entity\LibraryCard;
use App\Entity\Library;

use App\Entity\GameType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;

#[Route('/api')]
class CardController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    private function cardToArray(Card $card, ?LibraryCard $libraryCard = null): array
    {
        $data = [
            'id'        => $card->getId(),
            'name'      => $card->getName(),
            'extension' => $card->getExtension(),
            'number'    => $card->getNumber(),
            'image'     => $card->getImage(),
            'gameType'  => [
                'id'  => $card->getGameType()->getId(),
                'nom' => $card->getGameType()->getName(),
                'abbreviated' => $card->getGameType()->getAbbreviated(),
                'url' => $card->getGameType()->getUrl(),
            ],
        ];

        if ($libraryCard !== null) {
            $data['numberCard'] = $libraryCard->getNumberCard();
            $data['isFavorite'] = $libraryCard->isFavorite();
        }

        return $data;
    }

    // CRUD

    #[Route('/cards', name: 'api_cards_list', methods: ['GET'])]
    public function listCards(Request $request): JsonResponse
    {
        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 25)));

        $query = $this->em->getRepository(Card::class)->createQueryBuilder('c')
            ->orderBy('c.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        $paginator = new Paginator($query, true);
        $total     = count($paginator);
        $pages     = (int) ceil($total / $limit);

        $library = $this->getOptionalLibrary($request);

        $cards = [];
        foreach ($paginator as $card) {
            $libraryCard = $library ? $this->findLibraryCard($library, $card) : null;
            $cards[]     = $this->cardToArray($card, $libraryCard);
        }

        return $this->json([
            'cards'      => $cards,
            'pagination' => [
                'page'    => $page,
                'perPage' => $limit,
                'total'   => $total,
                'pages'   => $pages,
            ],
        ]);
    }

    #[Route('/cards', name: 'api_create_card', methods: ['POST'])]
    public function createCard(Request $request): JsonResponse
    {
        $data       = json_decode($request->getContent(), true);
        $name       = $data['name']       ?? null;
        $extension  = $data['extension']  ?? null;
        $number     = $data['number']     ?? null;
        $image      = $data['image']      ?? null;
        $gameTypeId = $data['gameTypeId'] ?? null;

        if (!$name || !$extension || !$number || !$gameTypeId) {
            return $this->json(
                ['error' => 'Missing fields: name, extension, number and gameTypeId required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $uuid = strtolower($name . '_' . $number . '_' . $extension);

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
        $card->setUuid($uuid);

        $this->em->persist($card);
        $this->em->flush();

        return $this->json($this->cardToArray($card), Response::HTTP_CREATED);
    }

    #[Route('/card/{id}', name: 'api_card_get_one', methods: ['GET'])]
    public function getOneCard(Request $request, int $id): JsonResponse
    {
        $card = $this->em->getRepository(Card::class)->find($id);
        if (!$card) {
            return $this->json(['error' => 'Card not found'], Response::HTTP_NOT_FOUND);
        }

        $library     = $this->getOptionalLibrary($request);
        $libraryCard = $library ? $this->findLibraryCard($library, $card) : null;

        return $this->json($this->cardToArray($card, $libraryCard));
    }

    #[Route('/card/{id}', name: 'api_card_update', methods: ['PUT'])]
    public function updateCard(Request $request, int $id): JsonResponse
    {
        $card = $this->em->getRepository(Card::class)->find($id);
        if (!$card) {
            return $this->json(['error' => 'Card not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('name', $data)) {
            if (!$data['name']) {
                return $this->json(['error' => 'Name cannot be empty'], Response::HTTP_BAD_REQUEST);
            }
            $card->setName($data['name']);
        }

        if (array_key_exists('extension', $data)) {
            if (!$data['extension']) {
                return $this->json(['error' => 'Extension cannot be empty'], Response::HTTP_BAD_REQUEST);
            }
            $card->setExtension($data['extension']);
        }

        if (array_key_exists('number', $data)) {
            if (!$data['number']) {
                return $this->json(['error' => 'Number cannot be empty'], Response::HTTP_BAD_REQUEST);
            }
            $card->setNumber($data['number']);
        }

        if (array_key_exists('image', $data)) {
            $card->setImage($data['image']);
        }

        if (array_key_exists('gameTypeId', $data)) {
            $gameType = $this->em->getRepository(GameType::class)->find($data['gameTypeId']);
            if (!$gameType) {
                return $this->json(['error' => 'GameType not found'], Response::HTTP_NOT_FOUND);
            }
            $card->setGameType($gameType);
        }

        $this->em->flush();

        return $this->json($this->cardToArray($card));
    }

    #[Route('/card/{id}', name: 'api_card_delete', methods: ['DELETE'])]
    public function deleteCard(int $id): JsonResponse
    {
        $card = $this->em->getRepository(Card::class)->find($id);
        if (!$card) {
            return $this->json(['error' => 'Card not found'], Response::HTTP_NOT_FOUND);
        }

        foreach ($card->getSets() as $set) {
            $set->removeCard($card);
        }

        $this->em->remove($card);
        $this->em->flush();

        return $this->json(['message' => 'Card deleted successfully']);
    }

    //Route Custom

    #[Route('/cards/add-user-card', name: 'api_add_user_card', methods: ['POST'])]
    public function AddUserCard(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->prepareUserCard($data);
        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], $result['status']);
        }

        $this->em->flush();

        return $this->json(
            $this->userCardToArray($result['card'], $result['libraryCard']),
            $result['status']
        );
    }

    #[Route('/cards/add-user-card/list', name: 'api_add_user_card_list', methods: ['POST'])]
    public function addUserCardList(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $cardsData = $data['cards'] ?? $data;
        if (!is_array($cardsData) || $cardsData === []) {
            return $this->json(['error' => 'Missing field: cards'], Response::HTTP_BAD_REQUEST);
        }

        $results = [];
        $seen = [];

        foreach ($cardsData as $index => $cardData) {
            if (!is_array($cardData)) {
                return $this->json([
                    'error' => 'Invalid card data',
                    'index' => $index,
                ], Response::HTTP_BAD_REQUEST);
            }

            $uuid = $this->buildCardUuid(
                $cardData['name'] ?? null,
                $cardData['number'] ?? null,
                $cardData['extension'] ?? null
            );
            $libraryId = $cardData['libraryId'] ?? null;
            $duplicateKey = $libraryId . ':' . $uuid;

            if ($uuid && $libraryId && isset($seen[$duplicateKey])) {
                return $this->json([
                    'error' => 'Card already exists in request',
                    'index' => $index,
                ], Response::HTTP_CONFLICT);
            }

            $result = $this->prepareUserCard($cardData);
            if (isset($result['error'])) {
                return $this->json([
                    'error' => $result['error'],
                    'index' => $index,
                ], $result['status']);
            }

            $seen[$duplicateKey] = true;
            $results[] = $result;
        }

        $this->em->flush();

        $cards = [];
        $created = 0;
        foreach ($results as $result) {
            if ($result['status'] === Response::HTTP_CREATED) {
                ++$created;
            }

            $cards[] = $this->userCardToArray($result['card'], $result['libraryCard']);
        }

        return $this->json([
            'cards' => $cards,
            'count' => count($cards),
            'created' => $created,
            'existing' => count($cards) - $created,
        ], Response::HTTP_CREATED);
    }

    private function prepareUserCard(array $data): array
    {
        $name = $data['name'] ?? null;
        $extension = $data['extension'] ?? null;
        $number = $data['number'] ?? null;
        $image = $data['image'] ?? null;
        $gameTypeId = $data['gameTypeId'] ?? null;
        $libraryId = $data['libraryId'] ?? null;
        $numberCard = (int) ($data['numberCard'] ?? 1);
        $isFavorite = (bool) ($data['isFavorite'] ?? false);

        if (!$name || !$extension || !$number || !$gameTypeId || !$libraryId) {
            return [
                'error' => 'Missing fields: name, extension, number, gameTypeId and libraryId required',
                'status' => Response::HTTP_BAD_REQUEST,
            ];
        }

        if ($numberCard < 0) {
            return [
                'error' => 'numberCard must be greater than or equal to 0',
                'status' => Response::HTTP_BAD_REQUEST,
            ];
        }

        $uuid = $this->buildCardUuid($name, $number, $extension);

        $gameType = $this->em->getRepository(GameType::class)->find($gameTypeId);
        if (!$gameType) {
            return [
                'error' => 'GameType not found',
                'status' => Response::HTTP_NOT_FOUND,
            ];
        }

        $library = $this->em->getRepository(Library::class)->find($libraryId);
        if (!$library) {
            return [
                'error' => 'Library not found',
                'status' => Response::HTTP_NOT_FOUND,
            ];
        }

        $card = $this->em->getRepository(Card::class)->findOneBy([
            'uuid' => $uuid,
        ]);

        $status = Response::HTTP_OK;

        if ($card) {
            $libraryCard = $this->em->getRepository(LibraryCard::class)->findOneBy([
                'card' => $card,
                'library' => $library,
            ]);

            if ($libraryCard) {
                return [
                    'error' => 'Card already exists in library',
                    'status' => Response::HTTP_CONFLICT,
                ];
            }
        } else {
            $card = new Card();
            $card->setName($name);
            $card->setExtension($extension);
            $card->setNumber($number);
            $card->setImage($image);
            $card->setGameType($gameType);
            $card->setUuid($uuid);

            $this->em->persist($card);
            $status = Response::HTTP_CREATED;
        }

        $libraryCard = new LibraryCard();
        $libraryCard->setCard($card);
        $libraryCard->setLibrary($library);
        $libraryCard->setNumberCard($numberCard);
        $libraryCard->setIsFavorite($isFavorite);

        $this->em->persist($libraryCard);

        return [
            'card' => $card,
            'libraryCard' => $libraryCard,
            'status' => $status,
        ];
    }

    private function buildCardUuid(?string $name, ?string $number, ?string $extension): ?string
    {
        if (!$name || !$number || !$extension) {
            return null;
        }

        return strtolower($name . '_' . $number . '_' . $extension);
    }

    private function userCardToArray(Card $card, LibraryCard $libraryCard): array
    {
        $gameType = $card->getGameType();

        return [
            'id' => $card->getId(),
            'name' => $card->getName(),
            'extension' => $card->getExtension(),
            'number' => $card->getNumber(),
            'image' => $card->getImage(),
            'uuid' => $card->getUuid(),
            'numberCard' => $libraryCard->getNumberCard(),
            'isFavorite' => $libraryCard->isFavorite(),
            'gameType' => [
                'id' => $gameType->getId(),
                'nom' => $gameType->getName(),
                'abbreviated' => $gameType->getAbbreviated(),
                'url' => $gameType->getUrl(),
            ],
        ];
    }

    private function getOptionalLibrary(Request $request): ?Library
    {
        $auth = $request->headers->get('Authorization');
        $token = null;

        if ($auth && stripos($auth, 'Bearer ') === 0) {
            $token = substr($auth, 7);
        } else {
            $token = $request->query->get('apiToken');
        }

        if (!$token) {
            return null;
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['apiToken' => $token]);

        if ($user?->isApiTokenExpired()) {
            return null;
        }

        return $user?->getLibrary();
    }

    private function findLibraryCard(Library $library, Card $card): ?LibraryCard
    {
        return $this->em->getRepository(LibraryCard::class)->findOneBy([
            'library' => $library,
            'card'    => $card,
        ]);
    }
}
