<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;

final class SetControllerTest extends ApiTestCase
{
    public function testCreateUpdateAddCardListAndDeleteSet(): void
    {
        $gameType = $this->createGameType();
        $card = $this->createCard($gameType);
        $user = $this->registerUser();
        $headers = $this->bearer($user['token']);

        $this->jsonRequest('POST', '/api/me/sets', [
            'name' => 'Deck principal',
            'gameTypeId' => $gameType->getId(),
            'color' => '#3366AA',
        ], $headers);

        self::assertResponseStatusCodeSame(201);
        $set = $this->jsonResponse();
        self::assertSame('Deck principal', $set['name']);
        self::assertSame('#3366AA', $set['color']);

        $this->jsonRequest('GET', '/api/me/sets', null, $headers);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->jsonResponse()['sets']);

        $this->jsonRequest('PUT', '/api/me/sets/' . $set['id'], [
            'name' => 'Deck combat',
            'color' => '#FFAA00',
        ], $headers);

        self::assertResponseIsSuccessful();
        $updated = $this->jsonResponse();
        self::assertSame('Deck combat', $updated['name']);
        self::assertSame('#FFAA00', $updated['color']);

        $this->jsonRequest('POST', '/api/me/sets/' . $set['id'] . '/card', [
            'cardId' => $card->getId(),
        ], $headers);

        self::assertResponseIsSuccessful();
        self::assertSame('Pikachu', $this->jsonResponse()['name']);

        $this->jsonRequest('GET', '/api/me/sets/' . $set['id'], null, $headers);
        self::assertResponseIsSuccessful();
        $cards = $this->jsonResponse();
        self::assertSame(1, $cards['pagination']['total']);
        self::assertSame('Pikachu', $cards['cards'][0]['name']);

        $this->jsonRequest('DELETE', '/api/me/sets/' . $set['id'] . '/card/' . $card->getId(), null, $headers);
        self::assertResponseIsSuccessful();
        self::assertSame(['message' => 'Card removed from set successfully'], $this->jsonResponse());

        $this->jsonRequest('DELETE', '/api/me/sets/' . $set['id'], null, $headers);
        self::assertResponseIsSuccessful();
        self::assertSame(['message' => 'Set deleted successfully'], $this->jsonResponse());
    }

    public function testCreateSetRejectsInvalidColorAndUnknownGameType(): void
    {
        $user = $this->registerUser();
        $headers = $this->bearer($user['token']);

        $this->jsonRequest('POST', '/api/me/sets', [
            'name' => 'Deck principal',
            'gameTypeId' => 999999,
            'color' => 'blue',
        ], $headers);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'Invalid color format, expected hex like #RRGGBB'], $this->jsonResponse());

        $this->jsonRequest('POST', '/api/me/sets', [
            'name' => 'Deck principal',
            'gameTypeId' => 999999,
            'color' => '#3366AA',
        ], $headers);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'GameType not found'], $this->jsonResponse());
    }

    public function testCannotAccessAnotherUsersSet(): void
    {
        $gameType = $this->createGameType();
        $owner = $this->registerUser('owner@example.test');
        $ownerHeaders = $this->bearer($owner['token']);

        $this->jsonRequest('POST', '/api/me/sets', [
            'name' => 'Deck prive',
            'gameTypeId' => $gameType->getId(),
        ], $ownerHeaders);
        self::assertResponseStatusCodeSame(201);
        $set = $this->jsonResponse();

        $other = $this->registerUser('other@example.test');

        $this->jsonRequest('GET', '/api/me/sets/' . $set['id'], null, $this->bearer($other['token']));

        self::assertResponseStatusCodeSame(403);
        self::assertSame(['error' => 'Access denied'], $this->jsonResponse());
    }
}
