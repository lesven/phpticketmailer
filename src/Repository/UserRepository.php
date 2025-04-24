<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByUsername(string $username): ?User
    {
        return $this->findOneBy(['username' => $username]);
    }
    
    public function findMultipleByUsernames(array $usernames): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.username IN (:usernames)')
            ->setParameter('usernames', $usernames)
            ->getQuery()
            ->getResult();
    }
    
    /**
     * Search users by username (case-insensitive partial match)
     * with optional sorting
     * 
     * @param string|null $searchTerm Search term for username
     * @param string|null $sortField Field to sort by (id, username, email)
     * @param string|null $sortDirection Direction to sort (ASC or DESC)
     * @return User[]
     */
    public function searchByUsername(?string $searchTerm, ?string $sortField = null, ?string $sortDirection = null): array
    {
        // Validate and set default sort parameters
        $validSortFields = ['id', 'username', 'email'];
        $sortField = in_array($sortField, $validSortFields) ? $sortField : 'id';
        $sortDirection = ($sortDirection === 'DESC') ? 'DESC' : 'ASC';
        
        $queryBuilder = $this->createQueryBuilder('u');
        
        // Apply search filter if a search term is provided
        if ($searchTerm) {
            $queryBuilder
                ->where('LOWER(u.username) LIKE LOWER(:searchTerm)')
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }
        
        // Apply sorting
        $queryBuilder->orderBy('u.' . $sortField, $sortDirection);
        
        return $queryBuilder->getQuery()->getResult();
    }
}