<?php

namespace App\Service\PosOperatorio\Whatsapp;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ClinicWhatsappSenderFactory
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $provider = '',
        private string $metaToken = '',
        private string $metaPhoneNumberId = '',
        private string $metaGraphVersion = 'v21.0',
        private string $templateAgenda = '',
        private string $templateQuestionario = '',
        private string $templateLang = 'pt_BR',
    ) {}

    public function create(): ClinicWhatsappSenderInterface
    {
        $provider = strtolower(trim($this->provider));
        $token = trim($this->metaToken);
        $phoneId = trim($this->metaPhoneNumberId);
        $version = trim($this->metaGraphVersion) !== '' ? trim($this->metaGraphVersion) : 'v21.0';

        if ($provider === 'meta' && $token !== '' && $phoneId !== '') {
            return new MetaCloudWhatsappSender(
                $this->httpClient,
                $token,
                $phoneId,
                $version,
                $this->logger,
                trim($this->templateAgenda),
                trim($this->templateQuestionario),
                trim($this->templateLang) !== '' ? trim($this->templateLang) : 'pt_BR',
            );
        }

        return new NoopWhatsappSender();
    }
}
