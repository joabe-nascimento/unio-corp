<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Onboarding e offboarding RH + vínculo Funcionario/User';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE rh_onboarding_process (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            funcionario_id INT DEFAULT NULL,
            nome VARCHAR(150) NOT NULL,
            email VARCHAR(180) NOT NULL,
            cargo VARCHAR(100) DEFAULT NULL,
            status VARCHAR(24) NOT NULL,
            data_prevista DATE DEFAULT NULL,
            data_conclusao DATE DEFAULT NULL,
            observacoes LONGTEXT DEFAULT NULL,
            checklist JSON NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_ONBOARDING_EMPRESA (empresa_id),
            INDEX IDX_ONBOARDING_FUNC (funcionario_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_onboarding_process ADD CONSTRAINT FK_ONBOARDING_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rh_onboarding_process ADD CONSTRAINT FK_ONBOARDING_FUNC FOREIGN KEY (funcionario_id) REFERENCES funcionario (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE rh_offboarding_process (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            funcionario_id INT NOT NULL,
            status VARCHAR(24) NOT NULL,
            data_prevista DATE DEFAULT NULL,
            data_conclusao DATE DEFAULT NULL,
            motivo VARCHAR(80) DEFAULT NULL,
            observacoes LONGTEXT DEFAULT NULL,
            checklist JSON NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_OFFBOARDING_EMPRESA (empresa_id),
            INDEX IDX_OFFBOARDING_FUNC (funcionario_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rh_offboarding_process ADD CONSTRAINT FK_OFFBOARDING_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE rh_offboarding_process ADD CONSTRAINT FK_OFFBOARDING_FUNC FOREIGN KEY (funcionario_id) REFERENCES funcionario (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE funcionario ADD data_demissao DATE DEFAULT NULL, ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE funcionario ADD CONSTRAINT FK_FUNCIONARIO_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_FUNCIONARIO_USER ON funcionario (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE funcionario DROP FOREIGN KEY FK_FUNCIONARIO_USER');
        $this->addSql('DROP INDEX IDX_FUNCIONARIO_USER ON funcionario');
        $this->addSql('ALTER TABLE funcionario DROP data_demissao, DROP user_id');
        $this->addSql('ALTER TABLE rh_offboarding_process DROP FOREIGN KEY FK_OFFBOARDING_EMPRESA');
        $this->addSql('ALTER TABLE rh_offboarding_process DROP FOREIGN KEY FK_OFFBOARDING_FUNC');
        $this->addSql('DROP TABLE rh_offboarding_process');
        $this->addSql('ALTER TABLE rh_onboarding_process DROP FOREIGN KEY FK_ONBOARDING_EMPRESA');
        $this->addSql('ALTER TABLE rh_onboarding_process DROP FOREIGN KEY FK_ONBOARDING_FUNC');
        $this->addSql('DROP TABLE rh_onboarding_process');
    }
}
