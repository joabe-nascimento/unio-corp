<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ResetPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'mapped'        => false,
            'type'          => PasswordType::class,
            'first_options' => [
                'attr' => ['placeholder' => '••••••••', 'autocomplete' => 'new-password', 'autofocus' => true],
            ],
            'second_options' => [
                'attr' => ['placeholder' => '••••••••', 'autocomplete' => 'new-password'],
            ],
            'invalid_message' => 'As senhas não conferem.',
            'constraints' => [
                new NotBlank(['message' => 'Informe uma senha.']),
                new Length([
                    'min'        => 6,
                    'minMessage' => 'A senha deve ter no mínimo {{ limit }} caracteres.',
                    'max'        => 72,
                ]),
                new Regex([
                    'pattern' => '/[A-Za-z]/',
                    'message' => 'A senha deve conter ao menos uma letra.',
                ]),
            ],
        ]);
    }
}
