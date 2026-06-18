<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\ApiTestCase;

final class AuthControllerTest extends ApiTestCase
{
    public function testRegisterCreatesUserLibraryAndToken(): void
    {
        $this->jsonRequest('POST', '/api/register', [
            'pseudo' => 'Enzo',
            'mail' => 'enzo@example.test',
            'password' => 'Password123!',
            'passwordConfirm' => 'Password123!',
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $this->jsonResponse();

        self::assertArrayHasKey('id', $payload);
        self::assertNotEmpty($payload['apiToken']);
        self::assertNotEmpty($payload['apiTokenExpiresAt']);

        $user = $this->em->getRepository(User::class)->find($payload['id']);
        self::assertInstanceOf(User::class, $user);
        self::assertSame('enzo@example.test', $user->getMail());
        self::assertNotSame('Password123!', $user->getPassword());
        self::assertNotNull($user->getLibrary()->getId());
    }

    public function testRegisterRejectsDuplicateEmailAndPasswordMismatch(): void
    {
        $this->registerUser();

        $this->jsonRequest('POST', '/api/register', [
            'pseudo' => 'Other',
            'mail' => 'enzo@example.test',
            'password' => 'Password123!',
            'passwordConfirm' => 'Password123!',
        ]);

        self::assertResponseStatusCodeSame(409);
        self::assertSame(['error' => 'Email already used'], $this->jsonResponse());

        $this->jsonRequest('POST', '/api/register', [
            'pseudo' => 'Other',
            'mail' => 'other@example.test',
            'password' => 'Password123!',
            'passwordConfirm' => 'Different123!',
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'Passwords do not match'], $this->jsonResponse());
    }

    public function testLoginMeUpdateAndLogoutFlow(): void
    {
        $this->registerUser('enzo@example.test', 'Password123!');

        $this->jsonRequest('POST', '/api/login', [
            'mail' => 'enzo@example.test',
            'password' => 'Password123!',
        ]);

        self::assertResponseIsSuccessful();
        $login = $this->jsonResponse();
        $token = $login['apiToken'];

        $this->jsonRequest('GET', '/api/me', null, $this->bearer($token));
        self::assertResponseIsSuccessful();
        self::assertSame('enzo@example.test', $this->jsonResponse()['mail']);

        $this->jsonRequest('PUT', '/api/me', [
            'pseudo' => 'NewPseudo',
            'mail' => 'new@example.test',
        ], $this->bearer($token));

        self::assertResponseIsSuccessful();
        $updated = $this->jsonResponse();
        self::assertSame('NewPseudo', $updated['pseudo']);
        self::assertSame('new@example.test', $updated['mail']);

        $this->jsonRequest('POST', '/api/logout', null, $this->bearer($token));
        self::assertResponseIsSuccessful();
        self::assertSame(['success' => true], $this->jsonResponse());

        $this->jsonRequest('GET', '/api/me', null, $this->bearer($token));
        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Invalid token'], $this->jsonResponse());
    }

    public function testLoginRejectsInvalidCredentials(): void
    {
        $this->registerUser('enzo@example.test', 'Password123!');

        $this->jsonRequest('POST', '/api/login', [
            'mail' => 'enzo@example.test',
            'password' => 'bad-password',
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Invalid credentials'], $this->jsonResponse());
    }
}
