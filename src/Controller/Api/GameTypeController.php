<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use App\Entity\GameType;

#[Route('/api')]
class GameTypeController extends AbstractController
{

    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/gametype', name: 'api_game_type_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $gameTypes = $this->em->getRepository(GameType::class)->findBy([], ['id' => 'ASC']);
        $data = [];

        foreach ($gameTypes as $gameType) {
            $data[] = [
                'id' => $gameType->getId(),
                'name' => $gameType->getName(),
                'abbreviated' => $gameType->getAbbreviated(),
            ];
        }

        return $this->json(['data' => $data]);
    }

    #[Route('/gametype', name: 'api_game_type_create', methods: ['POST'])]
    public function createGameType(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? null;
        $abbreviated = $data['abbreviated'] ?? null;

        if (!$name || !$abbreviated) {
            return $this->json(['error' => 'Missing fields: name and abbreviated required'], Response::HTTP_BAD_REQUEST);
        }

        $gameType = new GameType();
        $gameType->setName($name);
        $gameType->setAbbreviated($abbreviated);

        $this->em->persist($gameType);
        $this->em->flush();

        return $this->json([
            'id' => $gameType->getId(),
            'name' => $gameType->getName(),
            'abbreviated' => $gameType->getAbbreviated(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/gametype/{id}', name: 'api_game_type_show', methods: ['GET'])]
    public function getOneGameType(int $id): JsonResponse
    {
        $gameType = $this->em->getRepository(GameType::class)->find($id);
        if (!$gameType) {
            return $this->json(['error' => 'Game type not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $gameType->getId(),
            'name' => $gameType->getName(),
            'abbreviated' => $gameType->getAbbreviated(),
        ]);
    }

    #[Route('/gametype/{id}', name: 'api_game_type_update', methods: ['PUT'])]
    public function updateGameType(Request $request, int $id): JsonResponse
    {
        $gameType = $this->em->getRepository(GameType::class)->find($id);
        if (!$gameType) {
            return $this->json(['error' => 'Game type not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? null;
        $abbreviated = $data['abbreviated'] ?? null;

        if (!$name || !$abbreviated) {
            return $this->json(['error' => 'Missing fields: name and abbreviated required'], Response::HTTP_BAD_REQUEST);
        }

        $gameType->setName($name);
        $gameType->setAbbreviated($abbreviated);
        $this->em->flush();

        return $this->json([
            'id' => $gameType->getId(),
            'name' => $gameType->getName(),
            'abbreviated' => $gameType->getAbbreviated(),
        ]);
    }

    #[Route('/gametype/{id}', name: 'api_game_type_delete', methods: ['DELETE'])]
    public function deleteGameType(int $id): JsonResponse
    {
        $gameType = $this->em->getRepository(GameType::class)->find($id);

        if (!$gameType) {
            return $this->json(['error' => 'Game type not found'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($gameType);
        $this->em->flush();

        return $this->json(['message' => 'Game type deleted successfully']);
    }

}
