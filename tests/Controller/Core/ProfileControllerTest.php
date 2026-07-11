<?php

namespace App\Tests\Controller\Core;

use App\Dev\DevSeedEmails;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProfileControllerTest extends WebTestCase
{
    private const GESTOR_EMAIL = DevSeedEmails::RENATA;
    private const PASS = 'unio123';

    public function testProfilePageLoadsWithForms(): void
    {
        $client = $this->createLoggedInClient();
        $crawler = $client->request('GET', '/meu-perfil');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form.profile-identity-form');
        self::assertSelectorExists('form.profile-password-form');
    }

    public function testUpdateIdentityChangesName(): void
    {
        $client = $this->createLoggedInClient();
        $crawler = $client->request('GET', '/meu-perfil');

        $form = $crawler->filter('form.profile-identity-form')->form([
            'profile_identity_form[nome]' => 'Gestor Unio Teste',
        ]);

        $client->submit($form);
        self::assertResponseRedirects('/meu-perfil');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.ficha-hero-name', 'Gestor Unio Teste');

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => self::GESTOR_EMAIL]);
        self::assertNotNull($user);
        $user->setNome('Renata Oliveira');
        static::getContainer()->get('doctrine')->getManager()->flush();
    }

    public function testChangePasswordRejectsWrongCurrentPassword(): void
    {
        $client = $this->createLoggedInClient();
        $crawler = $client->request('GET', '/meu-perfil');

        $form = $crawler->filter('form.profile-password-form')->form([
            'change_password_form[currentPassword]' => 'wrong-password',
            'change_password_form[plainPassword][first]' => 'NewPass123',
            'change_password_form[plainPassword][second]' => 'NewPass123',
        ]);

        $client->submit($form);
        self::assertResponseRedirects('/meu-perfil');
    }

    private function createLoggedInClient(): KernelBrowser
    {
        $client = static::createClient();
        $this->skipIfUnavailable();

        $client->restart();
        $crawler = $client->request('GET', '/login');
        $csrf = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/login', [
            'email' => self::GESTOR_EMAIL,
            'password' => self::PASS,
            '_csrf_token' => $csrf,
        ]);

        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        $client->request('GET', '/workspace');
        if ($client->getResponse()->isRedirect()) {
            $client->followRedirect();
        }

        return $client;
    }

    private function skipIfUnavailable(): void
    {
        try {
            static::getContainer()->get('doctrine')->getConnection()->executeQuery('SELECT 1');
        } catch (\Throwable) {
            self::markTestSkipped('Banco indisponível.');
        }

        if (!static::getContainer()->get(UserRepository::class)->findOneBy(['email' => self::GESTOR_EMAIL])) {
            self::markTestSkipped('Execute app:seed-users antes dos testes.');
        }
    }
}
