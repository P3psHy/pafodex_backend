<?php

namespace App\Tests\Controller;

use App\Entity\GameType;
use App\Tests\ApiTestCase;

final class GameTypeControllerTest extends ApiTestCase
{
    public function testListReturnsGameTypesOrderedById(): void
    {
        $this->createGameType(['name' => 'Pokemon', 'abbreviated' => 'PKM']);
        $this->createGameType(['name' => 'Magic', 'abbreviated' => 'MTG']);

        $this->jsonRequest('GET', '/api/gametype');

        self::assertResponseIsSuccessful();
        $payload = $this->jsonResponse();

        self::assertCount(2, $payload['data']);
        self::assertSame('Pokemon', $payload['data'][0]['name']);
        self::assertSame('MTG', $payload['data'][1]['abbreviated']);
    }

    public function testCreateGameTypeValidatesRequiredFields(): void
    {
        $this->jsonRequest('POST', '/api/gametype', [
            'name' => 'Pokemon',
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(
            ['error' => 'Missing fields: name, abbreviated and url required'],
            $this->jsonResponse()
        );
    }

    public function testCreateShowUpdateAndDeleteGameType(): void
    {
        $this->jsonRequest('POST', '/api/gametype', [
            'name' => 'Pokemon',
            'abbreviated' => 'PKM',
            'url' => 'https://example.test/pokemon',
        ]);

        self::assertResponseStatusCodeSame(201);
        $created = $this->jsonResponse();
        self::assertSame('Pokemon', $created['name']);

        $this->jsonRequest('GET', '/api/gametype/' . $created['id']);
        self::assertResponseIsSuccessful();
        self::assertSame('https://example.test/pokemon', $this->jsonResponse()['url']);

        $this->jsonRequest('PUT', '/api/gametype/' . $created['id'], [
            'name' => 'Magic',
            'abbreviated' => 'MTG',
            'url' => 'https://example.test/magic',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('Magic', $this->jsonResponse()['name']);

        $this->jsonRequest('DELETE', '/api/gametype/' . $created['id']);
        self::assertResponseIsSuccessful();
        self::assertSame(['message' => 'Game type deleted successfully'], $this->jsonResponse());

        self::assertNull($this->em->getRepository(GameType::class)->find($created['id']));
    }

    public function testUnknownGameTypeReturnsNotFound(): void
    {
        $this->jsonRequest('GET', '/api/gametype/999999');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'Game type not found'], $this->jsonResponse());
    }
}
