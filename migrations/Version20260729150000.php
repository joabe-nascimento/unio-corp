<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unio Jurídico: tabela de metas (receita/taxa de êxito) por escritório, área ou advogado';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE juridico_meta (
            id INT AUTO_INCREMENT NOT NULL,
            tipo VARCHAR(20) NOT NULL,
            area VARCHAR(80) DEFAULT NULL,
            periodo VARCHAR(7) NOT NULL,
            valor_meta NUMERIC(12, 2) NOT NULL,
            criado_em DATETIME NOT NULL,
            empresa_id INT NOT NULL,
            responsavel_id INT DEFAULT NULL,
            criado_por_id INT DEFAULT NULL,
            INDEX IDX_JUR_META_EMPRESA (empresa_id),
            INDEX IDX_JUR_META_RESPONSAVEL (responsavel_id),
            INDEX IDX_JUR_META_CRIADO_POR (criado_por_id),
            INDEX IDX_JUR_META_EMPRESA_PERIODO (empresa_id, periodo),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE juridico_meta ADD CONSTRAINT FK_JUR_META_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_meta ADD CONSTRAINT FK_JUR_META_RESPONSAVEL FOREIGN KEY (responsavel_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_meta ADD CONSTRAINT FK_JUR_META_CRIADO_POR FOREIGN KEY (criado_por_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE juridico_meta DROP FOREIGN KEY FK_JUR_META_EMPRESA');
        $this->addSql('ALTER TABLE juridico_meta DROP FOREIGN KEY FK_JUR_META_RESPONSAVEL');
        $this->addSql('ALTER TABLE juridico_meta DROP FOREIGN KEY FK_JUR_META_CRIADO_POR');
        $this->addSql('DROP TABLE juridico_meta');
    }
}
