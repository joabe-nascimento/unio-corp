<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260711160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CPF do paciente pós-operatório para validação da carteirinha digital';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pos_operatorio_paciente ADD cpf VARCHAR(11) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_POSOP_PAC_CPF ON pos_operatorio_paciente (empresa_id, cpf)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_POSOP_PAC_CPF ON pos_operatorio_paciente');
        $this->addSql('ALTER TABLE pos_operatorio_paciente DROP cpf');
    }
}
