<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tabela password_reset_token para recuperação de senha';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE password_reset_token (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            usado_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_PASSWORD_RESET_USER (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE password_reset_token ADD CONSTRAINT FK_PASSWORD_RESET_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_reset_token DROP FOREIGN KEY FK_PASSWORD_RESET_USER');
        $this->addSql('DROP TABLE password_reset_token');
    }
}
