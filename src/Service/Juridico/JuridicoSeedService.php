<?php

namespace App\Service\Juridico;

/**
 * Fornece dados de exemplo (seeds) para telas do Unio Jurídico em desenvolvimento.
 */
final class JuridicoSeedService
{
    /**
     * @return list<array{numero: string, cliente: string, area: string, fase: string, responsavel: string, prazo_dias: int, valor: string, status: string, tribunal: string}>
     */
    public function getProcessosExemplo(): array
    {
        return [
            [
                'numero' => '0001234-56.2025.5.02.0001',
                'cliente' => 'Silva & Associados Ltda.',
                'area' => 'Trabalhista',
                'fase' => 'Conhecimento',
                'responsavel' => 'Dr. Carlos Mendes',
                'prazo_dias' => 7,
                'valor' => 'R$ 45.000,00',
                'status' => 'ativo',
                'tribunal' => 'TRT 2ª Região',
            ],
            [
                'numero' => '1009876-54.2024.8.26.0100',
                'cliente' => 'Transportadora Ágil S.A.',
                'area' => 'Cível',
                'fase' => 'Recursal',
                'responsavel' => 'Dra. Ana Paula Costa',
                'prazo_dias' => 15,
                'valor' => 'R$ 120.000,00',
                'status' => 'ativo',
                'tribunal' => 'TJSP',
            ],
            [
                'numero' => '0003456-78.2025.4.01.3400',
                'cliente' => 'Indústria Metal Tech',
                'area' => 'Tributário',
                'fase' => 'Execução',
                'responsavel' => 'Dr. Roberto Alves',
                'prazo_dias' => 3,
                'valor' => 'R$ 230.000,00',
                'status' => 'crítico',
                'tribunal' => 'TRF 1ª Região',
            ],
            [
                'numero' => '0007890-12.2024.5.03.0012',
                'cliente' => 'Construções Unidas',
                'area' => 'Trabalhista',
                'fase' => 'Instrução',
                'responsavel' => 'Dr. Carlos Mendes',
                'prazo_dias' => 22,
                'valor' => 'R$ 38.500,00',
                'status' => 'ativo',
                'tribunal' => 'TRT 3ª Região',
            ],
            [
                'numero' => '0005678-90.2025.8.26.0224',
                'cliente' => 'Comércio São Jorge',
                'area' => 'Consumidor',
                'fase' => 'Sentença',
                'responsavel' => 'Dra. Fernanda Lima',
                'prazo_dias' => 45,
                'valor' => 'R$ 8.900,00',
                'status' => 'ativo',
                'tribunal' => 'TJSP',
            ],
        ];
    }

    /**
     * @return list<array{tipo: string, descricao: string, processo: string, data_limite: string, responsavel: string, dias_restantes: int, status: string}>
     */
    public function getPrazosExemplo(): array
    {
        return [
            [
                'tipo' => 'Contestação',
                'descricao' => 'Contestação em reclamação trabalhista',
                'processo' => '0001234-56.2025.5.02.0001',
                'data_limite' => '2026-08-02',
                'responsavel' => 'Dr. Carlos Mendes',
                'dias_restantes' => 3,
                'status' => 'crítico',
            ],
            [
                'tipo' => 'Apelação',
                'descricao' => 'Recurso de apelação contra sentença',
                'processo' => '1009876-54.2024.8.26.0100',
                'data_limite' => '2026-08-12',
                'responsavel' => 'Dra. Ana Paula Costa',
                'dias_restantes' => 13,
                'status' => 'alerta',
            ],
            [
                'tipo' => 'Embargos',
                'descricao' => 'Embargos à execução fiscal',
                'processo' => '0003456-78.2025.4.01.3400',
                'data_limite' => '2026-07-30',
                'responsavel' => 'Dr. Roberto Alves',
                'dias_restantes' => 1,
                'status' => 'crítico',
            ],
            [
                'tipo' => 'Manifestação',
                'descricao' => 'Manifestação sobre documentos',
                'processo' => '0007890-12.2024.5.03.0012',
                'data_limite' => '2026-08-15',
                'responsavel' => 'Dr. Carlos Mendes',
                'dias_restantes' => 16,
                'status' => 'ok',
            ],
            [
                'tipo' => 'Alegações Finais',
                'descricao' => 'Alegações finais em ação cível',
                'processo' => '0005678-90.2025.8.26.0224',
                'data_limite' => '2026-09-05',
                'responsavel' => 'Dra. Fernanda Lima',
                'dias_restantes' => 37,
                'status' => 'ok',
            ],
        ];
    }

    /**
     * @return list<array{nome: string, tipo: string, cnpj_cpf: string, email: string, telefone: string, area_atuacao: string, processos_ativos: int, valor_carteira: string, status: string}>
     */
    public function getClientesExemplo(): array
    {
        return [
            [
                'nome' => 'Silva & Associados Ltda.',
                'tipo' => 'PJ',
                'cnpj_cpf' => '12.345.678/0001-90',
                'email' => 'contato@silvaassociados.com.br',
                'telefone' => '(11) 3456-7890',
                'area_atuacao' => 'Construção Civil',
                'processos_ativos' => 3,
                'valor_carteira' => 'R$ 156.000,00',
                'status' => 'premium',
            ],
            [
                'nome' => 'Transportadora Ágil S.A.',
                'tipo' => 'PJ',
                'cnpj_cpf' => '98.765.432/0001-10',
                'email' => 'juridico@agilsa.com.br',
                'telefone' => '(11) 2345-6789',
                'area_atuacao' => 'Logística',
                'processos_ativos' => 2,
                'valor_carteira' => 'R$ 180.000,00',
                'status' => 'premium',
            ],
            [
                'nome' => 'João Carlos da Silva',
                'tipo' => 'PF',
                'cnpj_cpf' => '123.456.789-00',
                'email' => 'joao.silva@email.com',
                'telefone' => '(11) 98765-4321',
                'area_atuacao' => 'Consumidor',
                'processos_ativos' => 1,
                'valor_carteira' => 'R$ 8.900,00',
                'status' => 'standard',
            ],
            [
                'nome' => 'Indústria Metal Tech',
                'tipo' => 'PJ',
                'cnpj_cpf' => '45.678.901/0001-23',
                'email' => 'legal@metaltech.ind.br',
                'telefone' => '(31) 3456-7890',
                'area_atuacao' => 'Indústria Metalúrgica',
                'processos_ativos' => 5,
                'valor_carteira' => 'R$ 520.000,00',
                'status' => 'premium',
            ],
            [
                'nome' => 'Construções Unidas',
                'tipo' => 'PJ',
                'cnpj_cpf' => '56.789.012/0001-34',
                'email' => 'adm@construcoesunidas.com.br',
                'telefone' => '(21) 2345-6789',
                'area_atuacao' => 'Construção',
                'processos_ativos' => 2,
                'valor_carteira' => 'R$ 95.000,00',
                'status' => 'standard',
            ],
        ];
    }

    /**
     * @return list<array{nome: string, tipo: string, partes: string, vigencia_inicio: string, vigencia_fim: string, valor_mensal: string, clausulas_criticas: int, status_revisao: string, alerta_renovacao: bool}>
     */
    public function getContratosExemplo(): array
    {
        return [
            [
                'nome' => 'Contrato de Prestação de Serviços Advocatícios',
                'tipo' => 'Honorários',
                'partes' => 'Escritório Lima & Costa ↔ Silva & Associados',
                'vigencia_inicio' => '2025-01-15',
                'vigencia_fim' => '2027-01-14',
                'valor_mensal' => 'R$ 8.500,00',
                'clausulas_criticas' => 2,
                'status_revisao' => 'aprovado',
                'alerta_renovacao' => false,
            ],
            [
                'nome' => 'Contrato de Assessoria Tributária',
                'tipo' => 'Consultivo',
                'partes' => 'Escritório Lima & Costa ↔ Metal Tech Indústria',
                'vigencia_inicio' => '2024-06-01',
                'vigencia_fim' => '2026-05-31',
                'valor_mensal' => 'R$ 12.000,00',
                'clausulas_criticas' => 3,
                'status_revisao' => 'revisão',
                'alerta_renovacao' => true,
            ],
            [
                'nome' => 'Contrato de Êxito - Ação Trabalhista',
                'tipo' => 'Êxito',
                'partes' => 'Escritório Lima & Costa ↔ Transportadora Ágil',
                'vigencia_inicio' => '2024-11-20',
                'vigencia_fim' => '2026-11-19',
                'valor_mensal' => '20% êxito',
                'clausulas_criticas' => 1,
                'status_revisao' => 'ativo',
                'alerta_renovacao' => false,
            ],
        ];
    }

    /**
     * @return list<array{advogado: string, horas_mes: string, processos_ativos: int, taxa_exito: string, receita_mes: string, carga: string}>
     */
    public function getProducaoAdvogadosExemplo(): array
    {
        return [
            [
                'advogado' => 'Dr. Carlos Mendes',
                'horas_mes' => '142h',
                'processos_ativos' => 8,
                'taxa_exito' => '73%',
                'receita_mes' => 'R$ 28.500,00',
                'carga' => 'alta',
            ],
            [
                'advogado' => 'Dra. Ana Paula Costa',
                'horas_mes' => '128h',
                'processos_ativos' => 6,
                'taxa_exito' => '81%',
                'receita_mes' => 'R$ 32.000,00',
                'carga' => 'média',
            ],
            [
                'advogado' => 'Dr. Roberto Alves',
                'horas_mes' => '156h',
                'processos_ativos' => 11,
                'taxa_exito' => '68%',
                'receita_mes' => 'R$ 45.600,00',
                'carga' => 'alta',
            ],
            [
                'advogado' => 'Dra. Fernanda Lima',
                'horas_mes' => '98h',
                'processos_ativos' => 4,
                'taxa_exito' => '75%',
                'receita_mes' => 'R$ 18.900,00',
                'carga' => 'baixa',
            ],
        ];
    }

    /**
     * @return list<array{nome: string, categoria: string, tamanho: string, data: string, processo: string, confidencial: bool}>
     */
    public function getDocumentosExemplo(): array
    {
        return [
            [
                'nome' => 'Petição Inicial - Ação Trabalhista 001234',
                'categoria' => 'Petição',
                'tamanho' => '2.4 MB',
                'data' => '2025-07-15',
                'processo' => '0001234-56.2025.5.02.0001',
                'confidencial' => false,
            ],
            [
                'nome' => 'Contestação - Processo 1009876',
                'categoria' => 'Peça Processual',
                'tamanho' => '1.8 MB',
                'data' => '2024-12-10',
                'processo' => '1009876-54.2024.8.26.0100',
                'confidencial' => false,
            ],
            [
                'nome' => 'Contrato Confidencial - Metal Tech',
                'categoria' => 'Contrato',
                'tamanho' => '856 KB',
                'data' => '2024-06-01',
                'processo' => '—',
                'confidencial' => true,
            ],
            [
                'nome' => 'Parecer Jurídico - Tributário',
                'categoria' => 'Parecer',
                'tamanho' => '3.2 MB',
                'data' => '2025-03-22',
                'processo' => '0003456-78.2025.4.01.3400',
                'confidencial' => true,
            ],
            [
                'nome' => 'Procuração - Silva & Associados',
                'categoria' => 'Procuração',
                'tamanho' => '245 KB',
                'data' => '2025-01-15',
                'processo' => '—',
                'confidencial' => false,
            ],
        ];
    }

    /**
     * @return list<array{tribunal: string, tema: string, data: string, resultado: string, relevancia: string, link: string}>
     */
    public function getJurisprudenciaExemplo(): array
    {
        return [
            [
                'tribunal' => 'STJ',
                'tema' => 'Horas extras - bancário',
                'data' => '2024-11-15',
                'resultado' => 'Provido',
                'relevancia' => 'alta',
                'link' => 'RESp 1.234.567',
            ],
            [
                'tribunal' => 'TST',
                'tema' => 'Vínculo empregatício - aplicativo',
                'data' => '2025-02-28',
                'resultado' => 'Provido parcialmente',
                'relevancia' => 'alta',
                'link' => 'RR 12345-67.2023.5.01.0001',
            ],
            [
                'tribunal' => 'TJSP',
                'tema' => 'Dano moral - negativação indevida',
                'data' => '2024-09-10',
                'resultado' => 'Provido',
                'relevancia' => 'média',
                'link' => 'Ap 1234567-89.2024.8.26.0100',
            ],
            [
                'tribunal' => 'TRF-1',
                'tema' => 'Execução fiscal - prescrição intercorrente',
                'data' => '2025-01-20',
                'resultado' => 'Negado',
                'relevancia' => 'alta',
                'link' => 'AC 0012345-67.2024.4.01.3400',
            ],
        ];
    }

    /**
     * @return array{processos_ativos: int, prazos_criticos: int, clientes_premium: int, receita_mes: string, taxa_exito: string, horas_faturadas: string}
     */
    public function getDashboardMetricas(): array
    {
        return [
            'processos_ativos' => 23,
            'prazos_criticos' => 5,
            'clientes_premium' => 12,
            'receita_mes' => 'R$ 125.000,00',
            'taxa_exito' => '74%',
            'horas_faturadas' => '524h',
        ];
    }
}
