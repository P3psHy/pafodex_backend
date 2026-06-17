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
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $pseudo = $this->cleanString($data['pseudo'] ?? null);
        $mail = $this->normalizeEmail($data['mail'] ?? null);
        $password = $data['password'] ?? null;
        $passwordConfirm = $data['passwordConfirm'] ?? null;

        if (!$pseudo || !$mail || !$password || !$passwordConfirm) {
            return $this->json(['error' => 'Missing fields'], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($pseudo) > 50) {
            return $this->json(['error' => 'Pseudo must be 50 characters or less'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->isStrongEnoughPassword($password)) {
            return $this->json(['error' => 'Password must be at least 8 characters and contain letters and numbers'], Response::HTTP_BAD_REQUEST);
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
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $mail = $this->normalizeEmail($data['mail'] ?? null);
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

    private function getAuthenticatedUser(): User|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
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
    public function logout(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $user->setApiToken(null);
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return $this->json($this->userToArray($user));
    }

    #[Route('/me', name: 'api_me_update', methods: ['PUT'])]
    public function updateMe(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        if (array_key_exists('pseudo', $data)) {
            $pseudo = $this->cleanString($data['pseudo']);
            if (!$pseudo) {
                return $this->json(['error' => 'Pseudo cannot be empty'], Response::HTTP_BAD_REQUEST);
            }
            if (strlen($pseudo) > 50) {
                return $this->json(['error' => 'Pseudo must be 50 characters or less'], Response::HTTP_BAD_REQUEST);
            }
            $user->setPseudo($pseudo);
        }

        if (array_key_exists('mail', $data)) {
            $mail = $this->normalizeEmail($data['mail']);
            if (!$mail) {
                return $this->json(['error' => 'Mail cannot be empty'], Response::HTTP_BAD_REQUEST);
            }

            if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                return $this->json(['error' => 'Invalid email'], Response::HTTP_BAD_REQUEST);
            }

            $existing = $this->em->getRepository(User::class)->findOneBy(['mail' => $mail]);
            if ($existing && $existing->getId() !== $user->getId()) {
                return $this->json(['error' => 'Email already used'], Response::HTTP_CONFLICT);
            }

            $user->setMail($mail);
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

            if (!$this->isStrongEnoughPassword($password)) {
                return $this->json(['error' => 'Password must be at least 8 characters and contain letters and numbers'], Response::HTTP_BAD_REQUEST);
            }

            $user->setPassword($this->hasher->hashPassword($user, $password));
        }

        $this->em->flush();

        return $this->json($this->userToArray($user));
    }

    #[Route('/me', name: 'api_me_delete', methods: ['DELETE'])]
    public function deleteMe(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->json(['message' => 'User deleted successfully']);
    }

    private function cleanString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $value = $this->cleanString($value);

        return null === $value ? null : strtolower($value);
    }

    private function isStrongEnoughPassword(mixed $password): bool
    {
        return is_string($password)
            && strlen($password) >= 8
            && (bool) preg_match('/[A-Za-z]/', $password)
            && (bool) preg_match('/\d/', $password);
    }
}
