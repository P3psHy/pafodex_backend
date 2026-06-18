<?php

namespace App\Tests\Controller;

use App\Entity\Card;
use App\Entity\LibraryCard;
use App\Tests\ApiTestCase;

final class CardControllerTest extends ApiTestCase
{
    public function testCreateListShowUpdateAndDeleteCard(): void
    {
        $gameType = $this->createGameType();

        $this->jsonRequest('POST', '/api/cards', [
            'name' => 'Pikachu',
            'extension' => 'Base',
            'number' => '025',
            'image' => 'https://example.test/pikachu.png',
            'gameTypeId' => $gameType->getId(),
        ]);

        self::assertResponseStatusCodeSame(201);
        $created = $this->jsonResponse();
        self::assertSame('Pikachu', $created['name']);
        self::assertSame('Pokemon', $created['gameType']['nom']);

        $this->jsonRequest('GET', '/api/cards?page=1&limit=10');
        self::assertResponseIsSuccessful();
        $list = $this->jsonResponse();
        self::assertSame(1, $list['pagination']['total']);
        self::assertSame('025', $list['cards'][0]['number']);

        $this->jsonRequest('GET', '/api/card/' . $created['id']);
        self::assertResponseIsSuccessful();
        self::assertSame('Base', $this->jsonResponse()['extension']);

        $this->jsonRequest('PUT', '/api/card/' . $created['id'], [
            'name' => 'Raichu',
            'image' => null,
        ]);

        self::assertResponseIsSuccessful();
        $updated = $this->jsonResponse();
        self::assertSame('Raichu', $updated['name']);
        self::assertNull($updated['image']);

        $this->jsonRequest('DELETE', '/api/card/' . $created['id']);
        self::assertResponseIsSuccessful();
        self::assertSame(['message' => 'Card deleted successfully'], $this->jsonResponse());

        self::assertNull($this->em->getRepository(Card::class)->find($created['id']));
    }

    public function testCreateCardRejectsMissingFieldsAndUnknownGameType(): void
    {
        $this->jsonRequest('POST', '/api/cards', [
            'name' => 'Pikachu',
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(
            ['error' => 'Missing fields: name, extension, number and gameTypeId required'],
            $this->jsonResponse()
        );

        $this->jsonRequest('POST', '/api/cards', [
            'name' => 'Pikachu',
            'extension' => 'Base',
            'number' => '025',
            'gameTypeId' => 999999,
        ]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'GameType not found'], $this->jsonResponse());
    }

    public function testAddUserCardCreatesCardAndLibraryCardThenRejectsDuplicate(): void
    {
        $gameType = $this->createGameType();
        $user = $this->registerUser();

        $payload = [
            'name' => 'Pikachu',
            'extension' => 'Base',
            'number' => '025',
            'image' => 'https://example.test/pikachu.png',
            'gameTypeId' => $gameType->getId(),
            'libraryId' => $user['libraryId'],
            'numberCard' => 2,
            'isFavorite' => true,
        ];

        $this->jsonRequest('POST', '/api/cards/add-user-card', $payload);

        self::assertResponseStatusCodeSame(201);
        $created = $this->jsonResponse();
        self::assertSame('pikachu_025_base', $created['uuid']);
        self::assertSame(2, $created['numberCard']);
        self::assertTrue($created['isFavorite']);

        self::assertCount(1, $this->em->getRepository(Card::class)->findAll());
        self::assertCount(1, $this->em->getRepository(LibraryCard::class)->findAll());

        $this->jsonRequest('POST', '/api/cards/add-user-card', $payload);

        self::assertResponseStatusCodeSame(409);
        self::assertSame(['error' => 'Card already exists in library'], $this->jsonResponse());
    }

    public function testAddUserCardListRejectsDuplicateInsideRequest(): void
    {
        $gameType = $this->createGameType();
        $user = $this->registerUser();
        $cardPayload = [
            'name' => 'Pikachu',
            'extension' => 'Base',
            'number' => '025',
            'gameTypeId' => $gameType->getId(),
            'libraryId' => $user['libraryId'],
        ];

        $this->jsonRequest('POST', '/api/cards/add-user-card/list', [
            'cards' => [$cardPayload, $cardPayload],
        ]);

        self::assertResponseStatusCodeSame(409);
        self::assertSame([
            'error' => 'Card already exists in request',
            'index' => 1,
        ], $this->jsonResponse());
    }
}
