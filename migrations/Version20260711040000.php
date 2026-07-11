<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260711040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Carteirinha digital vinculada ao paciente (foto e emissão)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pos_operatorio_paciente ADD foto_path VARCHAR(255) DEFAULT NULL, ADD carteirinha_plano VARCHAR(24) DEFAULT NULL, ADD carteirinha_verificacao VARCHAR(32) DEFAULT NULL, ADD carteirinha_emitida_em DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD carteirinha_valida_ate DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_POSOP_CART_VERIF ON pos_operatorio_paciente (empresa_id, carteirinha_verificacao)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_POSOP_CART_VERIF ON pos_operatorio_paciente');
        $this->addSql('ALTER TABLE pos_operatorio_paciente DROP foto_path, DROP carteirinha_plano, DROP carteirinha_verificacao, DROP carteirinha_emitida_em, DROP carteirinha_valida_ate');
    }
}
