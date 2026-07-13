<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\ClinicAgendamento;
use App\Entity\Empresa;
use App\Entity\User;
use App\Http\RequestInts;
use App\Service\PosOperatorio\ClinicAgendaService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/agenda')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioAgendaController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicAgendaService $agenda,
    ) {}

    #[Route('', name: 'app_pos_operatorio_agenda', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $vista = $request->query->getString('vista', 'dia');
        if (!\in_array($vista, ['dia', 'semana'], true)) {
            $vista = 'dia';
        }

        $medicoId = RequestInts::positiveOrNull($request->query->get('medico_id'));
        $medico = $medicoId !== null ? $this->agenda->findMedico($empresa, $medicoId) : null;

        $day = null;
        $dayParam = trim($request->query->getString('dia'));
        if ($dayParam !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayParam)) {
            $day = new \DateTimeImmutable($dayParam);
        }

        $weekStart = null;
        $weekParam = trim($request->query->getString('semana'));
        if ($weekParam !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekParam)) {
            $weekStart = (new \DateTimeImmutable($weekParam))->modify('monday this week')->setTime(0, 0);
        }

        if ($vista === 'semana') {
            $lista = $this->agenda->listWeek($empresa, $weekStart ?? ($day?->modify('monday this week')), $medico);
            $focusDate = $lista['week_start'];
        } else {
            $lista = $this->agenda->listDay($empresa, $day, $medico);
            $focusDate = $lista['day'];
        }

        $items = $lista['items'];

        return $this->render('modules/pos-operatorio/agenda/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'agenda',
            'vista' => $vista,
            'agendamentos' => $items,
            'day' => $vista === 'dia' ? $lista['day'] : $focusDate,
            'week_start' => $vista === 'semana' ? $lista['week_start'] : $focusDate->modify('monday this week')->setTime(0, 0),
            'week_end' => $vista === 'semana' ? $lista['week_end'] : $focusDate->modify('monday this week')->setTime(0, 0)->modify('+7 days'),
            'medico_filtro' => $medico,
            'medicos' => $this->agenda->listMedicos($empresa),
            'statuses' => ClinicAgendamento::STATUSES,
            'status_labels' => ClinicAgendaService::statusLabels(),
            'stats' => $this->agenda->countByStatus($items),
            'whatsapp_map' => $this->agenda->confirmWhatsappMap($items),
        ]);
    }

    #[Route('/novo', name: 'app_pos_operatorio_agenda_novo', methods: ['GET', 'POST'])]
    public function novo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $prefill = $this->agenda->prefillFromRequest($empresa, $request->query->all());

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_agenda', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');

                return $this->redirectToRoute('app_pos_operatorio_agenda_novo');
            }

            try {
                $data = $this->parseFormData($request);
                $this->agenda->create($empresa, $data);
                $this->addFlash('success', 'Agendamento criado.');

                return $this->redirectToRoute('app_pos_operatorio_agenda', [
                    'vista' => 'dia',
                    'dia' => $data['inicio']->format('Y-m-d'),
                ]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
                $prefill = array_merge($prefill, $this->formToPrefill($request));
            }
        }

        return $this->render('modules/pos-operatorio/agenda/form.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'agenda',
            'agendamento' => null,
            'prefill' => $prefill,
            'pacientes' => $this->agenda->listPacientesAtivos($empresa),
            'medicos' => $this->agenda->listMedicos($empresa),
            'statuses' => ClinicAgendamento::STATUSES,
            'status_labels' => ClinicAgendaService::statusLabels(),
            'origens' => ClinicAgendamento::ORIGENS,
        ]);
    }

    #[Route('/{id}', name: 'app_pos_operatorio_agenda_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $agendamento = $this->agenda->find($empresa, $id);
        if ($agendamento === null) {
            throw $this->createNotFoundException('Agendamento não encontrado.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_agenda_' . $id, (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');

                return $this->redirectToRoute('app_pos_operatorio_agenda_editar', ['id' => $id]);
            }

            try {
                $data = $this->parseFormData($request);
                $this->agenda->update($agendamento, $empresa, $data);
                $this->addFlash('success', 'Agendamento atualizado.');

                return $this->redirectToRoute('app_pos_operatorio_agenda', [
                    'vista' => 'dia',
                    'dia' => $agendamento->getInicio()->format('Y-m-d'),
                ]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('modules/pos-operatorio/agenda/form.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'agenda',
            'agendamento' => $agendamento,
            'prefill' => [
                'paciente_id' => $agendamento->getPaciente()->getId(),
                'medico_id' => $agendamento->getMedico()?->getId(),
                'inicio' => $agendamento->getInicio()->format('Y-m-d\TH:i'),
                'fim' => $agendamento->getFim()->format('Y-m-d\TH:i'),
                'titulo' => $agendamento->getTitulo() ?? '',
                'observacao' => $agendamento->getObservacao() ?? '',
                'origem' => $agendamento->getOrigem(),
                'protocolo_dia' => $agendamento->getProtocoloDia(),
                'status' => $agendamento->getStatus(),
            ],
            'pacientes' => $this->agenda->listPacientesAtivos($empresa),
            'medicos' => $this->agenda->listMedicos($empresa),
            'statuses' => ClinicAgendamento::STATUSES,
            'status_labels' => ClinicAgendaService::statusLabels(),
            'origens' => ClinicAgendamento::ORIGENS,
        ]);
    }

    #[Route('/{id}/status', name: 'app_pos_operatorio_agenda_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function status(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $agendamento = $this->agenda->find($empresa, $id);
        if ($agendamento === null) {
            throw $this->createNotFoundException('Agendamento não encontrado.');
        }

        if (!$this->isCsrfTokenValid('clinic_agenda_status_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_agenda');
        }

        try {
            $this->agenda->changeStatus($agendamento, $empresa, $request->request->getString('status'));
            $this->addFlash('success', 'Status atualizado.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        $vista = $request->request->getString('vista', $request->query->getString('vista', 'dia'));
        $params = [
            'vista' => \in_array($vista, ['dia', 'semana'], true) ? $vista : 'dia',
        ];
        $medicoFiltro = RequestInts::positiveOrNull(
            $request->request->get('medico_id', $request->query->get('medico_id'))
        );
        if ($medicoFiltro !== null) {
            $params['medico_id'] = $medicoFiltro;
        }
        if ($params['vista'] === 'semana') {
            $params['semana'] = $agendamento->getInicio()->format('Y-m-d');
        } else {
            $params['dia'] = $agendamento->getInicio()->format('Y-m-d');
        }

        return $this->redirectToRoute('app_pos_operatorio_agenda', $params);
    }

    /** @return array<string, mixed> */
    private function parseFormData(Request $request): array
    {
        $inicioRaw = $request->request->getString('inicio');
        $fimRaw = $request->request->getString('fim');
        if ($inicioRaw === '' || $fimRaw === '') {
            throw new \InvalidArgumentException('Informe início e fim.');
        }

        try {
            $inicio = new \DateTimeImmutable($inicioRaw);
            $fim = new \DateTimeImmutable($fimRaw);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Datas inválidas.');
        }

        $pacienteId = RequestInts::positiveOrNull($request->request->get('paciente_id'));
        if ($pacienteId === null) {
            throw new \InvalidArgumentException('Selecione o paciente.');
        }

        return [
            'paciente_id' => $pacienteId,
            'medico_id' => RequestInts::positiveOrNull($request->request->get('medico_id')),
            'inicio' => $inicio,
            'fim' => $fim,
            'titulo' => $request->request->getString('titulo'),
            'observacao' => $request->request->getString('observacao'),
            'origem' => $request->request->getString('origem', ClinicAgendamento::ORIGEM_MANUAL),
            'protocolo_dia' => RequestInts::positiveOrNull($request->request->get('protocolo_dia')),
            'status' => $request->request->getString('status', ClinicAgendamento::STATUS_MARCADO),
        ];
    }

    /** @return array<string, mixed> */
    private function formToPrefill(Request $request): array
    {
        return [
            'paciente_id' => RequestInts::positiveOrNull($request->request->get('paciente_id')),
            'medico_id' => RequestInts::positiveOrNull($request->request->get('medico_id')),
            'inicio' => $request->request->getString('inicio'),
            'fim' => $request->request->getString('fim'),
            'titulo' => $request->request->getString('titulo'),
            'observacao' => $request->request->getString('observacao'),
            'origem' => $request->request->getString('origem', ClinicAgendamento::ORIGEM_MANUAL),
            'protocolo_dia' => RequestInts::positiveOrNull($request->request->get('protocolo_dia')),
            'status' => $request->request->getString('status', ClinicAgendamento::STATUS_MARCADO),
        ];
    }

    private function requireEmpresa(): Empresa
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if ($empresa === null) {
            throw $this->createAccessDeniedException('Área de trabalho indisponível.');
        }

        return $empresa;
    }
}
