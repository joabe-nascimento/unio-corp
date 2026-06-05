<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hub TI — comunicados (ti_novidade)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ti_novidade (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            autor_id INT DEFAULT NULL,
            titulo VARCHAR(180) NOT NULL,
            resumo LONGTEXT NOT NULL,
            icon VARCHAR(32) NOT NULL,
            badge VARCHAR(32) DEFAULT NULL,
            variant VARCHAR(16) NOT NULL,
            publicado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_TI_NOV_EMPRESA (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ti_novidade ADD CONSTRAINT FK_TI_NOV_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ti_novidade ADD CONSTRAINT FK_TI_NOV_AUTOR FOREIGN KEY (autor_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ti_novidade DROP FOREIGN KEY FK_TI_NOV_EMPRESA');
        $this->addSql('ALTER TABLE ti_novidade DROP FOREIGN KEY FK_TI_NOV_AUTOR');
        $this->addSql('DROP TABLE ti_novidade');
    }
}
