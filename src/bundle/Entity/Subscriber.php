<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** A person who subscribed to the newsletter. Table `app_subscriber`, created by the migration of the bundle. */
#[ORM\Entity]
#[ORM\Table(name: 'app_subscriber')]
#[ORM\UniqueConstraint(name: 'UNIQ_SUBSCRIBER_EMAIL_LIST', columns: ['email', 'list_id'])]
#[ORM\HasLifecycleCallbacks]
class Subscriber
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $email;

    /** The list the person subscribed to: the sites that share a `newsletter.list_id` share their subscribers. */
    #[ORM\Column(name: 'list_id', type: Types::STRING, length: 64, options: ['default' => 'default'])]
    private string $listId = 'default';

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $consentedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $confirmationToken = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $confirmed = false;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->consentedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getListId(): string
    {
        return $this->listId;
    }

    public function setListId(string $listId): self
    {
        $this->listId = $listId;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getConsentedAt(): \DateTimeImmutable
    {
        return $this->consentedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getConfirmationToken(): ?string
    {
        return $this->confirmationToken;
    }

    public function setConfirmationToken(?string $confirmationToken): self
    {
        $this->confirmationToken = $confirmationToken;
        return $this;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    public function setConfirmed(bool $confirmed): self
    {
        $this->confirmed = $confirmed;
        return $this;
    }

    public function generateConfirmationToken(): string
    {
        $this->confirmationToken = bin2hex(random_bytes(32));
        return $this->confirmationToken;
    }
}
