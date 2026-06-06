<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260604170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recrutamento: entrevistador e tipo (online/presencial) na entrevista do candidato';
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('rh_candidato', 'entrevista_tipo')) {
            $this->addSql("ALTER TABLE rh_candidato ADD entrevista_tipo VARCHAR(16) DEFAULT NULL");
        }
        if (!$this->columnExists('rh_candidato', 'entrevista_entrevistador_id')) {
            $this->addSql('ALTER TABLE rh_candidato ADD entrevista_entrevistador_id INT DEFAULT NULL');
            $this->addSql('ALTER TABLE rh_candidato ADD CONSTRAINT FK_RH_CAND_ENTREVISTADOR FOREIGN KEY (entrevista_entrevistador_id) REFERENCES `user` (id) ON DELETE SET NULL');
            $this->addSql('CREATE INDEX IDX_RH_CAND_ENTREVISTADOR ON rh_candidato (entrevista_entrevistador_id)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->columnExists('rh_candidato', 'entrevista_entrevistador_id')) {
            $this->addSql('ALTER TABLE rh_candidato DROP FOREIGN KEY FK_RH_CAND_ENTREVISTADOR');
            $this->addSql('DROP INDEX IDX_RH_CAND_ENTREVISTADOR ON rh_candidato');
            $this->addSql('ALTER TABLE rh_candidato DROP entrevista_entrevistador_id');
        }
        if ($this->columnExists('rh_candidato', 'entrevista_tipo')) {
            $this->addSql('ALTER TABLE rh_candidato DROP entrevista_tipo');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $sm = $this->connection->createSchemaManager();
        if (!$sm->tablesExist([$table])) {
            return false;
        }

        return isset($sm->listTableColumns($table)[$column]);
    }
}
