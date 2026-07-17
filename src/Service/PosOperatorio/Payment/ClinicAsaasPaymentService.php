<?php

namespace App\Service\PosOperatorio\Payment;

use App\Entity\ClinicConta;
use App\Entity\Empresa;
use App\Repository\ClinicContaRepository;
use App\Service\PosOperatorio\ClinicContaService;
use App\Service\PosOperatorio\ClinicIntegrationConfigService;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicAsaasPaymentService
{
    public const METHOD_PIX = 'pix';
    public const METHOD_LINK = 'link';

    public function __construct(
        private AsaasClient $asaas,
        private ClinicIntegrationConfigService $integrationConfig,
        private ClinicContaService $contas,
        private ClinicContaRepository $contaRepo,
        private EntityManagerInterface $em,
    ) {}

    public function isReady(Empresa $empresa): bool
    {
        return $this->integrationConfig->asaasConfigured($empresa);
    }

    /**
     * @return array{url: string, external_id: string, method: string}
     */
    public function createCharge(ClinicConta $conta, Empresa $empresa, string $method): array
    {
        $this->contas->assertScopePublic($conta, $empresa);

        if ($conta->getStatus() !== ClinicConta::STATUS_ABERTO) {
            throw new \InvalidArgumentException('Só é possível cobrar contas abertas.');
        }
        if ($conta->getTipo() !== ClinicConta::TIPO_PARTICULAR) {
            throw new \InvalidArgumentException('Cobrança Asaas disponível apenas para contas particulares.');
        }

        $valor = (int) ($conta->getValorCentavos() ?? 0);
        if ($valor < 500) {
            throw new \InvalidArgumentException('Valor mínimo para cobrança: R$ 5,00.');
        }

        if ($conta->getPaymentUrl()
            && $conta->getPaymentExternalId()
            && $conta->getPaymentStatus() === 'pending'
            && $conta->getPaymentMethod() === $method
        ) {
            return [
                'url' => (string) $conta->getPaymentUrl(),
                'external_id' => (string) $conta->getPaymentExternalId(),
                'method' => $method,
            ];
        }

        $cfg = $this->integrationConfig->asaasConfig($empresa);
        if (!$cfg['asaas_enabled'] || $cfg['asaas_api_key'] === '') {
            throw new \InvalidArgumentException('Asaas não configurado. Configure em Integrações.');
        }

        $paciente = $conta->getPaciente();
        $cpf = preg_replace('/\D+/', '', (string) ($paciente->getCpf() ?? '')) ?? '';
        if (strlen($cpf) < 11) {
            throw new \InvalidArgumentException('Paciente precisa de CPF válido para gerar cobrança Asaas.');
        }

        $customerPayload = [
            'name' => $paciente->getNome(),
            'cpfCnpj' => $cpf,
            'externalReference' => 'paciente-'.(string) $paciente->getId(),
        ];
        if ($paciente->getEmailContato()) {
            $customerPayload['email'] = $paciente->getEmailContato();
        }
        $phone = preg_replace('/\D+/', '', (string) ($paciente->getTelefoneContato() ?? '')) ?? '';
        if (strlen($phone) >= 10) {
            $customerPayload['mobilePhone'] = $phone;
        }

        $customer = $this->asaas->createCustomer($cfg['asaas_api_key'], $cfg['asaas_env'], $customerPayload);

        $customerId = (string) ($customer['id'] ?? '');
        if ($customerId === '') {
            throw new \RuntimeException('Asaas não retornou o cliente.');
        }

        $billingType = $method === self::METHOD_PIX ? 'PIX' : 'UNDEFINED';
        $payment = $this->asaas->createPayment($cfg['asaas_api_key'], $cfg['asaas_env'], [
            'customer' => $customerId,
            'billingType' => $billingType,
            'value' => round($valor / 100, 2),
            'dueDate' => (new \DateTimeImmutable('+3 days'))->format('Y-m-d'),
            'description' => $conta->getDescricao() ?: ('Conta clínica #'.$conta->getId()),
            'externalReference' => 'clinic_conta_'.$conta->getId(),
        ]);

        $paymentId = (string) ($payment['id'] ?? '');
        $url = (string) ($payment['invoiceUrl'] ?? $payment['bankSlipUrl'] ?? $payment['transactionReceiptUrl'] ?? '');
        if ($paymentId === '' || $url === '') {
            throw new \RuntimeException('Asaas não retornou link de pagamento.');
        }

        $conta->setPaymentProvider('asaas');
        $conta->setPaymentExternalId($paymentId);
        $conta->setPaymentUrl($url);
        $conta->setPaymentMethod($method);
        $conta->setPaymentStatus('pending');
        $conta->touch();
        $this->em->flush();

        return [
            'url' => $url,
            'external_id' => $paymentId,
            'method' => $method,
        ];
    }

    public function markPaidFromWebhook(string $paymentId, ?float $value = null): bool
    {
        $conta = $this->contaRepo->findOneBy([
            'paymentProvider' => 'asaas',
            'paymentExternalId' => $paymentId,
        ]);
        if (!$conta instanceof ClinicConta) {
            return false;
        }
        if ($conta->getStatus() === ClinicConta::STATUS_PAGO) {
            $conta->setPaymentStatus('received');
            $conta->touch();
            $this->em->flush();

            return true;
        }

        $valorCentavos = null;
        if ($value !== null && $value > 0) {
            $valorCentavos = (int) round($value * 100);
        }

        $this->contas->markPago($conta, $conta->getEmpresa(), $valorCentavos);
        $conta->setPaymentStatus('received');
        $conta->touch();
        $this->em->flush();

        return true;
    }
}
