<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nome', TextType::class, [
                'attr' => ['placeholder' => 'Seu nome completo', 'autocomplete' => 'name'],
                'constraints' => [
                    new NotBlank(['message' => 'Informe seu nome.']),
                    new Length(['min' => 2, 'minMessage' => 'Nome muito curto.', 'max' => 100]),
                ],
            ])
            ->add('email', EmailType::class, [
                'attr' => ['placeholder' => 'seu@email.com', 'autocomplete' => 'email'],
                'constraints' => [
                    new NotBlank(['message' => 'Informe seu e-mail.']),
                    new Email(['message' => 'E-mail inválido.']),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type'          => PasswordType::class,
                'mapped'        => false,
                'first_options' => [
                    'attr' => ['placeholder' => '••••••••', 'autocomplete' => 'new-password'],
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
