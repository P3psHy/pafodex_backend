<?php

namespace App\Tests;

use App\Entity\Card;
use App\Entity\GameType;
use App\Entity\Library;
use App\Entity\LibraryCard;
use App\Entity\Set as GameSet;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->resetDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em) && $this->em->isOpen()) {
            $this->em->clear();
        }
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string> $headers
     */
    protected function jsonRequest(string $method, string $uri, ?array $payload = null, array $headers = []): void
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $this->client->request(
            $method,
            $uri,
            [],
            [],
            $server,
            $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();

        self::assertIsString($content);
        self::assertJson($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function createGameType(array $overrides = []): GameType
    {
        $gameType = (new GameType())
            ->setName($overrides['name'] ?? 'Pokemon')
            ->setAbbreviated($overrides['abbreviated'] ?? 'PKM')
            ->setUrl($overrides['url'] ?? 'https://example.test/pokemon');

        $this->em->persist($gameType);
        $this->em->flush();

        return $gameType;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function createCard(GameType $gameType, array $overrides = []): Card
    {
        $name = $overrides['name'] ?? 'Pikachu';
        $number = $overrides['number'] ?? '025';
        $extension = $overrides['extension'] ?? 'Base';

        $card = (new Card())
            ->setName($name)
            ->setExtension($extension)
            ->setNumber($number)
            ->setImage($overrides['image'] ?? 'https://example.test/pikachu.png')
            ->setGameType($gameType)
            ->setUuid(strtolower($name . '_' . $number . '_' . $extension));

        $this->em->persist($card);
        $this->em->flush();

        return $card;
    }

    /**
     * @return array{id:int, token:string, libraryId:int}
     */
    protected function registerUser(string $mail = 'enzo@example.test', string $password = 'Password123!'): array
    {
        $this->jsonRequest('POST', '/api/register', [
            'pseudo' => 'Enzo',
            'mail' => $mail,
            'password' => $password,
            'passwordConfirm' => $password,
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $this->jsonResponse();

        $user = $this->em->getRepository(User::class)->find($payload['id']);
        self::assertInstanceOf(User::class, $user);

        return [
            'id' => $payload['id'],
            'token' => $payload['apiToken'],
            'libraryId' => $user->getLibrary()->getId(),
        ];
    }

    protected function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    private function resetDatabase(): void
    {
        foreach ([LibraryCard::class, GameSet::class, Card::class, GameType::class, Library::class, User::class] as $class) {
            foreach ($this->em->getRepository($class)->findAll() as $entity) {
                $this->em->remove($entity);
            }
        }

        $this->em->flush();
        $this->em->clear();
    }
}
