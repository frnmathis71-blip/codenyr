<?php

use App\Livewire\InquiryForm;
use App\Models\Lead;
use App\Models\User;
use App\Support\SpamGuard;
use Illuminate\Support\Facades\Http;
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

function recaptchaKeys(bool $withChallenge = true): void
{
    config(['services.recaptcha.site_key' => 'site-v3', 'services.recaptcha.secret_key' => 'secret-v3']);
    if ($withChallenge) {
        config(['services.recaptcha.v2_site_key' => 'site-v2', 'services.recaptcha.v2_secret_key' => 'secret-v2']);
    }
}

/** @param array<string, mixed> $answer */
function googleAnswers(array $answer): void
{
    Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response($answer)]);
}

test('a filled honeypot is refused silently: same confirmation, nothing stored or sent', function () {
    Mail::fake();
    validInquiry()->set('fax', 'https://spam.example')->call('submit')->assertHasNoErrors()->assertSet('submitted', true);
    expect(Lead::count())->toBe(0);
    Mail::assertNothingQueued();
});

test('the sixth submission from one address in ten minutes gets HTTP 429', function () {
    Mail::fake();
    foreach (range(1, 5) as $i) {
        validInquiry()->set('email', "claire$i@example.test")->call('submit')->assertHasNoErrors();
    }
    validInquiry()->set('email', 'claire6@example.test')->call('submit')->assertStatus(429);
    expect(Lead::count())->toBe(5);

    $user = ['name' => 'Claire Martin', 'password' => 'Mot-de-passe-solide-2026', 'password_confirmation' => 'Mot-de-passe-solide-2026'];
    foreach (range(1, 5) as $i) {
        $this->post(route('register.store'), [...$user, 'email' => "claire$i@example.test"]);
        auth()->logout();
    }
    $this->post(route('register.store'), [...$user, 'email' => 'claire6@example.test'])->assertStatus(429);
    expect(User::count())->toBe(5);
});

test('messages stuffed with links or spam phrases are refused without saying why', function () {
    Mail::fake();
    $generic = SpamGuard::message();
    validInquiry()->set('description', 'Voyez https://a.example puis https://b.example et enfin www.c.example pour comprendre.')
        ->call('submit')->assertHasErrors('submit')->assertSee($generic)->assertDontSee('liens');
    validInquiry()->set('description', 'We sell cheap BACKLINKS for your website, best price guaranteed.')
        ->call('submit')->assertHasErrors('submit')->assertSee($generic);
    config(['codenyr.spam.keywords' => ['offre exclusive']]);
    validInquiry()->set('description', 'Profitez de notre Offre Exclusive dès maintenant, sans attendre.')->call('submit')->assertHasErrors('submit');
    expect(Lead::count())->toBe(0);
    validInquiry()->set('description', 'Mon site actuel est https://a.example, un concurrent : https://b.example.')->call('submit')->assertHasNoErrors();
    expect(Lead::count())->toBe(1);
    Mail::assertQueuedCount(2);
});

test('entries are cleaned: no markup, no line break in one-line fields, valid phone number', function () {
    Mail::fake();
    validInquiry()->set('company', "Atelier <b>Martin</b>\r\nBcc: victime@example.test")
        ->set('description', "Bonjour,\r\n<script>alert(1)</script>je souhaite un site pour mon atelier.")
        ->set('phone', '06 12 34 56 78')->call('submit')->assertHasNoErrors();
    $lead = Lead::sole();
    expect($lead->company)->toBe('Atelier Martin Bcc: victime@example.test')
        ->and($lead->description)->toBe("Bonjour,\nalert(1)je souhaite un site pour mon atelier.");
    validInquiry()->set('email', 'autre@example.test')->set('phone', 'appelez-moi')->call('submit')->assertHasErrors('phone');
    validInquiry()->set('email', "autre@example.test\nBcc: victime@example.test")->call('submit')->assertHasErrors('email');
    expect(Lead::count())->toBe(1);
});

test('refused submissions are logged with an anonymised address, the reason and the score', function () {
    Mail::fake();
    recaptchaKeys(false);
    googleAnswers(['success' => true, 'action' => 'devis', 'score' => 0.1]);
    $journal = storage_path('framework/testing/spam-'.uniqid().'.log');
    config(['logging.channels.spam' => ['driver' => 'single', 'path' => $journal]]);
    validInquiry()->set('captcha', 'token')->call('submit')->assertHasErrors('submit');
    $line = (string) file_get_contents($journal);
    unlink($journal);
    expect($line)->toContain('Soumission rejetée {"form":"devis","reason":"captcha_low_score","score":0.1,"ip":"127.0.0.0"}')->not->toContain('127.0.0.1');
    expect(Lead::count())->toBe(0);
    Mail::assertNothingQueued();
});

test('without reCAPTCHA keys the forms work and nothing is loaded from Google', function () {
    Http::fake();
    Mail::fake();
    validInquiry()->call('submit')->assertHasNoErrors();
    Http::assertNothingSent();
    $this->get('/devis')->assertOk()->assertDontSee('data-recaptcha', false);
    $this->get(route('register'))->assertOk()->assertDontSee('data-recaptcha', false);
    $this->get('/politique-confidentialite')->assertDontSee('reCAPTCHA');
});

test('reCAPTCHA is only present on the pages that contain a form, each with its own action', function () {
    recaptchaKeys();
    $this->get('/devis')->assertSee('data-action="devis"', false)->assertSee('data-site-key="site-v3"', false)->assertSee('data-challenge-key="site-v2"', false)
        ->assertSee('Ce site est protégé par reCAPTCHA')->assertDontSee('secret-v3')->assertDontSee('google.com/recaptcha/api.js', false);
    $this->get('/contact')->assertSee('data-action="contact"', false);
    $this->get(route('register'))->assertSee('data-action="inscription"', false);
    foreach (['/', '/services', '/a-propos', '/login'] as $page) {
        $this->get($page)->assertOk()->assertDontSee('data-recaptcha', false)->assertDontSee('recaptcha.js', false);
    }
    $this->get('/politique-confidentialite')->assertSee('Google reCAPTCHA')->assertSee('Google Ireland Limited');
    $this->get('/')->assertSee('Un seul service facultatif est proposé : Google reCAPTCHA');
});

test('a good reCAPTCHA score lets the request through, verified with the secret key', function () {
    Mail::fake();
    recaptchaKeys();
    googleAnswers(['success' => true, 'action' => 'devis', 'score' => 0.9]);
    validInquiry()->set('captcha', 'token-v3')->call('submit')->assertHasNoErrors()->assertSet('submitted', true);
    Http::assertSent(fn ($request) => $request->url() === 'https://www.google.com/recaptcha/api/siteverify' && $request['secret'] === 'secret-v3' && $request['response'] === 'token-v3');
    expect(Lead::count())->toBe(1);
});

test('a missing, invalid, expired, mismatched or low-score token is refused', function (array $answer, string $token) {
    Mail::fake();
    recaptchaKeys(false);
    googleAnswers($answer);
    validInquiry()->set('captcha', $token)->call('submit')->assertHasErrors('submit')->assertSee(SpamGuard::message())->assertNotDispatched('recaptcha-challenge');
    expect(Lead::count())->toBe(0);
    Mail::assertNothingQueued();
})->with([
    'missing' => [['success' => true, 'action' => 'devis', 'score' => 0.9], ''],
    'invalid' => [['success' => false, 'error-codes' => ['invalid-input-response']], 'forged'],
    'expired' => [['success' => false, 'error-codes' => ['timeout-or-duplicate']], 'old'],
    'other action' => [['success' => true, 'action' => 'contact', 'score' => 0.9], 'token'],
    'low score' => [['success' => true, 'action' => 'devis', 'score' => 0.4], 'token'],
    'no score' => [['success' => true, 'action' => 'devis'], 'token'],
]);

test('the threshold is configurable', function () {
    Mail::fake();
    recaptchaKeys(false);
    config(['services.recaptcha.threshold' => 0.7]);
    googleAnswers(['success' => true, 'action' => 'contact', 'score' => 0.6]);
    $contact = fn () => Livewire::test(InquiryForm::class, ['mode' => 'contact'])
        ->set('firstname', 'Claire')->set('lastname', 'Martin')->set('email', 'claire@example.test')->set('subject', 'Une question')
        ->set('description', 'Une question précise sur vos prestations.')->set('consent', true)->set('captcha', 'token');
    $contact()->call('submit')->assertHasErrors('submit');
    config(['services.recaptcha.threshold' => 0.5]);
    $contact()->call('submit')->assertHasNoErrors();
    expect(Lead::count())->toBe(1);
});

test('a doubtful score asks for the checkbox instead of refusing, then accepts its answer', function () {
    Mail::fake();
    recaptchaKeys();
    Http::fake(['www.google.com/recaptcha/api/siteverify' => fn ($request) => Http::response(match ($request['secret']) {
        'secret-v3' => ['success' => true, 'action' => 'devis', 'score' => 0.4],
        default => ['success' => $request['response'] === 'ticked'],
    })]);
    $form = validInquiry()->set('captcha', 'token')->call('submit')->assertHasErrors('submit')->assertDispatched('recaptcha-challenge')->assertDontSee(SpamGuard::message());
    expect(Lead::count())->toBe(0);
    $form->set('captcha', 'token-2')->set('captchaChallenge', 'forged')->call('submit')->assertHasErrors('submit')->assertDispatched('recaptcha-reset');
    expect(Lead::count())->toBe(0);
    $form->set('captcha', 'token-3')->set('captchaChallenge', 'ticked')->call('submit')->assertHasNoErrors();
    expect(Lead::count())->toBe(1);
});

test('a score below the doubtful range is refused even when the checkbox is available', function () {
    Mail::fake();
    recaptchaKeys();
    googleAnswers(['success' => true, 'action' => 'devis', 'score' => 0.2]);
    validInquiry()->set('captcha', 'token')->call('submit')->assertHasErrors('submit')->assertNotDispatched('recaptcha-challenge');
    expect(Lead::count())->toBe(0);
});

test('the form stays usable when Google does not answer', function () {
    Mail::fake();
    recaptchaKeys();
    Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response('', 503)]);
    validInquiry()->set('captcha', 'token')->call('submit')->assertHasNoErrors();
    expect(Lead::count())->toBe(1);
});

test('registration checks reCAPTCHA with its own action and offers the checkbox on a doubtful score', function () {
    recaptchaKeys();
    $user = ['name' => 'Claire Martin', 'email' => 'claire@example.test', 'password' => 'Mot-de-passe-solide-2026', 'password_confirmation' => 'Mot-de-passe-solide-2026'];
    Http::fake(['www.google.com/recaptcha/api/siteverify' => fn ($request) => Http::response(match ($request['response']) {
        'human' => ['success' => true, 'action' => 'inscription', 'score' => 0.9],
        'doubtful' => ['success' => true, 'action' => 'inscription', 'score' => 0.4],
        'other-form' => ['success' => true, 'action' => 'contact', 'score' => 0.9],
        'ticked' => ['success' => true],
        default => ['success' => false, 'error-codes' => ['invalid-input-response']],
    })]);
    $this->post(route('register.store'), $user)->assertSessionHasErrors('email');
    $this->post(route('register.store'), [...$user, 'recaptcha_token' => 'other-form'])->assertSessionHasErrors('email');
    expect(User::count())->toBe(0);
    $this->from(route('register'))->post(route('register.store'), [...$user, 'recaptcha_token' => 'doubtful'])->assertSessionHasErrors('captcha');
    $this->get(route('register'))->assertSee('data-challenge ', false);
    expect(User::count())->toBe(0);
    $this->post(route('register.store'), [...$user, 'recaptcha_token' => 'doubtful', 'recaptcha_challenge' => 'ticked'])->assertSessionHasNoErrors();
    expect(User::count())->toBe(1);
    auth()->logout();
    $this->post(route('register.store'), [...$user, 'email' => 'paul@example.test', 'recaptcha_token' => 'human'])->assertSessionHasNoErrors();
    expect(User::count())->toBe(2);
});
