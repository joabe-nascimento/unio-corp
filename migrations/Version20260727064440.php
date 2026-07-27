<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727064440 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unio Jurídico: tabelas reais para Processos, Prazos, Clientes, Documentos, Honorários e Jurisprudência';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE juridico_cliente (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(180) NOT NULL, tipo VARCHAR(2) NOT NULL, documento VARCHAR(20) DEFAULT NULL, email VARCHAR(160) DEFAULT NULL, telefone VARCHAR(20) DEFAULT NULL, area_atuacao VARCHAR(120) DEFAULT NULL, status VARCHAR(16) NOT NULL, observacoes LONGTEXT DEFAULT NULL, criado_em DATETIME NOT NULL, atualizado_em DATETIME DEFAULT NULL, empresa_id INT NOT NULL, INDEX IDX_CA44147A521E1991 (empresa_id), INDEX IDX_JUR_CLIENTE_EMPRESA_STATUS (empresa_id, status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE juridico_documento (id INT AUTO_INCREMENT NOT NULL, nome VARCHAR(200) NOT NULL, categoria VARCHAR(24) NOT NULL, arquivo_path VARCHAR(255) NOT NULL, mime_type VARCHAR(120) DEFAULT NULL, tamanho_bytes INT NOT NULL, confidencial TINYINT NOT NULL, criado_em DATETIME NOT NULL, empresa_id INT NOT NULL, processo_id INT DEFAULT NULL, uploaded_by_id INT DEFAULT NULL, INDEX IDX_F695CFB5AAA822D2 (processo_id), INDEX IDX_F695CFB5A2B28FE8 (uploaded_by_id), INDEX IDX_JUR_DOCUMENTO_EMPRESA (empresa_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE juridico_honorario_lancamento (id INT AUTO_INCREMENT NOT NULL, data DATETIME NOT NULL, horas NUMERIC(6, 2) NOT NULL, valor_hora NUMERIC(10, 2) DEFAULT NULL, descricao LONGTEXT DEFAULT NULL, faturavel TINYINT NOT NULL, criado_em DATETIME NOT NULL, atualizado_em DATETIME DEFAULT NULL, empresa_id INT NOT NULL, advogado_id INT DEFAULT NULL, processo_id INT DEFAULT NULL, INDEX IDX_438B3D92521E1991 (empresa_id), INDEX IDX_438B3D92B3EDD038 (advogado_id), INDEX IDX_438B3D92AAA822D2 (processo_id), INDEX IDX_JUR_HONOR_EMPRESA_DATA (empresa_id, data), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE juridico_jurisprudencia (id INT AUTO_INCREMENT NOT NULL, tribunal VARCHAR(40) NOT NULL, tema VARCHAR(220) NOT NULL, data DATETIME DEFAULT NULL, resultado VARCHAR(120) DEFAULT NULL, relevancia VARCHAR(10) NOT NULL, referencia VARCHAR(120) DEFAULT NULL, resumo LONGTEXT DEFAULT NULL, criado_em DATETIME NOT NULL, atualizado_em DATETIME DEFAULT NULL, empresa_id INT NOT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_892335C8521E1991 (empresa_id), INDEX IDX_892335C8B03A8386 (created_by_id), INDEX IDX_JUR_JURISPR_EMPRESA_RELEV (empresa_id, relevancia), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE juridico_prazo (id INT AUTO_INCREMENT NOT NULL, tipo VARCHAR(80) NOT NULL, descricao LONGTEXT DEFAULT NULL, data_limite DATETIME NOT NULL, cumprido TINYINT NOT NULL, criado_em DATETIME NOT NULL, atualizado_em DATETIME DEFAULT NULL, empresa_id INT NOT NULL, processo_id INT DEFAULT NULL, responsavel_id INT DEFAULT NULL, INDEX IDX_44C022CD521E1991 (empresa_id), INDEX IDX_44C022CDAAA822D2 (processo_id), INDEX IDX_44C022CDBB9AF004 (responsavel_id), INDEX IDX_JUR_PRAZO_EMPRESA_CUMPRIDO (empresa_id, cumprido), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE juridico_processo (id INT AUTO_INCREMENT NOT NULL, numero VARCHAR(40) NOT NULL, area VARCHAR(80) DEFAULT NULL, fase VARCHAR(20) NOT NULL, tribunal VARCHAR(80) DEFAULT NULL, valor NUMERIC(12, 2) DEFAULT NULL, status VARCHAR(16) NOT NULL, resultado VARCHAR(20) DEFAULT NULL, observacoes LONGTEXT DEFAULT NULL, criado_em DATETIME NOT NULL, atualizado_em DATETIME DEFAULT NULL, empresa_id INT NOT NULL, cliente_id INT DEFAULT NULL, responsavel_id INT DEFAULT NULL, INDEX IDX_ED0FACC7521E1991 (empresa_id), INDEX IDX_ED0FACC7DE734E51 (cliente_id), INDEX IDX_ED0FACC7BB9AF004 (responsavel_id), INDEX IDX_JUR_PROCESSO_EMPRESA_STATUS (empresa_id, status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE juridico_cliente ADD CONSTRAINT FK_CA44147A521E1991 FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_documento ADD CONSTRAINT FK_F695CFB5521E1991 FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_documento ADD CONSTRAINT FK_F695CFB5AAA822D2 FOREIGN KEY (processo_id) REFERENCES juridico_processo (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_documento ADD CONSTRAINT FK_F695CFB5A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_honorario_lancamento ADD CONSTRAINT FK_438B3D92521E1991 FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_honorario_lancamento ADD CONSTRAINT FK_438B3D92B3EDD038 FOREIGN KEY (advogado_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_honorario_lancamento ADD CONSTRAINT FK_438B3D92AAA822D2 FOREIGN KEY (processo_id) REFERENCES juridico_processo (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_jurisprudencia ADD CONSTRAINT FK_892335C8521E1991 FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_jurisprudencia ADD CONSTRAINT FK_892335C8B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_prazo ADD CONSTRAINT FK_44C022CD521E1991 FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_prazo ADD CONSTRAINT FK_44C022CDAAA822D2 FOREIGN KEY (processo_id) REFERENCES juridico_processo (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_prazo ADD CONSTRAINT FK_44C022CDBB9AF004 FOREIGN KEY (responsavel_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_processo ADD CONSTRAINT FK_ED0FACC7521E1991 FOREIGN KEY (empresa_id) REFERENCES empresa (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE juridico_processo ADD CONSTRAINT FK_ED0FACC7DE734E51 FOREIGN KEY (cliente_id) REFERENCES juridico_cliente (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE juridico_processo ADD CONSTRAINT FK_ED0FACC7BB9AF004 FOREIGN KEY (responsavel_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE juridico_documento DROP FOREIGN KEY FK_F695CFB5AAA822D2');
        $this->addSql('ALTER TABLE juridico_honorario_lancamento DROP FOREIGN KEY FK_438B3D92AAA822D2');
        $this->addSql('ALTER TABLE juridico_prazo DROP FOREIGN KEY FK_44C022CDAAA822D2');
        $this->addSql('ALTER TABLE juridico_processo DROP FOREIGN KEY FK_ED0FACC7DE734E51');
        $this->addSql('DROP TABLE juridico_documento');
        $this->addSql('DROP TABLE juridico_honorario_lancamento');
        $this->addSql('DROP TABLE juridico_jurisprudencia');
        $this->addSql('DROP TABLE juridico_prazo');
        $this->addSql('DROP TABLE juridico_processo');
        $this->addSql('DROP TABLE juridico_cliente');
    }
}
