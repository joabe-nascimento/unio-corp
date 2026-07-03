<?php

namespace App\Form;

use App\Service\PlatformConfigService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordFormType extends AbstractType
{
    public function __construct(private PlatformConfigService $platformConfig) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'mapped' => false,
                'attr' => [
                    'class' => 'unio-form-control',
                    'autocomplete' => 'current-password',
                ],
                'constraints' => [
                    new NotBlank(message: 'Informe sua senha atual.'),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'mapped' => false,
                'type' => PasswordType::class,
                'first_options' => [
                    'attr' => [
                        'class' => 'unio-form-control',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'second_options' => [
                    'attr' => [
                        'class' => 'unio-form-control',
                        'autocomplete' => 'new-password',
                    ],
                ],
                'invalid_message' => 'As senhas não conferem.',
                'constraints' => $this->platformConfig->getPasswordConstraints(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
