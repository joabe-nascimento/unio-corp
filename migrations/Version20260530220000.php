<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hub TI — anexos de chamados';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ti_chamado_anexo (
            id INT AUTO_INCREMENT NOT NULL,
            chamado_id INT NOT NULL,
            empresa_id INT NOT NULL,
            enviado_por_id INT DEFAULT NULL,
            nome_original VARCHAR(255) NOT NULL,
            caminho VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            tamanho INT NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_TI_ANEXO_CHAMADO (chamado_id),
            INDEX IDX_TI_ANEXO_EMPRESA (empresa_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ti_chamado_anexo ADD CONSTRAINT FK_TI_ANEXO_CHAMADO FOREIGN KEY (chamado_id) REFERENCES ti_chamado (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ti_chamado_anexo ADD CONSTRAINT FK_TI_ANEXO_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ti_chamado_anexo ADD CONSTRAINT FK_TI_ANEXO_USER FOREIGN KEY (enviado_por_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ti_chamado_anexo DROP FOREIGN KEY FK_TI_ANEXO_CHAMADO');
        $this->addSql('ALTER TABLE ti_chamado_anexo DROP FOREIGN KEY FK_TI_ANEXO_EMPRESA');
        $this->addSql('ALTER TABLE ti_chamado_anexo DROP FOREIGN KEY FK_TI_ANEXO_USER');
        $this->addSql('DROP TABLE ti_chamado_anexo');
    }
}
