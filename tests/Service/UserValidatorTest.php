<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserValidator;
use PHPUnit\Framework\TestCase;

class InMemoryUserRepository extends UserRepository
{
    /** @var array<int,User> */
    private array $users;

    public function __construct(array $users)
    {
        $this->users = $users;
    }

    public function findMultipleByUsernames(array $usernames): array
    {
        return array_values(array_filter(
            $this->users,
            fn(User $u) => in_array($u->getUsername(), $usernames, true)
        ));
    }

    public function findOneByUsername(string $username): ?User
    {
        foreach ($this->users as $user) {
            if ($user->getUsername() === $username) {
                return $user;
            }
        }
        return null;
    }
}

class UserValidatorTest extends TestCase
{
    public function testIdentifyUnknownUsers(): void
    {
        $known = (new User())->setUsername('known1')->setEmail('k@example.com');
        $repo = new InMemoryUserRepository([$known]);
        $validator = new UserValidator($repo);

        $unknown = $validator->identifyUnknownUsers(['known1' => true, 'unknown1' => true]);

        $this->assertSame(['unknown1'], $unknown);
    }

    public function testIsKnownUser(): void
    {
        $known = (new User())->setUsername('known1')->setEmail('k@example.com');
        $repo = new InMemoryUserRepository([$known]);
        $validator = new UserValidator($repo);

        $this->assertTrue($validator->isKnownUser('known1'));
        $this->assertFalse($validator->isKnownUser('unknown1'));
    }

    public function testIdentifyUnknownUsersReturnsEmptyForEmptyInput(): void
    {
        $repo = new InMemoryUserRepository([]);
        $validator = new UserValidator($repo);

        $this->assertSame([], $validator->identifyUnknownUsers([]));
    }

    public function testIdentifyUnknownUsersWithMultipleKnownAndUnknown(): void
    {
        $a = (new User())->setUsername('a')->setEmail('a@example.com');
        $b = (new User())->setUsername('b')->setEmail('b@example.com');
        $repo = new InMemoryUserRepository([$a, $b]);
        $validator = new UserValidator($repo);

        $input = ['a' => true, 'b' => true, 'c' => true, 'd' => true];
        $unknown = $validator->identifyUnknownUsers($input);

        sort($unknown);
        $this->assertSame(['c', 'd'], $unknown);
    }

    public function testFilterKnownAndUnknownUsersSplitsAndIgnoresMissingUsername(): void
    {
        $k1 = (new User())->setUsername('k1')->setEmail('k1@example.com');
        $k2 = (new User())->setUsername('k2')->setEmail('k2@example.com');
        $repo = new InMemoryUserRepository([$k1, $k2]);
        $validator = new UserValidator($repo);

        $records = [
            ['username' => 'k1', 'val' => 1],
            ['nope' => 'x'],
            ['username' => 'u1', 'val' => 2],
            ['username' => 'k2', 'val' => 3],
        ];

        [$known, $unknown] = $validator->filterKnownAndUnknownUsers($records);

        $this->assertCount(2, $known);
        $this->assertCount(1, $unknown);
        $this->assertSame('k1', $known[0]['username']);
        $this->assertSame('k2', $known[1]['username']);
        $this->assertSame('u1', $unknown[0]['username']);
    }
}
