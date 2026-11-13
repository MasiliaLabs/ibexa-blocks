<?php

declare(strict_types=1);

namespace Masilia\Bundle\IbexaBlocks\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The table of the newsletter subscribers. A project that had it before the bundle (it was created by the project's own
 * migration) keeps its table and its subscribers: nothing is done when it exists.
 */
final class Version20261115000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create app_subscriber (newsletter subscribers) when it does not exist yet.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'This table is created for MySQL / MariaDB.');

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS app_subscriber (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) DEFAULT NULL,
                email VARCHAR(255) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                consented_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                confirmation_token VARCHAR(64) DEFAULT NULL,
                confirmed TINYINT(1) NOT NULL DEFAULT 0,
                UNIQUE INDEX UNIQ_SUBSCRIBER_EMAIL (email),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        // The subscribers are the project's data: a rollback of the bundle does not drop them.
    }
}
