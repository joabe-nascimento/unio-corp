<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lembrete confirmação agenda (D-1) + hipótese/CID no atendimento (PEP leve)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic_agendamento ADD lembrete_confirmacao_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE clinic_atendimento ADD hipotese VARCHAR(500) DEFAULT NULL, ADD cid10 VARCHAR(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clinic_agendamento DROP lembrete_confirmacao_em');
        $this->addSql('ALTER TABLE clinic_atendimento DROP hipotese, DROP cid10');
    }
}
