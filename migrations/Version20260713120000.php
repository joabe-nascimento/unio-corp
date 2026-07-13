<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Organismo Runtime: vitality, reflexos, contratos de cuidado, memória e gêmeo do dia';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organismo_vitality_snapshot (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            score SMALLINT NOT NULL,
            nivel VARCHAR(24) NOT NULL,
            breakdown JSON NOT NULL,
            tendencia SMALLINT DEFAULT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_ORG_VITAL_EMPRESA_CRIADO (empresa_id, criado_em),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE organismo_vitality_snapshot ADD CONSTRAINT FK_ORG_VITAL_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE organismo_reflex_log (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            paciente_id INT DEFAULT NULL,
            reflex_code VARCHAR(64) NOT NULL,
            motivo VARCHAR(255) NOT NULL,
            acao VARCHAR(64) NOT NULL,
            alvo VARCHAR(255) DEFAULT NULL,
            payload JSON NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_ORG_REFLEX_EMPRESA_CRIADO (empresa_id, criado_em),
            INDEX IDX_ORG_REFLEX_CODE (empresa_id, reflex_code),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE organismo_reflex_log ADD CONSTRAINT FK_ORG_REFLEX_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organismo_reflex_log ADD CONSTRAINT FK_ORG_REFLEX_PACIENTE FOREIGN KEY (paciente_id) REFERENCES pos_operatorio_paciente (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE organismo_care_contract (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            paciente_id INT NOT NULL,
            protocolo_id INT DEFAULT NULL,
            versao SMALLINT NOT NULL,
            status VARCHAR(24) NOT NULL,
            content_hash VARCHAR(64) NOT NULL,
            snapshot JSON NOT NULL,
            ativo TINYINT(1) NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            atualizado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_ORG_CONTRACT_EMPRESA (empresa_id),
            INDEX IDX_ORG_CONTRACT_PACIENTE (paciente_id, ativo),
            UNIQUE INDEX UNIQ_ORG_CONTRACT_PAC_VER (paciente_id, versao),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE organismo_care_contract ADD CONSTRAINT FK_ORG_CONTRACT_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organismo_care_contract ADD CONSTRAINT FK_ORG_CONTRACT_PACIENTE FOREIGN KEY (paciente_id) REFERENCES pos_operatorio_paciente (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organismo_care_contract ADD CONSTRAINT FK_ORG_CONTRACT_PROTOCOLO FOREIGN KEY (protocolo_id) REFERENCES pos_operatorio_protocolo (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE organismo_care_attestation (
            id INT AUTO_INCREMENT NOT NULL,
            contract_id INT NOT NULL,
            ator_id INT DEFAULT NULL,
            marco_key VARCHAR(64) NOT NULL,
            evidencia VARCHAR(255) NOT NULL,
            content_hash VARCHAR(64) NOT NULL,
            prev_hash VARCHAR(64) DEFAULT NULL,
            payload JSON NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_ORG_ATTEST_CONTRACT (contract_id, criado_em),
            UNIQUE INDEX UNIQ_ORG_ATTEST_MARCO (contract_id, marco_key),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE organismo_care_attestation ADD CONSTRAINT FK_ORG_ATTEST_CONTRACT FOREIGN KEY (contract_id) REFERENCES organismo_care_contract (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organismo_care_attestation ADD CONSTRAINT FK_ORG_ATTEST_ATOR FOREIGN KEY (ator_id) REFERENCES user (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE organismo_memory_fact (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            paciente_id INT DEFAULT NULL,
            tipo VARCHAR(64) NOT NULL,
            sujeito VARCHAR(160) NOT NULL,
            peso SMALLINT NOT NULL,
            payload JSON NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_ORG_MEMORY_EMPRESA_TIPO (empresa_id, tipo, criado_em),
            INDEX IDX_ORG_MEMORY_PACIENTE (paciente_id, criado_em),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE organismo_memory_fact ADD CONSTRAINT FK_ORG_MEMORY_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organismo_memory_fact ADD CONSTRAINT FK_ORG_MEMORY_PACIENTE FOREIGN KEY (paciente_id) REFERENCES pos_operatorio_paciente (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE organismo_day_twin_run (
            id INT AUTO_INCREMENT NOT NULL,
            empresa_id INT NOT NULL,
            dia DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            scenarios JSON NOT NULL,
            criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_ORG_TWIN_EMPRESA_DIA (empresa_id, dia),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE organismo_day_twin_run ADD CONSTRAINT FK_ORG_TWIN_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organismo_care_attestation DROP FOREIGN KEY FK_ORG_ATTEST_CONTRACT');
        $this->addSql('ALTER TABLE organismo_care_attestation DROP FOREIGN KEY FK_ORG_ATTEST_ATOR');
        $this->addSql('ALTER TABLE organismo_care_contract DROP FOREIGN KEY FK_ORG_CONTRACT_EMPRESA');
        $this->addSql('ALTER TABLE organismo_care_contract DROP FOREIGN KEY FK_ORG_CONTRACT_PACIENTE');
        $this->addSql('ALTER TABLE organismo_care_contract DROP FOREIGN KEY FK_ORG_CONTRACT_PROTOCOLO');
        $this->addSql('ALTER TABLE organismo_reflex_log DROP FOREIGN KEY FK_ORG_REFLEX_EMPRESA');
        $this->addSql('ALTER TABLE organismo_reflex_log DROP FOREIGN KEY FK_ORG_REFLEX_PACIENTE');
        $this->addSql('ALTER TABLE organismo_memory_fact DROP FOREIGN KEY FK_ORG_MEMORY_EMPRESA');
        $this->addSql('ALTER TABLE organismo_memory_fact DROP FOREIGN KEY FK_ORG_MEMORY_PACIENTE');
        $this->addSql('ALTER TABLE organismo_vitality_snapshot DROP FOREIGN KEY FK_ORG_VITAL_EMPRESA');
        $this->addSql('ALTER TABLE organismo_day_twin_run DROP FOREIGN KEY FK_ORG_TWIN_EMPRESA');
        $this->addSql('DROP TABLE organismo_care_attestation');
        $this->addSql('DROP TABLE organismo_care_contract');
        $this->addSql('DROP TABLE organismo_reflex_log');
        $this->addSql('DROP TABLE organismo_memory_fact');
        $this->addSql('DROP TABLE organismo_vitality_snapshot');
        $this->addSql('DROP TABLE organismo_day_twin_run');
    }
}
