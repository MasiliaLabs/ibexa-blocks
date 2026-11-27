<?php

declare(strict_types=1);

namespace Masilia\IbexaBlocks\Tests\bundle\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Masilia\Bundle\IbexaBlocks\Migrations\Version20261127000000;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SubscriberListMigrationTest extends TestCase
{
    /** @param list<Index> $indexes */
    private function migration(bool $hasList, array $indexes): Version20261127000000
    {
        $columns = [new Column('email', \Doctrine\DBAL\Types\Type::getType(Types::STRING))];
        if ($hasList) {
            $columns[] = new Column('list_id', \Doctrine\DBAL\Types\Type::getType(Types::STRING));
        }
        $manager = $this->createStub(AbstractSchemaManager::class);
        $manager->method('introspectTable')->willReturn(new Table('app_subscriber', $columns, $indexes));
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('createSchemaManager')->willReturn($manager);

        return new Version20261127000000($connection, new NullLogger());
    }

    /** @return list<string> */
    private static function sql(Version20261127000000 $migration): array
    {
        return array_map(static fn ($query): string => $query->getStatement(), $migration->getSql());
    }

    public function testUpAddsTheListDropsTheUniqueAddressWhateverItsNameAndMakesTheAddressUniqueInAList(): void
    {
        $migration = $this->migration(false, [new Index('UNIQ_FB1BF015E7927C74', ['email'], true), new Index('PRIMARY', ['id'], true, true)]);
        $migration->up(new Schema());

        self::assertSame([
            "ALTER TABLE app_subscriber ADD list_id VARCHAR(64) NOT NULL DEFAULT 'default'",
            'DROP INDEX UNIQ_FB1BF015E7927C74 ON app_subscriber',
            'CREATE UNIQUE INDEX UNIQ_SUBSCRIBER_EMAIL_LIST ON app_subscriber (email, list_id)',
        ], self::sql($migration));
    }

    public function testUpDoesNothingToATableThatAlreadyHasIt(): void
    {
        $migration = $this->migration(true, [new Index('UNIQ_SUBSCRIBER_EMAIL_LIST', ['email', 'list_id'], true)]);
        $migration->up(new Schema());

        self::assertSame([], self::sql($migration));
    }

    public function testDownMergesTheListsBackKeepingTheOldestRowOfEachAddress(): void
    {
        $migration = $this->migration(true, []);
        $migration->down(new Schema());

        $sql = self::sql($migration);
        self::assertStringStartsWith('DELETE s FROM app_subscriber s INNER JOIN app_subscriber o ON o.email = s.email AND o.id < s.id', $sql[0]);
        self::assertSame(['DROP INDEX UNIQ_SUBSCRIBER_EMAIL_LIST ON app_subscriber', 'CREATE UNIQUE INDEX UNIQ_SUBSCRIBER_EMAIL ON app_subscriber (email)', 'ALTER TABLE app_subscriber DROP list_id'], array_slice($sql, 1));
    }
}
