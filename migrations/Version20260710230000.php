<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260710230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Campos clínicos adicionais no cadastro pós-operatório do paciente';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pos_operatorio_paciente ADD email_contato VARCHAR(120) DEFAULT NULL, ADD contato_emergencia VARCHAR(120) DEFAULT NULL, ADD telefone_emergencia VARCHAR(40) DEFAULT NULL, ADD observacoes LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pos_operatorio_paciente DROP email_contato, DROP contato_emergencia, DROP telefone_emergencia, DROP observacoes');
    }
}
