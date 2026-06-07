<?php



namespace App\Service\Ti;



use App\Entity\Empresa;
use App\Entity\TiChamado;
use App\Platform\AiAssistant;
use App\Repository\TiChamadoRepository;

/** Triagem Vitória / Cortex — regras inteligentes sobre dados reais. */
final class TiHeliaService
{
    public function __construct(
        private TiKbService $kbService,
        private TiChamadoRepository $chamadoRepository,
    ) {}

    /** @param array<string, mixed> $input */
    public function analyzeInput(array $input, ?Empresa $empresa = null): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $summary = trim((string) ($input['summary'] ?? ''));
        $text = mb_strtolower($title . ' ' . $summary);

        $rules = $this->matchRules($text);
        $kb = $empresa !== null
            ? $this->kbService->matchForText($empresa, $text, 3)
            : $this->matchKb($text, 3);

        $confidence = min(98, 72 + \count($kb) * 8 + ($rules['priority'] === 'P1' ? 6 : 0));
        if ($empresa !== null) {
            $feedbackBoost = $this->feedbackConfidenceBoost($empresa);
            $confidence = min(98, $confidence + $feedbackBoost);
        }

        return [
            'confidence' => $confidence,
            'summary' => $rules['analysis'],
            'suggested_category' => $rules['category'],
            'suggested_priority' => $rules['priority'],
            'suggested_impact' => $rules['impact'],
            'suggested_title' => $title !== '' ? $title : $rules['title_hint'],
            'kb_articles' => $kb,
            'similar_patterns' => $rules['patterns'],
            'estimated_resolution' => $rules['resolution'],
            'auto_triage_ready' => mb_strlen($summary) >= 20,
        ];
    }

    private function feedbackConfidenceBoost(Empresa $empresa): int
    {
        $stats = $this->chamadoRepository->heliaFeedbackStats($empresa);
        if ($stats['total'] < 3) {
            return 0;
        }
        $ratio = $stats['correct'] / max(1, $stats['total']);

        return (int) round(($ratio - 0.5) * 12);
    }

    /** @param array<string, mixed> $ticket */
    public function insightForTicket(array $ticket, ?Empresa $empresa = null): array

    {

        if (($ticket['helia_analysis'] ?? '') !== '') {

            $analysis = $this->analyzeInput([
                'title' => $ticket['title'] ?? '',
                'summary' => $ticket['summary'] ?? '',
            ], $empresa);

            $kbText = mb_strtolower(($ticket['title'] ?? '') . ' ' . ($ticket['summary'] ?? ''));
            $kb = $empresa !== null
                ? $this->kbService->matchForText($empresa, $kbText, 2)
                : $this->matchKb($kbText, 2);

            return [

                'suggestion' => (string) $ticket['helia_analysis'],

                'auto_applied' => (bool) ($ticket['helia_applied'] ?? false),

                'confidence' => (int) ($ticket['helia_confidence'] ?? 80),

                'status' => 'analyzed',

                'suggested_category' => $analysis['suggested_category'],

                'suggested_priority' => $analysis['suggested_priority'],

                'kb_articles' => $kb,

                'next_steps' => $this->nextSteps($ticket),

                'sentiment' => $this->sentiment($ticket),

            ];

        }



        $analysis = $this->analyzeInput([

            'title' => $ticket['title'] ?? '',

            'summary' => $ticket['summary'] ?? '',

        ], $empresa);



        return [

            'suggestion' => $analysis['summary'],

            'auto_applied' => (bool) ($ticket['helia_applied'] ?? false),

            'confidence' => $analysis['confidence'],

            'status' => 'analyzed',

            'suggested_category' => $analysis['suggested_category'],

            'suggested_priority' => $analysis['suggested_priority'],

            'kb_articles' => $analysis['kb_articles'],

            'next_steps' => $this->nextSteps($ticket),

            'sentiment' => $this->sentiment($ticket),

        ];

    }



    public function ticketHasInsight(array $ticket): bool

    {

        if (($ticket['helia_confidence'] ?? null) !== null) {

            return true;

        }



        $p = (string) ($ticket['priority'] ?? '');

        if (\in_array($p, ['P1', 'P2'], true)) {

            return true;

        }



        $text = mb_strtolower(($ticket['title'] ?? '') . ' ' . ($ticket['summary'] ?? ''));



        return str_contains($text, 'vpn')

            || str_contains($text, 'senha')

            || str_contains($text, '502')

            || str_contains($text, 'backup');

    }



    /** @return list<array<string, mixed>> */

    public function relatedTickets(array $ticket, Empresa $empresa, TiChamadoService $chamados, int $limit = 3): array

    {

        $cat = (string) ($ticket['category'] ?? '');

        $id = (string) ($ticket['id'] ?? '');

        $related = [];



        foreach ($chamados->all($empresa) as $t) {

            if ($t['id'] === $id || ($t['status'] ?? '') === TiChamado::STATUS_RESOLVIDO) {

                continue;

            }

            if (($t['category'] ?? '') === $cat) {

                $related[] = $t;

            }

        }



        return \array_slice($related, 0, $limit);

    }



    /** @return array<string, string> */

    private function matchRules(string $text): array

    {

        $category = 'sistema';

        $priority = 'P3';

        $impact = 'medio';

        $ai = AiAssistant::NAME;

        $analysis = $ai . ' classificou como solicitação genérica de TI. Revise categoria e prioridade antes de confirmar.';

        $titleHint = 'Descreva o problema em uma linha';

        $resolution = '24 h';

        $patterns = [];



        if (preg_match('/vpn|rede|wifi|conect/i', $text)) {

            $category = 'rede';

            $priority = 'P2';

            $impact = 'alto';

            $analysis = 'Padrão recorrente de conectividade remota detectado. ' . $ai . ' recomenda KB-042 e validação MFA antes de escalar.';

            $titleHint = 'Problema de VPN / conectividade';

            $resolution = '2 h';

            $patterns = ['Padrão VPN detectado'];

        } elseif (preg_match('/senha|acesso|login|mfa|bloque/i', $text)) {

            $category = 'acesso';

            $priority = 'P2';

            $impact = 'medio';

            $analysis = 'Solicitação de acesso ou credencial. ' . $ai . ' sugere fluxo de reset AD/MFA padronizado.';

            $titleHint = 'Reset de senha ou acesso';

            $resolution = '15 min';

            $patterns = ['Fluxo de acesso'];

        } elseif (preg_match('/502|integra|webhook|api|esocial/i', $text)) {

            $category = 'integracao';

            $priority = 'P1';

            $impact = 'critico';

            $analysis = 'Incidente de integração/API identificado. ' . $ai . ' correlaciona com falhas de webhook e recomenda reprocessamento com backoff.';

            $titleHint = 'Falha de integração';

            $resolution = '4 h';

            $patterns = ['Incidente de integração'];

        } elseif (preg_match('/backup|disco|sql|servidor/i', $text)) {

            $category = 'infra';

            $priority = 'P1';

            $impact = 'critico';

            $analysis = 'Incidente de infraestrutura/backup. ' . $ai . ' sugere verificar quota de disco e jobs noturnos.';

            $titleHint = 'Falha de backup ou infra';

            $resolution = '4 h';

            $patterns = ['Infraestrutura'];

        } elseif (preg_match('/notebook|monitor|impressora|hardware|tela/i', $text)) {

            $category = 'hardware';

            $priority = 'P3';

            $impact = 'medio';

            $analysis = 'Solicitação de hardware/periférico. ' . $ai . ' indica verificar estoque e ciclo de vida do ativo.';

            $titleHint = 'Equipamento ou periférico';

            $resolution = '48 h';

        } elseif (preg_match('/adobe|licença|licenca|software/i', $text)) {

            $category = 'licenca';

            $priority = 'P4';

            $impact = 'baixo';

            $analysis = 'Questão de licenciamento ou software. ' . $ai . ' identifica renovação ou instalação homologada.';

            $titleHint = 'Licença ou software';

            $resolution = '8 h';

        }



        return compact('category', 'priority', 'impact', 'analysis', 'titleHint', 'resolution', 'patterns');

    }



    /** @return list<array<string, mixed>> */

    private function matchKb(string $text, int $limit): array

    {

        $matched = [];

        foreach (TiReferenceData::knowledgeBase() as $kb) {

            foreach ($kb['keywords'] as $kw) {

                if (str_contains($text, mb_strtolower($kw))) {

                    $matched[] = $kb;

                    break;

                }

            }

        }



        if ($matched === []) {

            return \array_slice(TiReferenceData::knowledgeBase(), 0, min(2, $limit));

        }



        return \array_slice($matched, 0, $limit);

    }



    /** @param array<string, mixed> $ticket */

    private function nextSteps(array $ticket): array

    {

        $status = (string) ($ticket['status'] ?? TiChamado::STATUS_NOVO);



        return match ($status) {

            TiChamado::STATUS_NOVO => ['Validar informações com solicitante', 'Aplicar artigo KB sugerido', 'Atribuir técnico N1'],

            TiChamado::STATUS_EM_ANALISE => ['Executar diagnóstico remoto', 'Documentar causa raiz', 'Definir plano de ação'],

            TiChamado::STATUS_EM_EXECUCAO => ['Acompanhar SLA restante', 'Atualizar solicitante', 'Preparar encerramento'],

            TiChamado::STATUS_AGUARDANDO => ['Cobrar retorno do usuário', 'Monitorar prazo de SLA', 'Escalar se necessário'],

            default => ['Arquivar documentação', 'Registrar lições aprendidas'],

        };

    }



    /** @param array<string, mixed> $ticket */

    private function sentiment(array $ticket): array

    {

        $p = (string) ($ticket['priority'] ?? 'P3');

        $sla = (int) ($ticket['sla_pct'] ?? 100);



        if ($p === 'P1' || $sla < 40) {

            return ['label' => 'Crítico', 'tone' => 'danger'];

        }

        if ($p === 'P2' || $sla < 70) {

            return ['label' => 'Atenção', 'tone' => 'warning'];

        }



        return ['label' => 'Estável', 'tone' => 'success'];

    }

}


