<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Funcionário: CPF, documentos, endereço, bancários e contrato';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('funcionario')) {
            return;
        }

        $this->addSql('ALTER TABLE funcionario ADD cpf VARCHAR(11) DEFAULT NULL');
        $this->addSql('ALTER TABLE funcionario ADD rg VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE funcionario ADD data_nascimento DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\'');
        $this->addSql('ALTER TABLE funcionario ADD tipo_contrato VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE funcionario ADD cep VARCHAR(8) DEFAULT NULL');
        $this->addSql('ALTER TABLE funcionario ADD logradouro VARCHAR(150) DEFAULT NULL');
        $this->addSql('ALTER TABLE funcionario ADD numero VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE funcionario ADD complemento VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE funcionario ADD bairro VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE funcionario ADD cidade VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE funcionario ADD uf VARCHAR(2) DEFAULT NULL');
        $this->addSql('ALTER TABLE funcionario ADD banco VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE funcionario ADD agencia VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE funcionario ADD conta VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE funcionario ADD pix VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE funcionario ADD observacoes LONGTEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FUNCIONARIO_EMPRESA_CPF ON funcionario (empresa_id, cpf)');
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('funcionario')) {
            return;
        }

        $this->addSql('DROP INDEX UNIQ_FUNCIONARIO_EMPRESA_CPF ON funcionario');
        $this->addSql('ALTER TABLE funcionario DROP cpf, DROP rg, DROP data_nascimento, DROP tipo_contrato, DROP cep, DROP logradouro, DROP numero, DROP complemento, DROP bairro, DROP cidade, DROP uf, DROP banco, DROP agencia, DROP conta, DROP pix, DROP observacoes');
    }
}
