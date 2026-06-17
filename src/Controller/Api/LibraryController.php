<?php

namespace App\Controller\Api;

use App\Entity\Card;
use App\Entity\User;
use App\Entity\Library;
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

    #[Route('/me/library', name: 'api_me_library', methods: ['GET'])]
    public function getMyLibrary(Request $request): JsonResponse
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
                    'name' => $set->getGameType()->getName(),
                    'abbreviated' => $set->getGameType()->getAbbreviated(),
                ],
            ];
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 25;

        $query = $this->em->getRepository(Card::class)->createQueryBuilder('c')
            ->select('DISTINCT c')
            ->join('c.sets', 's')
            ->join('s.library', 'l')
            ->where('l.id = :libraryId')
            ->setParameter('libraryId', $library->getId())
            ->orderBy('c.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        $paginator = new Paginator($query, true);
        $total = count($paginator);
        $pages = (int) ceil($total / $limit);

        $cardsResult = [];
        foreach ($paginator as $card) {
            $cardsResult[] = [
                'id' => $card->getId(),
                'name' => $card->getName(),
                'extension' => $card->getExtension(),
                'number' => $card->getNumber(),
                'image' => $card->getImage(),
                'gameType' => [
                    'id' => $card->getGameType()->getId(),
                    'nom' => $card->getGameType()->getName(),
                    'abbreviated' => $card->getGameType()->getAbbreviated(),
                ],
            ];
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

        $cards = $this->em->getRepository(Card::class)->createQueryBuilder('c')
            ->select('DISTINCT c')
            ->join('c.sets', 's')
            ->join('s.library', 'l')
            ->join('c.gameType', 'cgt')
            ->where('l.id = :libraryId')
            ->andWhere('LOWER(c.name) LIKE :search OR LOWER(c.extension) LIKE :search OR LOWER(c.number) LIKE :search OR LOWER(cgt.name) LIKE :search')
            ->setParameter('libraryId', $library->getId())
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
                ],
            ];
        }

        $cardsResult = [];
        foreach ($cards as $card) {
            $cardsResult[] = [
                'id' => $card->getId(),
                'name' => $card->getName(),
                'extension' => $card->getExtension(),
                'number' => $card->getNumber(),
                'image' => $card->getImage(),
                'gameType' => [
                    'id' => $card->getGameType()->getId(),
                    'nom' => $card->getGameType()->getName(),
                    'abbreviated' => $card->getGameType()->getAbbreviated(),
                ],
            ];
        }

        return $this->json([
            'query' => $search,
            'sets' => $setsResult,
            'cards' => $cardsResult,
        ]);
    }
}
