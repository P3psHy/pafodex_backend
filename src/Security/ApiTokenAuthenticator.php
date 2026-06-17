<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class ApiTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/api')
            && null !== $this->extractBearerToken($request);
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $token = $this->extractBearerToken($request);
        if (null === $token || strlen($token) < 32) {
            throw new BadCredentialsException('Invalid token.');
        }

        return new SelfValidatingPassport(new UserBadge($token, function (string $token): User {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['apiToken' => $token]);
            if (!$user instanceof User) {
                throw new BadCredentialsException('Invalid token.');
            }

            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return $this->unauthorized();
    }

    public function start(Request $request, ?AuthenticationException $authException = null): JsonResponse
    {
        return $this->unauthorized();
    }

    private function extractBearerToken(Request $request): ?string
    {
        $authorization = $request->headers->get('Authorization');
        if (!is_string($authorization) || !str_starts_with(strtolower($authorization), 'bearer ')) {
            return null;
        }

        $token = trim(substr($authorization, 7));

        return '' === $token ? null : $token;
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
    }
}
