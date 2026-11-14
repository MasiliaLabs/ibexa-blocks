<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Newsletter;

use Doctrine\ORM\EntityManagerInterface;
use Masilia\Bundle\IbexaBlocks\Entity\Subscriber;

final readonly class DoctrineSubscriberStore implements SubscriberStore
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function byEmail(string $email): ?Subscriber
    {
        return $this->em->getRepository(Subscriber::class)->findOneBy(['email' => $email]);
    }

    public function byToken(string $token): ?Subscriber
    {
        return $this->em->getRepository(Subscriber::class)->findOneBy(['confirmationToken' => $token]);
    }

    public function count(): int
    {
        return $this->em->getRepository(Subscriber::class)->count([]);
    }

    public function latest(int $limit): array
    {
        return array_values($this->em->getRepository(Subscriber::class)->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC'], max(1, $limit)));
    }

    public function add(Subscriber $subscriber): void
    {
        $this->em->persist($subscriber);
        $this->em->flush();
    }

    public function save(): void
    {
        $this->em->flush();
    }
}
