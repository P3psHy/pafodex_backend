<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\Library;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class AuthController extends AbstractController
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $hasher;

    public function __construct(EntityManagerInterface $em, UserPasswordHasherInterface $hasher)
    {
        $this->em = $em;
        $this->hasher = $hasher;
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $pseudo = $data['pseudo'] ?? null;
        $mail = $data['mail'] ?? null;
        $password = $data['password'] ?? null;
        $passwordConfirm = $data['passwordConfirm'] ?? null;

        if (!$pseudo || !$mail || !$password || !$passwordConfirm) {
            return $this->json(['error' => 'Missing fields'], Response::HTTP_BAD_REQUEST);
        }

        if ($password !== $passwordConfirm) {
            return $this->json(['error' => 'Passwords do not match'], Response::HTTP_BAD_REQUEST);
        }

        $existing = $this->em->getRepository(User::class)->findOneBy(['mail' => $mail]);
        if ($existing) {
            return $this->json(['error' => 'Email already used'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setPseudo($pseudo);
        $user->setMail($mail);
        $user->setRoles(['ROLE_USER']);
        $hashed = $this->hasher->hashPassword($user, $password);
        $user->setPassword($hashed);
        // generate simple API token
        $user->setApiToken(bin2hex(random_bytes(32)));

        // create and link a Library for this user
        $library = new Library();
        $library->setUser($user);
        $user->setLibrary($library);

        $this->em->persist($user);
        $this->em->flush();

        return $this->json(['id' => $user->getId(), 'apiToken' => $user->getApiToken()], Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $mail = $data['mail'] ?? null;
        $password = $data['password'] ?? null;

        if (!$mail || !$password) {
            return $this->json(['error' => 'Missing fields'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['mail' => $mail]);
        if (!$user) {
            return $this->json(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->hasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        // rotate token
        $user->setApiToken(bin2hex(random_bytes(32)));
        $this->em->flush();

        return $this->json(['id' => $user->getId(), 'apiToken' => $user->getApiToken()]);
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

    private function getAuthenticatedUser(Request $request): User|JsonResponse
    {
        $token = $this->extractBearerToken($request) ?? $request->query->get('apiToken');
        if (!$token) {
            return $this->json(['error' => 'No token provided'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['apiToken' => $token]);
        if (!$user) {
            return $this->json(['error' => 'Invalid token'], Response::HTTP_UNAUTHORIZED);
        }

        return $user;
    }

    private function userToArray(User $user): array
    {
        return [
            'id' => $user->getId(),
            'pseudo' => $user->getPseudo(),
            'mail' => $user->getMail(),
        ];
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $token = $this->extractBearerToken($request) ?? $request->get('apiToken');
        if (!$token) {
            return $this->json(['error' => 'No token provided'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['apiToken' => $token]);
        if (!$user) {
            return $this->json(['error' => 'Invalid token'], Response::HTTP_UNAUTHORIZED);
        }

        $user->setApiToken(null);
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return $this->json($this->userToArray($user));
    }

    #[Route('/me', name: 'api_me_update', methods: ['PUT'])]
    public function updateMe(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('pseudo', $data)) {
            if (!$data['pseudo']) {
                return $this->json(['error' => 'Pseudo cannot be empty'], Response::HTTP_BAD_REQUEST);
            }
            $user->setPseudo($data['pseudo']);
        }

        if (array_key_exists('mail', $data)) {
            if (!$data['mail']) {
                return $this->json(['error' => 'Mail cannot be empty'], Response::HTTP_BAD_REQUEST);
            }

            $existing = $this->em->getRepository(User::class)->findOneBy(['mail' => $data['mail']]);
            if ($existing && $existing->getId() !== $user->getId()) {
                return $this->json(['error' => 'Email already used'], Response::HTTP_CONFLICT);
            }

            $user->setMail($data['mail']);
        }

        if (array_key_exists('password', $data) || array_key_exists('passwordConfirm', $data)) {
            $password = $data['password'] ?? null;
            $passwordConfirm = $data['passwordConfirm'] ?? null;

            if (!$password || !$passwordConfirm) {
                return $this->json(['error' => 'Missing fields: password and passwordConfirm required'], Response::HTTP_BAD_REQUEST);
            }

            if ($password !== $passwordConfirm) {
                return $this->json(['error' => 'Passwords do not match'], Response::HTTP_BAD_REQUEST);
            }

            $user->setPassword($this->hasher->hashPassword($user, $password));
        }

        $this->em->flush();

        return $this->json($this->userToArray($user));
    }

    #[Route('/me', name: 'api_me_delete', methods: ['DELETE'])]
    public function deleteMe(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->json(['message' => 'User deleted successfully']);
    }
}
