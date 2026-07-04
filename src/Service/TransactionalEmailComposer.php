<?php

namespace App\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Rodapé legal e links LGPD em e-mails transacionais.
 */
final class TransactionalEmailComposer
{
    public function __construct(
        private Environment $twig,
        private PlatformConfigService $platformConfig,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function appendPlainFooter(string $body): string
    {
        $footer = trim($this->twig->render('emails/_legal_footer_text.html.twig', $this->footerContext()));

        return rtrim($body) . "\n\n" . $footer . "\n";
    }

    public function renderHtmlFooter(): string
    {
        return $this->twig->render('emails/_legal_footer.html.twig', $this->footerContext());
    }

    /** @return array<string, mixed> */
    private function footerContext(): array
    {
        return [
            'platform_config' => $this->platformConfig->all(),
            'termos_url' => $this->urlGenerator->generate('app_legal_termos', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'privacidade_url' => $this->urlGenerator->generate('app_legal_privacidade', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'lgpd_url' => $this->urlGenerator->generate('app_legal_lgpd', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }
}
