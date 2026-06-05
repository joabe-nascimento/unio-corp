<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530280000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Observatório Causal — schema drift, shadow replay e extensão de traces';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE integ_schema_drift (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, conector_id INT DEFAULT NULL, flow_key VARCHAR(64) DEFAULT NULL, campo_origem VARCHAR(80) NOT NULL, campo_esperado VARCHAR(80) NOT NULL, campo_detectado VARCHAR(80) NOT NULL, severidade VARCHAR(16) NOT NULL, sugestao LONGTEXT NOT NULL, resolvido TINYINT(1) NOT NULL, detectado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_INTEG_DRIFT_EMPRESA (empresa_id), INDEX IDX_INTEG_DRIFT_CONECTOR (conector_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE integ_shadow_run (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, mapeamento_id INT DEFAULT NULL, mapeamento_nome VARCHAR(120) NOT NULL, campo_origem VARCHAR(80) NOT NULL, campo_destino_atual VARCHAR(80) NOT NULL, campo_destino_proposto VARCHAR(80) NOT NULL, periodo_dias INT NOT NULL, total_eventos INT NOT NULL, sucesso INT NOT NULL, falhas INT NOT NULL, duplicatas INT NOT NULL, amostras JSON NOT NULL, criado_em DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_INTEG_SHADOW_EMPRESA (empresa_id), INDEX IDX_INTEG_SHADOW_MAP (mapeamento_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE integ_schema_drift ADD CONSTRAINT FK_INTEG_DRIFT_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE integ_schema_drift ADD CONSTRAINT FK_INTEG_DRIFT_CONECTOR FOREIGN KEY (conector_id) REFERENCES integ_conector (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE integ_shadow_run ADD CONSTRAINT FK_INTEG_SHADOW_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE integ_shadow_run ADD CONSTRAINT FK_INTEG_SHADOW_MAP FOREIGN KEY (mapeamento_id) REFERENCES integ_mapeamento (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE integ_causal_trace ADD tendencia JSON DEFAULT NULL, ADD previsao JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integ_shadow_run DROP FOREIGN KEY FK_INTEG_SHADOW_EMPRESA');
        $this->addSql('ALTER TABLE integ_shadow_run DROP FOREIGN KEY FK_INTEG_SHADOW_MAP');
        $this->addSql('ALTER TABLE integ_schema_drift DROP FOREIGN KEY FK_INTEG_DRIFT_EMPRESA');
        $this->addSql('ALTER TABLE integ_schema_drift DROP FOREIGN KEY FK_INTEG_DRIFT_CONECTOR');
        $this->addSql('DROP TABLE integ_shadow_run');
        $this->addSql('DROP TABLE integ_schema_drift');
        $this->addSql('ALTER TABLE integ_causal_trace DROP tendencia, DROP previsao');
    }
}
