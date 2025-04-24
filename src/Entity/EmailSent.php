<?php

namespace App\Entity;

use App\Repository\EmailSentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmailSentRepository::class)]
#[ORM\Table(name: 'emails_sent')]
class EmailSent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $ticketId = null;

    #[ORM\Column(length: 255)]
    private ?string $username = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $subject = null;

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $timestamp = null;

    #[ORM\Column]
    private ?bool $testMode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ticketName = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTicketId(): ?string
    {
        return $this->ticketId;
    }

    public function setTicketId(string $ticketId): self
    {
        $this->ticketId = $ticketId;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getTimestamp(): ?\DateTimeInterface
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeInterface $timestamp): self
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Format timestamp as string when needed
     */
    public function getFormattedTimestamp(): string
    {
        return $this->timestamp ? $this->timestamp->format('Y-m-d H:i:s') : '';
    }

    public function getTestMode(): ?bool
    {
        return $this->testMode;
    }

    public function setTestMode(bool $testMode): self
    {
        $this->testMode = $testMode;

        return $this;
    }

    public function getTicketName(): ?string
    {
        return $this->ticketName;
    }

    public function setTicketName(?string $ticketName): self
    {
        $this->ticketName = $ticketName;

        return $this;
    }
}