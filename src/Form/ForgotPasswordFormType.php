<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class ForgotPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'mapped' => false,
            'attr' => [
                'placeholder' => 'seu@email.com',
                'autocomplete' => 'email',
                'autofocus'    => true,
            ],
            'constraints' => [
                new NotBlank(['message' => 'Informe seu e-mail.']),
                new Email(['message' => 'E-mail inválido.']),
            ],
        ]);
    }
}
