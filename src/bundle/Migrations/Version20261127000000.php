<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The subscribers belong to a list (`newsletter.list_id`): the same address may subscribe to the lists of several sites. The
 * subscribers that exist are those of the list "default"; the address is unique in a list, no longer in the whole table.
 */
final class Version20261127000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Give app_subscriber a list_id and make (email, list_id) unique instead of email.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'This table is for MySQL / MariaDB.');

        $table = $this->connection->createSchemaManager()->introspectTable('app_subscriber');
        if (!$table->hasColumn('list_id')) {
            $this->addSql("ALTER TABLE app_subscriber ADD list_id VARCHAR(64) NOT NULL DEFAULT 'default'");
        }
        // Whatever the unique index on the address alone was called (the project's own migration may have named it).
        foreach ($table->getIndexes() as $index) {
            if ($index->isUnique() && !$index->isPrimary() && $index->getColumns() === ['email']) {
                $this->addSql(sprintf('DROP INDEX %s ON app_subscriber', $index->getName()));
            }
        }
        if (!$table->hasIndex('UNIQ_SUBSCRIBER_EMAIL_LIST')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_SUBSCRIBER_EMAIL_LIST ON app_subscriber (email, list_id)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'This table is for MySQL / MariaDB.');
        // The lists are merged back into one: an address that is in two of them cannot stay twice under a unique address, so
        // the oldest row of each address is kept (refusing is the other way: this one says what it drops).
        $this->addSql('DELETE s FROM app_subscriber s INNER JOIN app_subscriber o ON o.email = s.email AND o.id < s.id');
        $this->addSql('DROP INDEX UNIQ_SUBSCRIBER_EMAIL_LIST ON app_subscriber');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SUBSCRIBER_EMAIL ON app_subscriber (email)');
        $this->addSql('ALTER TABLE app_subscriber DROP list_id');
    }
}
