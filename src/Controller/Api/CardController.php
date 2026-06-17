<?php

namespace App\Controller\Api;

use App\Entity\Card;
use App\Entity\Library;

use App\Entity\GameType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    private function cardToArray(Card $card): array
    {
        return [
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

    // CRUD

    #[Route('/cards', name: 'api_cards_list', methods: ['GET'])]
    public function listCards(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 25)));

        $query = $this->em->getRepository(Card::class)->createQueryBuilder('c')
            ->orderBy('c.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        $paginator = new Paginator($query, true);
        $total = count($paginator);
        $pages = (int) ceil($total / $limit);

        $cards = [];
        foreach ($paginator as $card) {
            $cards[] = $this->cardToArray($card);
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

    #[Route('/cards', name: 'api_create_card', methods: ['POST'])]
    public function createCard(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $name = $data['name'] ?? null;
        $extension = $data['extension'] ?? null;
        $number = $data['number'] ?? null;
        $image = $data['image'] ?? null;
        $gameTypeId = $data['gameTypeId'] ?? null;

        if (!$name || !$extension || !$number || !$gameTypeId) {
            return $this->json(['error' => 'Missing fields: name, extension, number and gameTypeId required'], Response::HTTP_BAD_REQUEST);
        }

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

        $this->em->persist($card);
        $this->em->flush();

        return $this->json($this->cardToArray($card), Response::HTTP_CREATED);
    }

    #[Route('/card/{id}', name: 'api_card_get_one', methods: ['GET'])]
    public function getOneCard(int $id): JsonResponse
    {
        $card = $this->em->getRepository(Card::class)->find($id);
        if (!$card) {
            return $this->json(['error' => 'Card not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->cardToArray($card));
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

}
