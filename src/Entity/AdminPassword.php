<?php

namespace App\Entity;

use App\Repository\AdminPasswordRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\Validator\Constraints as SecurityAssert;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AdminPasswordRepository::class)]
#[ORM\Table(name: 'admin_password')]
class AdminPassword
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $password;

    #[Assert\NotBlank(message: 'Das Passwort darf nicht leer sein')]
    #[Assert\Length(
        min: 8,
        minMessage: 'Das Passwort muss mindestens {{ limit }} Zeichen enthalten'
    )]
    private $plainPassword;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }
    
    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }
    
    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;
        
        return $this;
    }
}