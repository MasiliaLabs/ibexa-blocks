<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Newsletter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Masilia\Bundle\IbexaBlocks\Entity\Subscriber;
use Masilia\Bundle\IbexaBlocks\Newsletter\DoctrineSubscriberStore;
use PHPUnit\Framework\TestCase;

final class DoctrineSubscriberStoreTest extends TestCase
{
    public function testItAsksTheRepositoryOfSubscribers(): void
    {
        $found = (new Subscriber())->setEmail('a@b.test');
        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['email' => 'a@b.test', 'listId' => 'default'])->willReturn($found);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        self::assertSame($found, (new DoctrineSubscriberStore($em))->byEmail('a@b.test'));
    }

    public function testATokenAndTheLatestAreSearchedByTheirOwnFields(): void
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['confirmationToken' => 'tok', 'listId' => 'default'])->willReturn(null);
        $repository->expects(self::once())->method('findBy')->with([], ['createdAt' => 'DESC', 'id' => 'DESC'], 1)->willReturn([]);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $store = new DoctrineSubscriberStore($em);

        self::assertNull($store->byToken('tok'));
        self::assertSame([], $store->latest(0), 'a limit under 1 asks for one');
    }

    public function testAddingPersistsAndFlushesAndSavingOnlyFlushes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $subscriber = (new Subscriber())->setEmail('a@b.test');
        $em->expects(self::once())->method('persist')->with($subscriber);
        $em->expects(self::exactly(2))->method('flush');
        $store = new DoctrineSubscriberStore($em);

        $store->add($subscriber);
        $store->save();
    }
}
