<?php

namespace App\Command;

use App\Entity\Card;
use App\Entity\GameType;
use App\Entity\Library;
use App\Entity\LibraryCard;
use App\Entity\Set as GameSet;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed',
    description: 'Reload deterministic data for local development and API tests.'
)]
class SeedDatabaseCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'append',
            null,
            InputOption::VALUE_NONE,
            'Keep existing data and only add missing seed data.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $append = (bool) $input->getOption('append');

        $gameTypes = $this->seedGameTypes();
        $cards = $this->seedCards($gameTypes);
        $users = $this->seedUsers();
        $sets = $this->seedSets($users, $gameTypes, $cards);
        $libraryCards = $this->seedLibraryCards($users, $cards);

        $this->entityManager->flush();

        $io->success(sprintf(
            'Seed complete: %d users, %d libraries, %d game types, %d cards, %d sets, %d library cards.',
            count($users),
            count($users),
            count($gameTypes),
            count($cards),
            count($sets),
            count($libraryCards),
        ));

        $io->section('Test accounts');
        $io->table(
            ['Pseudo', 'Email', 'Password', 'API token'],
            array_map(
                static fn (User $user): array => [
                    $user->getPseudo(),
                    $user->getMail(),
                    'Password123!',
                    $user->getApiToken() ?? '',
                ],
                $users
            )
        );

        return Command::SUCCESS;
    }

    /**
     * @return array<string, GameType>
     */
    private function seedGameTypes(): array
    {
        $items = [
            'pokemon' => [
                'name' => 'Pokemon',
                'abbreviated' => 'PKM',
                'url' => 'https://www.pokemon.com/fr/jcc-pokemon',
            ],
            'magic' => [
                'name' => 'Magic: The Gathering',
                'abbreviated' => 'MTG',
                'url' => 'https://magic.wizards.com',
            ],
            'yugioh' => [
                'name' => 'Yu-Gi-Oh!',
                'abbreviated' => 'YGO',
                'url' => 'https://www.yugioh-card.com',
            ],
        ];

        $gameTypes = [];
        foreach ($items as $key => $data) {
            $gameType = $this->findOrCreate(GameType::class, ['name' => $data['name']]);
            $gameType
                ->setName($data['name'])
                ->setAbbreviated($data['abbreviated'])
                ->setUrl($data['url']);

            $this->entityManager->persist($gameType);
            $gameTypes[$key] = $gameType;
        }

        return $gameTypes;
    }

    /**
     * @param array<string, GameType> $gameTypes
     *
     * @return array<string, Card>
     */
    private function seedCards(array $gameTypes): array
    {
        $items = [
            'pikachu' => ['Pikachu', 'Base Set', '058/102', 'pokemon', 'https://images.pafodex.test/pokemon/pikachu.png'],
            'charizard' => ['Dracaufeu', 'Base Set', '004/102', 'pokemon', 'https://images.pafodex.test/pokemon/dracaufeu.png'],
            'mewtwo' => ['Mewtwo', 'Promo', '012/053', 'pokemon', 'https://images.pafodex.test/pokemon/mewtwo.png'],
            'black_lotus' => ['Black Lotus', 'Limited Edition Alpha', '233', 'magic', 'https://images.pafodex.test/mtg/black-lotus.png'],
            'lightning_bolt' => ['Lightning Bolt', 'Core Set 2010', '146', 'magic', 'https://images.pafodex.test/mtg/lightning-bolt.png'],
            'sol_ring' => ['Sol Ring', 'Commander', '331', 'magic', 'https://images.pafodex.test/mtg/sol-ring.png'],
            'dark_magician' => ['Dark Magician', 'Legend of Blue Eyes White Dragon', 'LOB-005', 'yugioh', 'https://images.pafodex.test/ygo/dark-magician.png'],
            'blue_eyes' => ['Blue-Eyes White Dragon', 'Legend of Blue Eyes White Dragon', 'LOB-001', 'yugioh', 'https://images.pafodex.test/ygo/blue-eyes.png'],
            'kuriboh' => ['Kuriboh', 'Metal Raiders', 'MRD-071', 'yugioh', 'https://images.pafodex.test/ygo/kuriboh.png'],
        ];

        $cards = [];
        foreach ($items as $key => [$name, $extension, $number, $gameTypeKey, $image]) {
            $uuid = strtolower($name . '_' . $number . '_' . $extension);
            $card = $this->findOrCreate(Card::class, ['uuid' => $uuid]);
            $card
                ->setName($name)
                ->setExtension($extension)
                ->setNumber($number)
                ->setImage($image)
                ->setGameType($gameTypes[$gameTypeKey])
                ->setUuid($uuid);

            $this->entityManager->persist($card);
            $cards[$key] = $card;
        }

        return $cards;
    }

    /**
     * @return array<string, User>
     */
    private function seedUsers(): array
    {
        $items = [
            'enzo' => ['Enzo', 'enzo@test.local', ['ROLE_USER'], 'seed-token-enzo', '+7 days'],
            'admin' => ['Admin', 'admin@test.local', ['ROLE_ADMIN'], 'seed-token-admin', '+7 days'],
            'expired' => ['Expired', 'expired@test.local', ['ROLE_USER'], 'seed-token-expired', '-1 hour'],
        ];

        $users = [];
        foreach ($items as $key => [$pseudo, $mail, $roles, $apiToken, $tokenTtl]) {
            $user = $this->findOrCreate(User::class, ['mail' => $mail]);
            $user
                ->setPseudo($pseudo)
                ->setMail($mail)
                ->setRoles($roles)
                ->setPassword($this->passwordHasher->hashPassword($user, 'Password123!'))
                ->setApiToken($apiToken)
                ->setApiTokenExpiresAt(new \DateTimeImmutable($tokenTtl));

            $library = $user->getId() ? $user->getLibrary() : new Library();
            $library->setUser($user);
            $user->setLibrary($library);

            $this->entityManager->persist($user);
            $users[$key] = $user;
        }

        return $users;
    }

    /**
     * @param array<string, User> $users
     * @param array<string, GameType> $gameTypes
     * @param array<string, Card> $cards
     *
     * @return list<GameSet>
     */
    private function seedSets(array $users, array $gameTypes, array $cards): array
    {
        $items = [
            ['user' => 'enzo', 'name' => 'Deck Pokemon favoris', 'color' => '#FFCB05', 'gameType' => 'pokemon', 'cards' => ['pikachu', 'charizard', 'mewtwo']],
            ['user' => 'enzo', 'name' => 'Deck Commander rouge', 'color' => '#C0392B', 'gameType' => 'magic', 'cards' => ['lightning_bolt', 'sol_ring']],
            ['user' => 'admin', 'name' => 'Collection Yu-Gi-Oh!', 'color' => '#2E86DE', 'gameType' => 'yugioh', 'cards' => ['dark_magician', 'blue_eyes', 'kuriboh']],
            ['user' => 'admin', 'name' => 'Cartes rares MTG', 'color' => '#8E44AD', 'gameType' => 'magic', 'cards' => ['black_lotus', 'sol_ring']],
            ['user' => 'expired', 'name' => 'Archive Pokemon', 'color' => '#7F8C8D', 'gameType' => 'pokemon', 'cards' => ['pikachu']],
        ];

        $sets = [];
        foreach ($items as $item) {
            $set = $this->findOrCreate(GameSet::class, [
                'name' => $item['name'],
                'library' => $users[$item['user']]->getLibrary(),
            ]);
            $set
                ->setName($item['name'])
                ->setColor($item['color'])
                ->setLibrary($users[$item['user']]->getLibrary())
                ->setGameType($gameTypes[$item['gameType']]);

            foreach ($item['cards'] as $cardKey) {
                $set->addCard($cards[$cardKey]);
            }

            $this->entityManager->persist($set);
            $sets[] = $set;
        }

        return $sets;
    }

    /**
     * @param array<string, User> $users
     * @param array<string, Card> $cards
     *
     * @return list<LibraryCard>
     */
    private function seedLibraryCards(array $users, array $cards): array
    {
        $items = [
            ['user' => 'enzo', 'card' => 'pikachu', 'count' => 4, 'favorite' => true],
            ['user' => 'enzo', 'card' => 'charizard', 'count' => 1, 'favorite' => true],
            ['user' => 'enzo', 'card' => 'mewtwo', 'count' => 2, 'favorite' => false],
            ['user' => 'enzo', 'card' => 'lightning_bolt', 'count' => 4, 'favorite' => false],
            ['user' => 'enzo', 'card' => 'sol_ring', 'count' => 1, 'favorite' => true],
            ['user' => 'admin', 'card' => 'black_lotus', 'count' => 1, 'favorite' => true],
            ['user' => 'admin', 'card' => 'dark_magician', 'count' => 3, 'favorite' => true],
            ['user' => 'admin', 'card' => 'blue_eyes', 'count' => 2, 'favorite' => true],
            ['user' => 'admin', 'card' => 'kuriboh', 'count' => 5, 'favorite' => false],
            ['user' => 'expired', 'card' => 'pikachu', 'count' => 1, 'favorite' => false],
        ];

        $libraryCards = [];
        foreach ($items as $item) {
            $library = $users[$item['user']]->getLibrary();
            $card = $cards[$item['card']];
            $libraryCard = $this->findOrCreate(LibraryCard::class, [
                'library' => $library,
                'card' => $card,
            ]);

            $libraryCard
                ->setLibrary($library)
                ->setCard($card)
                ->setNumberCard($item['count'])
                ->setIsFavorite($item['favorite']);

            $this->entityManager->persist($libraryCard);
            $libraryCards[] = $libraryCard;
        }

        return $libraryCards;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $className
     * @param array<string, mixed> $criteria
     *
     * @return T
     */
    private function findOrCreate(string $className, array $criteria): object
    {
        $entity = $this->entityManager->getRepository($className)->findOneBy($criteria);

        return $entity ?? new $className();
    }
}
