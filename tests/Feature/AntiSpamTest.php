<?php

use App\Livewire\InquiryForm;
use App\Models\Lead;
use App\Models\User;
use App\Support\SpamGuard;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function validInquiry(): Testable
{
    return Livewire::test(InquiryForm::class)
        ->set('firstname', 'Claire')->set('lastname', 'Martin')->set('email', 'claire@example.test')
        ->set('project_type', 'Site Pro')->set('description', 'Une demande de site valide et détaillée.')->set('consent', true);
}

test('a form sent instantly is refused, then accepted on a second click', function () {
    Mail::fake();
    config(['codenyr.spam.min_seconds' => 4]);
    $form = validInquiry()->call('submit')->assertHasErrors('submit');
    expect(Lead::count())->toBe(0);
    $form->call('submit')->assertHasNoErrors();
    expect(Lead::count())->toBe(1);
});

test('a form filled at a human pace goes through', function () {
    Mail::fake();
    config(['codenyr.spam.min_seconds' => 4]);
    $form = validInquiry();
    $this->travel(10)->seconds();
    $form->call('submit')->assertHasNoErrors();
    expect(Lead::count())->toBe(1);
});

test('throwaway mailboxes and links in names are refused', function () {
    Mail::fake();
    validInquiry()->set('email', 'bot@yopmail.com')->call('submit')->assertHasErrors('email');
    validInquiry()->set('lastname', 'Buy now https://spam.example')->call('submit')->assertHasErrors('submit');
    expect(Lead::count())->toBe(0);
    Mail::assertNothingQueued();
});

test('one address cannot send more than three requests an hour', function () {
    Mail::fake();
    foreach (range(1, 3) as $i) {
        validInquiry()->call('submit')->assertHasNoErrors();
    }
    validInquiry()->call('submit')->assertHasErrors('submit');
    expect(Lead::count())->toBe(3);
});

test('registration refuses the honeypot, instant submissions and throwaway mailboxes', function () {
    $user = ['name' => 'Claire Martin', 'email' => 'claire@example.test', 'password' => 'Mot-de-passe-solide-2026', 'password_confirmation' => 'Mot-de-passe-solide-2026'];
    $this->post(route('register.store'), [...$user, 'fax' => 'spam'])->assertSessionHasErrors('email');
    $this->post(route('register.store'), [...$user, 'email' => 'bot@mailinator.com'])->assertSessionHasErrors('email');
    config(['codenyr.spam.min_seconds' => 4]);
    $this->post(route('register.store'), [...$user, 'started' => SpamGuard::startToken()])->assertSessionHasErrors('email');
    $this->post(route('register.store'), [...$user, 'started' => 'forged'])->assertSessionHasErrors('email');
    expect(User::count())->toBe(0);
    $this->get(route('register'))->assertSee('name="started"', false)->assertSee('name="fax"', false);
});

test('AI training crawlers and scraping tools are turned away', function () {
    $this->get('/', ['User-Agent' => 'Mozilla/5.0 (compatible; GPTBot/1.2; +https://openai.com/gptbot)'])->assertForbidden();
    $this->get('/services', ['User-Agent' => 'python-requests/2.32'])->assertForbidden();
    $this->get('/', ['User-Agent' => ''])->assertForbidden();
    $this->get('/', ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0 Safari/537.36'])->assertOk();
    $this->get('/', ['User-Agent' => 'Mozilla/5.0 (compatible; OAI-SearchBot/1.0)'])->assertOk();
    $this->get('/robots.txt', ['User-Agent' => 'GPTBot'])->assertOk()->assertSee("User-agent: GPTBot\nDisallow: /", false)->assertSee('Sitemap:');
});

test('the Brevo mailer uses its SMTP relay', function () {
    expect(config('mail.mailers.brevo'))->toMatchArray(['transport' => 'smtp', 'host' => 'smtp-relay.brevo.com', 'port' => 587]);
});

test('the privacy policy names Brevo once it sends the e-mails', function () {
    $this->get('/politique-confidentialite')->assertDontSee('Brevo');
    config(['mail.default' => 'brevo']);
    $this->get('/politique-confidentialite')->assertSee('Brevo (Sendinblue SAS, Paris)');
});
