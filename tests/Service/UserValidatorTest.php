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
}
