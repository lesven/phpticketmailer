<?php

namespace App\Entity;

use App\Repository\EmailSentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=EmailSentRepository::class)
 * @ORM\Table(name="emails_sent")
 */
class EmailSent
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $ticketId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $username;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $subject;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $status;

    /**
     * @ORM\Column(type="datetime")
     */
    private $timestamp;

    /**
     * @ORM\Column(type="boolean")
     */
    private $testMode;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $ticketName;

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