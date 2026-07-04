<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class LgpdSolicitacaoFormType extends AbstractType
{
    public const TIPOS = [
        'Confirmação de tratamento / acesso aos dados' => 'acesso',
        'Correção de dados incompletos ou desatualizados' => 'correcao',
        'Eliminação ou anonimização' => 'exclusao',
        'Portabilidade dos dados' => 'portabilidade',
        'Revogação de consentimento' => 'revogacao',
        'Informação sobre compartilhamento' => 'compartilhamento',
        'Outro assunto LGPD' => 'outro',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nome', TextType::class, [
                'label' => 'Nome completo',
                'constraints' => [
                    new NotBlank(message: 'Informe seu nome.'),
                    new Length(max: 120),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail para retorno',
                'constraints' => [
                    new NotBlank(message: 'Informe seu e-mail.'),
                    new Email(message: 'E-mail inválido.'),
                ],
            ])
            ->add('tipo', ChoiceType::class, [
                'label' => 'Tipo de solicitação',
                'choices' => self::TIPOS,
                'constraints' => [new NotBlank(message: 'Selecione o tipo de solicitação.')],
            ])
            ->add('mensagem', TextareaType::class, [
                'label' => 'Descreva sua solicitação',
                'attr' => ['rows' => 5],
                'constraints' => [
                    new NotBlank(message: 'Descreva sua solicitação.'),
                    new Length(min: 10, minMessage: 'Descreva com mais detalhes (mín. 10 caracteres).', max: 4000),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'lgpd_solicitacao']);
    }
}
