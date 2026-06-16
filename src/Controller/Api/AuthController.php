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
}
