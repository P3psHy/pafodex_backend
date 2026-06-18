<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;

final class LibraryControllerTest extends ApiTestCase
{
    public function testLibraryRequiresAuthentication(): void
    {
        $this->jsonRequest('GET', '/api/me/library');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'No token provided'], $this->jsonResponse());
    }

    public function testAddUpdateFavoriteSearchAndRemoveLibraryCard(): void
    {
        $gameType = $this->createGameType();
        $card = $this->createCard($gameType, ['name' => 'Pikachu']);
        $user = $this->registerUser();
        $headers = $this->bearer($user['token']);

        $this->jsonRequest('POST', '/api/me/library/cards', [
            'cardId' => $card->getId(),
            'numberCard' => 3,
            'isFavorite' => false,
        ], $headers);

        self::assertResponseStatusCodeSame(201);
        $libraryCard = $this->jsonResponse();
        self::assertSame(3, $libraryCard['numberCard']);
        self::assertFalse($libraryCard['isFavorite']);

        $this->jsonRequest('GET', '/api/me/library', null, $headers);
        self::assertResponseIsSuccessful();
        $library = $this->jsonResponse();
        self::assertSame($user['libraryId'], $library['id']);
        self::assertSame(1, $library['pagination']['total']);

        $this->jsonRequest('PUT', '/api/me/library/cards/' . $card->getId(), [
            'numberCard' => 5,
            'isFavorite' => true,
        ], $headers);

        self::assertResponseIsSuccessful();
        $updated = $this->jsonResponse();
        self::assertSame(5, $updated['numberCard']);
        self::assertTrue($updated['isFavorite']);

        $this->jsonRequest('GET', '/api/me/library/search?q=pika', null, $headers);
        self::assertResponseIsSuccessful();
        self::assertSame('Pikachu', $this->jsonResponse()['cards'][0]['name']);

        $this->jsonRequest('DELETE', '/api/me/library/cards/' . $card->getId(), null, $headers);
        self::assertResponseIsSuccessful();
        self::assertSame(['message' => 'Card removed from library successfully'], $this->jsonResponse());

        $this->jsonRequest('GET', '/api/me/library/cards/' . $card->getId(), null, $headers);
        self::assertResponseStatusCodeSame(404);
    }

    public function testLibraryCardRejectsNegativeCountAndDuplicate(): void
    {
        $gameType = $this->createGameType();
        $card = $this->createCard($gameType);
        $user = $this->registerUser();
        $headers = $this->bearer($user['token']);

        $this->jsonRequest('POST', '/api/me/library/cards', [
            'cardId' => $card->getId(),
            'numberCard' => -1,
        ], $headers);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'numberCard must be greater than or equal to 0'], $this->jsonResponse());

        $this->jsonRequest('POST', '/api/me/library/cards', [
            'cardId' => $card->getId(),
        ], $headers);
        self::assertResponseStatusCodeSame(201);

        $this->jsonRequest('POST', '/api/me/library/cards', [
            'cardId' => $card->getId(),
        ], $headers);

        self::assertResponseStatusCodeSame(409);
        self::assertSame(['error' => 'Card already exists in library'], $this->jsonResponse());
    }
}
