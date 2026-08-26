<?php

namespace App\Service\Juridico;

use App\Contract\LegalAiClientInterface;
use App\Entity\Empresa;
use App\Entity\JuridicoAtendimentoTemplate;
use App\Entity\JuridicoAtendimentoTicket;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoAtendimentoTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

final class JuridicoAtendimentoTemplateService
{
    /** @var list<array{titulo: string, area: string, corpo: string}> */
    private const DEFAULTS = [
        [
            'titulo' => 'Atualização de andamento',
            'area' => 'geral',
            'corpo' => "Olá, {{cliente}}!\n\nSegue atualização sobre o processo {{processo}}: estamos acompanhando o andamento e manteremos você informado(a) sobre qualquer movimentação relevante.\n\nQualquer dúvida, estamos à disposição.\n\nAtenciosamente,\n{{escritorio}}",
        ],
        [
            'titulo' => 'Solicitação de documentos',
            'area' => 'geral',
            'corpo' => "Olá, {{cliente}}!\n\nPara dar continuidade ao processo {{processo}}, precisamos dos seguintes documentos:\n\n• [liste os documentos]\n\nPode enviar por este canal ou pelo e-mail cadastrado.\n\nObrigado(a)!",
        ],
        [
            'titulo' => 'Prazo processual próximo',
            'area' => 'contencioso',
            'corpo' => "Olá, {{cliente}}!\n\nInformamos que há prazo processual relevante no processo {{processo}}. Nossa equipe está atuando conforme necessário.\n\nSe precisar de esclarecimentos, responda esta mensagem.",
        ],
        [
            'titulo' => 'Agendamento de reunião',
            'area' => 'relacionamento',
            'corpo' => "Olá, {{cliente}}!\n\nGostaríamos de agendar uma conversa para alinhar o andamento do seu caso. Informe, por favor, os melhores dias e horários para você.\n\nAguardamos seu retorno.",
        ],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoAtendimentoTemplateRepository $repo,
        private LegalAiClientInterface $ai,
    ) {
    }

    public function ensureDefaults(Empresa $empresa): void
    {
        if ($this->repo->countForEmpresa($empresa) > 0) {
            return;
        }

        foreach (self::DEFAULTS as $item) {
            $tpl = (new JuridicoAtendimentoTemplate())
                ->setEmpresa($empresa)
                ->setTitulo($item['titulo'])
                ->setArea($item['area'])
                ->setCorpo($item['corpo']);
            $this->em->persist($tpl);
        }

        $this->em->flush();
    }

    /** @return list<JuridicoAtendimentoTemplate> */
    public function listarAtivos(Empresa $empresa, ?string $area = null): array
    {
        $this->ensureDefaults($empresa);

        return $this->repo->findAtivosForEmpresa($empresa, $area);
    }

    public function renderizar(JuridicoAtendimentoTemplate $template, JuridicoAtendimentoTicket $ticket): string
    {
        $vars = [
            '{{cliente}}' => $ticket->getCliente()?->getNome() ?? 'cliente',
            '{{processo}}' => $ticket->getProcesso()?->getNumero() ?? 'em andamento',
            '{{escritorio}}' => $ticket->getEmpresa()->getNome() ?? 'Escritório',
            '{{assunto}}' => $ticket->getAssunto(),
        ];

        return str_replace(array_keys($vars), array_values($vars), $template->getCorpo());
    }

    /**
     * @param array<string, mixed> $data
     */
    public function criar(Empresa $empresa, array $data): JuridicoAtendimentoTemplate
    {
        $titulo = trim((string) ($data['titulo'] ?? ''));
        $corpo = trim((string) ($data['corpo'] ?? ''));
        if ($titulo === '' || $corpo === '') {
            throw new JuridicoProcessException('Título e corpo do template são obrigatórios.');
        }

        $tpl = (new JuridicoAtendimentoTemplate())
            ->setEmpresa($empresa)
            ->setTitulo($titulo)
            ->setCorpo($corpo)
            ->setArea(trim((string) ($data['area'] ?? '')) ?: null);

        $this->em->persist($tpl);
        $this->em->flush();

        return $tpl;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function atualizar(JuridicoAtendimentoTemplate $template, array $data): void
    {
        $titulo = trim((string) ($data['titulo'] ?? ''));
        $corpo = trim((string) ($data['corpo'] ?? ''));
        if ($titulo === '' || $corpo === '') {
            throw new JuridicoProcessException('Título e corpo do template são obrigatórios.');
        }

        $template
            ->setTitulo($titulo)
            ->setCorpo($corpo)
            ->setArea(trim((string) ($data['area'] ?? '')) ?: null)
            ->touch();

        $this->em->flush();
    }

    public function excluir(JuridicoAtendimentoTemplate $template): void
    {
        $template->setAtivo(false)->touch();
        $this->em->flush();
    }

    public function loadForEmpresa(Empresa $empresa, int $id): JuridicoAtendimentoTemplate
    {
        $tpl = $this->repo->findOneByEmpresa($empresa, $id);
        if (!$tpl || !$tpl->isAtivo()) {
            throw new JuridicoProcessException('Template não encontrado.');
        }

        return $tpl;
    }

    public function sugerirComSasha(JuridicoAtendimentoTicket $ticket, ?string $instrucaoExtra = null): string
    {
        $contexto = $this->montarContextoTicket($ticket);
        $ultimaEntrada = '';
        foreach ($ticket->getMensagens() as $msg) {
            if ($msg->getDirecao() === \App\Entity\JuridicoAtendimentoMensagem::DIRECAO_ENTRADA) {
                $ultimaEntrada = $msg->getCorpo();
            }
        }

        $prompt = "Você é a Sasha, assistente jurídica do escritório. Redija uma resposta empática e profissional para o cliente.\n\n";
        $prompt .= "Contexto do caso:\n" . $contexto . "\n\n";
        if ($ultimaEntrada !== '') {
            $prompt .= "Última mensagem do cliente:\n" . $ultimaEntrada . "\n\n";
        }
        if ($instrucaoExtra !== null && trim($instrucaoExtra) !== '') {
            $prompt .= "Instrução adicional do atendente: " . trim($instrucaoExtra) . "\n\n";
        }
        $prompt .= 'Responda apenas com o texto da mensagem, sem markdown, pronta para enviar ao cliente.';

        $result = $this->ai->chat($prompt, [], [
            'escritorio_id' => (string) $ticket->getEmpresa()->getId(),
            'mode' => 'standard',
        ]);

        if ($result === null || trim($result['reply'] ?? '') === '') {
            throw new JuridicoProcessException('Sasha indisponível para sugerir resposta. Tente novamente em instantes.');
        }

        return trim($result['reply']);
    }

    private function montarContextoTicket(JuridicoAtendimentoTicket $ticket): string
    {
        $linhas = ['Assunto: ' . $ticket->getAssunto()];
        if ($ticket->getCliente() !== null) {
            $linhas[] = 'Cliente: ' . $ticket->getCliente()->getNome();
            $linhas[] = 'Carteira: ' . $ticket->getCliente()->getStatus();
        }
        if ($ticket->getProcesso() !== null) {
            $p = $ticket->getProcesso();
            $linhas[] = 'Processo: ' . $p->getNumero();
            $linhas[] = 'Fase: ' . $p->getFase();
            $linhas[] = 'Status: ' . $p->getStatus();
            if ($p->getTribunal() !== null) {
                $linhas[] = 'Tribunal: ' . $p->getTribunal();
            }
        }

        return implode("\n", $linhas);
    }
}
