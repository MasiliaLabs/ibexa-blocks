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

    public function byEmail(string $email, string $listId = 'default'): ?Subscriber
    {
        return $this->em->getRepository(Subscriber::class)->findOneBy(['email' => $email, 'listId' => $listId]);
    }

    public function byToken(string $token, string $listId = 'default'): ?Subscriber
    {
        return $this->em->getRepository(Subscriber::class)->findOneBy(['confirmationToken' => $token, 'listId' => $listId]);
    }

    public function count(?string $listId = null): int
    {
        return $this->em->getRepository(Subscriber::class)->count($listId === null ? [] : ['listId' => $listId]);
    }

    public function latest(int $limit, ?string $listId = null): array
    {
        return $this->em->getRepository(Subscriber::class)->findBy($listId === null ? [] : ['listId' => $listId], ['createdAt' => 'DESC', 'id' => 'DESC'], max(1, $limit));
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
