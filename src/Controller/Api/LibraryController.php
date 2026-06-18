<?php

namespace App\Controller\Api;

use App\Entity\Card;
use App\Entity\User;
use App\Entity\Library;
use App\Entity\LibraryCard;
use App\Entity\Set as GameSet;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class LibraryController extends AbstractController
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

    private function getAuthenticatedUser(Request $request): User|JsonResponse
    {
        $token = $this->extractBearerToken($request) ?? $request->query->get('apiToken');
        if (!$token) {
            return $this->json(['error' => 'No token provided'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['apiToken' => $token]);
        if (!$user) {
            return $this->json(['error' => 'Invalid token'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isApiTokenExpired()) {
            return $this->json(['error' => 'Token expired'], Response::HTTP_UNAUTHORIZED);
        }

        return $user;
    }

    private function getAuthenticatedLibrary(Request $request): Library|JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $library = $user->getLibrary();
        if (!$library) {
            return $this->json(['error' => 'Library not found'], Response::HTTP_NOT_FOUND);
        }

        return $library;
    }

    private function libraryCardToArray(LibraryCard $libraryCard): array
    {
        $card = $libraryCard->getCard();

        return [
            'id' => $card->getId(),
            'name' => $card->getName(),
            'extension' => $card->getExtension(),
            'number' => $card->getNumber(),
            'image' => $card->getImage(),
            'numberCard' => $libraryCard->getNumberCard(),
            'isFavorite' => $libraryCard->isFavorite(),
            'gameType' => [
                'id' => $card->getGameType()->getId(),
                'nom' => $card->getGameType()->getName(),
            ],
        ];
    }

    private function findLibraryCard(Library $library, int $cardId): ?LibraryCard
    {
        return $this->em->getRepository(LibraryCard::class)->createQueryBuilder('lc')
            ->where('lc.library = :library')
            ->andWhere('IDENTITY(lc.card) = :cardId')
            ->setParameter('library', $library)
            ->setParameter('cardId', $cardId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    #[Route('/me/library', name: 'api_me_library', methods: ['GET'])]
    public function getMyLibrary(Request $request): JsonResponse
    {
        $library = $this->getAuthenticatedLibrary($request);
        if ($library instanceof JsonResponse) {
            return $library;
        }

        $sets = [];
        foreach ($library->getSets() as $set) {
            $sets[] = [
                'id' => $set->getId(),
                'name' => $set->getName(),
                'color' => $set->getColor(),
                'gameType' => [
                    'id' => $set->getGameType()->getId(),
                    'name' => $set->getGameType()->getName(),
                    'abbreviated' => $set->getGameType()->getAbbreviated(),
                    'url' => $set->getGameType()->getUrl(),
                ],
            ];
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 25;

        $query = $this->em->getRepository(LibraryCard::class)->createQueryBuilder('lc')
            ->join('lc.card', 'c')
            ->where('lc.library = :library')
            ->setParameter('library', $library)
            ->orderBy('c.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        $paginator = new Paginator($query, true);
        $total = count($paginator);
        $pages = (int) ceil($total / $limit);

        $cardsResult = [];
        foreach ($paginator as $libraryCard) {
            $cardsResult[] = $this->libraryCardToArray($libraryCard);
        }

        return $this->json([
            'id' => $library->getId(),
            'userId' => $library->getUser()->getId(),
            'sets' => $sets,
            'cards' => $cardsResult,
            'pagination' => [
                'page' => $page,
                'perPage' => $limit,
                'total' => $total,
                'pages' => $pages,
            ],
        ]);
    }

    #[Route('/me/library/search', name: 'api_me_library_search', methods: ['GET'])]
    public function searchMyLibrary(Request $request): JsonResponse
    {
        $library = $this->getAuthenticatedLibrary($request);
        if ($library instanceof JsonResponse) {
            return $library;
        }

        $search = trim((string) $request->query->get('q', ''));
        if ($search === '') {
            return $this->json(['error' => 'Missing query parameter: q'], Response::HTTP_BAD_REQUEST);
        }

        $limit = min(100, max(1, (int) $request->query->get('limit', 25)));
        $searchLike = '%' . strtolower($search) . '%';

        $sets = $this->em->getRepository(GameSet::class)->createQueryBuilder('s')
            ->join('s.gameType', 'sgt')
            ->where('s.library = :library')
            ->andWhere('LOWER(s.name) LIKE :search OR LOWER(sgt.name) LIKE :search')
            ->setParameter('library', $library)
            ->setParameter('search', $searchLike)
            ->orderBy('s.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $libraryCards = $this->em->getRepository(LibraryCard::class)->createQueryBuilder('lc')
            ->join('lc.card', 'c')
            ->where('lc.library = :library')
            ->andWhere('LOWER(c.name) LIKE :search OR LOWER(c.extension) LIKE :search OR LOWER(c.number) LIKE :search')
            ->setParameter('library', $library)
            ->setParameter('search', $searchLike)
            ->orderBy('c.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $setsResult = [];
        foreach ($sets as $set) {
            $setsResult[] = [
                'id' => $set->getId(),
                'name' => $set->getName(),
                'color' => $set->getColor(),
                'gameType' => [
                    'id' => $set->getGameType()->getId(),
                    'nom' => $set->getGameType()->getName(),
                    'abbreviated' => $set->getGameType()->getAbbreviated(),
                    'url' => $set->getGameType()->getUrl(),
                ],
            ];
        }

        $cardsResult = [];
        foreach ($libraryCards as $libraryCard) {
            $cardsResult[] = $this->libraryCardToArray($libraryCard);
        }

        return $this->json([
            'query' => $search,
            'sets' => $setsResult,
            'cards' => $cardsResult,
        ]);
    }

    #[Route('/me/library/cards', name: 'api_me_library_add_card', methods: ['POST'])]
    public function addCardToLibrary(Request $request): JsonResponse
    {
        $library = $this->getAuthenticatedLibrary($request);
        if ($library instanceof JsonResponse) {
            return $library;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $cardId = $data['cardId'] ?? null;
        $numberCard = (int) ($data['numberCard'] ?? 1);
        $isFavorite = (bool) ($data['isFavorite'] ?? false);

        if (!$cardId) {
            return $this->json(['error' => 'Missing field: cardId'], Response::HTTP_BAD_REQUEST);
        }

        if ($numberCard < 0) {
            return $this->json(['error' => 'numberCard must be greater than or equal to 0'], Response::HTTP_BAD_REQUEST);
        }

        $card = $this->em->getRepository(Card::class)->find($cardId);
        if (!$card) {
            return $this->json(['error' => 'Card not found'], Response::HTTP_NOT_FOUND);
        }

        $libraryCard = $this->em->getRepository(LibraryCard::class)->findOneBy([
            'card' => $card,
            'library' => $library,
        ]);

        if ($libraryCard) {
            return $this->json(['error' => 'Card already exists in library'], Response::HTTP_CONFLICT);
        }

        $libraryCard = new LibraryCard();
        $libraryCard->setCard($card);
        $libraryCard->setLibrary($library);
        $libraryCard->setNumberCard($numberCard);
        $libraryCard->setIsFavorite($isFavorite);

        $this->em->persist($libraryCard);
        $this->em->flush();

        return $this->json($this->libraryCardToArray($libraryCard), Response::HTTP_CREATED);
    }

    #[Route('/me/library/cards/{cardId}', name: 'api_me_library_card_get', methods: ['GET'])]
    public function getLibraryCard(Request $request, int $cardId): JsonResponse
    {
        $library = $this->getAuthenticatedLibrary($request);
        if ($library instanceof JsonResponse) {
            return $library;
        }

        $libraryCard = $this->findLibraryCard($library, $cardId);

        if (!$libraryCard) {
            return $this->json(['error' => 'Card not found in library'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->libraryCardToArray($libraryCard));
    }

    #[Route('/me/library/cards/{cardId}/count', name: 'api_me_library_card_count', methods: ['GET'])]
    public function getLibraryCardCount(Request $request, int $cardId): JsonResponse
    {
        $library = $this->getAuthenticatedLibrary($request);
        if ($library instanceof JsonResponse) {
            return $library;
        }

        $libraryCard = $this->findLibraryCard($library, $cardId);

        if (!$libraryCard) {
            return $this->json(['error' => 'Card not found in library'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'cardId' => $cardId,
            'libraryId' => $library->getId(),
            'numberCard' => $libraryCard->getNumberCard(),
        ]);
    }

    #[Route('/me/library/cards/{cardId}', name: 'api_me_library_card_update', methods: ['PUT'])]
    public function updateLibraryCard(Request $request, int $cardId): JsonResponse
    {
        $library = $this->getAuthenticatedLibrary($request);
        if ($library instanceof JsonResponse) {
            return $library;
        }

        $libraryCard = $this->findLibraryCard($library, $cardId);

        if (!$libraryCard) {
            return $this->json(['error' => 'Card not found in library'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('numberCard', $data)) {
            $numberCard = (int) $data['numberCard'];
            if ($numberCard < 0) {
                return $this->json(['error' => 'numberCard must be greater than or equal to 0'], Response::HTTP_BAD_REQUEST);
            }

            $libraryCard->setNumberCard($numberCard);
        }

        if (array_key_exists('isFavorite', $data)) {
            $libraryCard->setIsFavorite((bool) $data['isFavorite']);
        }

        $this->em->flush();

        return $this->json($this->libraryCardToArray($libraryCard));
    }

    #[Route('/me/library/cards/{cardId}/favorite', name: 'api_me_library_card_add_favorite', methods: ['POST'])]
    public function addLibraryCardFavorite(Request $request, int $cardId): JsonResponse
    {
        $library = $this->getAuthenticatedLibrary($request);
        if ($library instanceof JsonResponse) {
            return $library;
        }

        $libraryCard = $this->findLibraryCard($library, $cardId);

        if (!$libraryCard) {
            return $this->json(['error' => 'Card not found in library'], Response::HTTP_NOT_FOUND);
        }

        $libraryCard->setIsFavorite(true);
        $this->em->flush();

        return $this->json($this->libraryCardToArray($libraryCard));
    }

    #[Route('/me/library/cards/{cardId}/favorite', name: 'api_me_library_card_remove_favorite', methods: ['DELETE'])]
    public function removeLibraryCardFavorite(Request $request, int $cardId): JsonResponse
    {
        $library = $this->getAuthenticatedLibrary($request);
        if ($library instanceof JsonResponse) {
            return $library;
        }

        $libraryCard = $this->findLibraryCard($library, $cardId);

        if (!$libraryCard) {
            return $this->json(['error' => 'Card not found in library'], Response::HTTP_NOT_FOUND);
        }

        $libraryCard->setIsFavorite(false);
        $this->em->flush();

        return $this->json($this->libraryCardToArray($libraryCard));
    }

    #[Route('/me/library/cards/{cardId}', name: 'api_me_library_card_delete', methods: ['DELETE'])]
    public function removeLibraryCard(Request $request, int $cardId): JsonResponse
    {
        $library = $this->getAuthenticatedLibrary($request);
        if ($library instanceof JsonResponse) {
            return $library;
        }

        $libraryCard = $this->findLibraryCard($library, $cardId);

        if (!$libraryCard) {
            return $this->json(['error' => 'Card not found in library'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($libraryCard);
        $this->em->flush();

        return $this->json(['message' => 'Card removed from library successfully']);
    }
}
