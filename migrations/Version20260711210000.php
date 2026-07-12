<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260711210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Comprovante de procedimento — códigos de verificação públicos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pos_operatorio_paciente ADD comprovante_verificacao VARCHAR(8) DEFAULT NULL, ADD comprovante_emitida_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD comprovante_valido_ate DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_POSOP_COMPROVANTE_VERIF ON pos_operatorio_paciente (comprovante_verificacao)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_POSOP_COMPROVANTE_VERIF ON pos_operatorio_paciente');
        $this->addSql('ALTER TABLE pos_operatorio_paciente DROP comprovante_verificacao, DROP comprovante_emitida_em, DROP comprovante_valido_ate');
    }
}
