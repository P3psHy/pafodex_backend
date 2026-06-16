<?php

namespace App\Controller\Api;

use App\Entity\Card;
use App\Entity\User;
use App\Entity\Library;
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
}
