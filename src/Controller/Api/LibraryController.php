<?php

namespace App\Controller\Api;

use App\Entity\Card;
use App\Entity\User;
use App\Entity\Library;
use Doctrine\ORM\EntityManagerInterface;
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

        $cards = $this->em->getRepository(Card::class)->createQueryBuilder('c')
            ->join('c.sets', 's')
            ->join('s.library', 'l')
            ->where('l.id = :libraryId')
            ->setParameter('libraryId', $library->getId())
            ->getQuery()
            ->getResult();

        $cardsResult = [];
        foreach ($cards as $card) {
            $cardsResult[] = [
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

        return $this->json([
            'id' => $library->getId(),
            'userId' => $library->getUser()->getId(),
            'sets' => $sets,
            'cards' => $cardsResult,
        ]);
    }
}
