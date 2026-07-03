<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProfileIdentityFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nome', TextType::class, [
                'attr' => [
                    'class' => 'unio-form-control',
                    'autocomplete' => 'name',
                    'maxlength' => 100,
                ],
                'constraints' => [
                    new NotBlank(message: 'Informe seu nome.'),
                    new Length(min: 2, minMessage: 'Nome muito curto.', max: 100),
                ],
            ])
            ->add('avatarFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'unio-form-control',
                    'accept' => 'image/jpeg,image/png,image/webp',
                ],
                'constraints' => [
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Use JPG, PNG ou WebP (até 2 MB).',
                    ),
                ],
            ])
            ->add('removeAvatar', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Remover foto atual',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
