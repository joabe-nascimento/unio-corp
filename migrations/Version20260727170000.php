<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unio Jurídico: Kanban/tarefas/partes em Processos e favoritos/histórico em Jurisprudência';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE juridico_processo_tarefa (id INT AUTO_INCREMENT NOT NULL, titulo VARCHAR(160) NOT NULL, descricao LONGTEXT DEFAULT NULL, prazo DATETIME DEFAULT NULL, status VARCHAR(16) NOT NULL, criado_em DATETIME NOT NULL, concluido_em DATETIME DEFAULT NULL, processo_id INT NOT NULL, responsavel_id INT DEFAULT NULL, INDEX IDX_JUR_TAREFA_PROCESSO (processo_id), INDEX IDX_JUR_TAREFA_RESPONSAVEL (responsavel_id), INDEX IDX_JUR_TAREFA_PROCESSO_STATUS (processo_id, status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE juridico_processo_parte (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(180) NOT NULL, tipo VARCHAR(20) NOT NULL, polo VARCHAR(10) NOT NULL, documento VARCHAR(20) DEFAULT NULL, advogado VARCHAR(160) DEFAULT NULL, oab VARCHAR(20) DEFAULT NULL, principal TINYINT NOT NULL, criado_em DATETIME NOT NULL, processo_id INT NOT NULL, INDEX IDX_JUR_PARTE_PROCESSO (processo_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE juridico_jurisprudencia_consulta (id INT AUTO_INCREMENT NOT NULL, tema VARCHAR(220) NOT NULL, tribunal VARCHAR(40) NOT NULL, periodo VARCHAR(60) DEFAULT NULL, area_juridica VARCHAR(80) DEFAULT NULL, resultados_count INT NOT NULL, criado_em DATETIME NOT NULL, empresa_id INT NOT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_JUR_CONSULTA_EMPRESA (empresa_id), INDEX IDX_JUR_CONSULTA_CREATED_BY (created_by_id), INDEX IDX_JUR_CONSULTA_EMPRESA_DATA (empresa_id, criado_em), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE juridico_processo_tarefa ADD CONSTRAINT FK_JUR_TAREFA_PROCESSO FOREIGN KEY (processo_id) REFERENCES juridico_processo (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_processo_tarefa ADD CONSTRAINT FK_JUR_TAREFA_RESPONSAVEL FOREIGN KEY (responsavel_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_processo_parte ADD CONSTRAINT FK_JUR_PARTE_PROCESSO FOREIGN KEY (processo_id) REFERENCES juridico_processo (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_jurisprudencia_consulta ADD CONSTRAINT FK_JUR_CONSULTA_EMPRESA FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_jurisprudencia_consulta ADD CONSTRAINT FK_JUR_CONSULTA_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_jurisprudencia ADD favorito TINYINT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE juridico_processo_tarefa DROP FOREIGN KEY FK_JUR_TAREFA_PROCESSO');
        $this->addSql('ALTER TABLE juridico_processo_tarefa DROP FOREIGN KEY FK_JUR_TAREFA_RESPONSAVEL');
        $this->addSql('ALTER TABLE juridico_processo_parte DROP FOREIGN KEY FK_JUR_PARTE_PROCESSO');
        $this->addSql('ALTER TABLE juridico_jurisprudencia_consulta DROP FOREIGN KEY FK_JUR_CONSULTA_EMPRESA');
        $this->addSql('ALTER TABLE juridico_jurisprudencia_consulta DROP FOREIGN KEY FK_JUR_CONSULTA_CREATED_BY');
        $this->addSql('DROP TABLE juridico_processo_tarefa');
        $this->addSql('DROP TABLE juridico_processo_parte');
        $this->addSql('DROP TABLE juridico_jurisprudencia_consulta');
        $this->addSql('ALTER TABLE juridico_jurisprudencia DROP favorito');
    }
}
