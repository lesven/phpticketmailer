<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class SMTPConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Der SMTP-Host darf nicht leer sein')]
    private ?string $host = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Der SMTP-Port darf nicht leer sein')]
    #[Assert\Positive(message: 'Der Port muss eine positive Zahl sein')]
    private ?int $port = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column]
    private bool $useTLS = false;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Die Absender-E-Mail darf nicht leer sein')]
    #[Assert\Email(message: 'Die E-Mail-Adresse {{ value }} ist ungültig')]
    private ?string $senderEmail = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Der Absendername darf nicht leer sein')]
    private ?string $senderName = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function setHost(string $host): self
    {
        $this->host = $host;

        return $this;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function setPort(int $port): self
    {
        $this->port = $port;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function isUseTLS(): bool
    {
        return $this->useTLS;
    }

    public function setUseTLS(bool $useTLS): self
    {
        $this->useTLS = $useTLS;

        return $this;
    }

    public function getSenderEmail(): ?string
    {
        return $this->senderEmail;
    }

    public function setSenderEmail(string $senderEmail): self
    {
        $this->senderEmail = $senderEmail;

        return $this;
    }

    public function getSenderName(): ?string
    {
        return $this->senderName;
    }

    public function setSenderName(string $senderName): self
    {
        $this->senderName = $senderName;

        return $this;
    }

    public function getDSN(): string
    {
        $dsn = 'smtp://';
        
        if ($this->username && $this->password) {
            $dsn .= urlencode($this->username) . ':' . urlencode($this->password) . '@';
        }
        
        $dsn .= $this->host . ':' . $this->port;
        
        if ($this->useTLS) {
            $dsn .= '?encryption=tls';
        }
        
        return $dsn;
    }
}