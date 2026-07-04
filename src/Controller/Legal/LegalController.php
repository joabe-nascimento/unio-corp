<?php

namespace App\Controller\Legal;

use App\Form\LgpdSolicitacaoFormType;
use App\Service\LgpdRequestMailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LegalController extends AbstractController
{
    #[Route('/privacidade', name: 'app_legal_privacidade', methods: ['GET'])]
    public function privacidade(): Response
    {
        return $this->render('legal/privacidade.html.twig');
    }

    #[Route('/termos', name: 'app_legal_termos', methods: ['GET'])]
    public function termos(): Response
    {
        return $this->render('legal/termos.html.twig');
    }

    #[Route('/lgpd', name: 'app_legal_lgpd', methods: ['GET', 'POST'])]
    public function lgpd(Request $request, LgpdRequestMailer $lgpdMailer): Response
    {
        $form = $this->createForm(LgpdSolicitacaoFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{nome: string, email: string, tipo: string, mensagem: string} $data */
            $data = $form->getData();

            try {
                $lgpdMailer->send($data);
                $this->addFlash('lgpd_success', 'Solicitação enviada. Responderemos no e-mail informado em prazo razoável, conforme a LGPD.');
            } catch (\Throwable) {
                $this->addFlash('lgpd_error', 'Não foi possível enviar agora. Tente o e-mail de contato indicado na Política de Privacidade.');
            }

            return $this->redirectToRoute('app_legal_lgpd');
        }

        return $this->render('legal/lgpd.html.twig', [
            'form' => $form,
        ]);
    }
}
