<?php

namespace App\Livewire;

use App\Mail\InquiryReceived;
use App\Models\Lead;
use App\Support\Recaptcha;
use App\Support\SpamGuard;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class InquiryForm extends Component
{
    #[Locked]
    public string $mode = 'quote';

    #[Locked]
    public bool $submitted = false;

    public string $firstname = '';

    public string $lastname = '';

    public string $company = '';

    public string $email = '';

    public string $phone = '';

    public string $project_type = '';

    public string $budget = '';

    /** @var array<int, string> */
    public array $features = [];

    public string $description = '';

    public string $desired_date = '';

    public string $website = '';

    public string $subject = '';

    public string $fax = '';

    public bool $consent = false;

    /** reCAPTCHA v3 token, requested by resources/js/recaptcha.js right before each submission. */
    public string $captcha = '';

    /** reCAPTCHA v2 token, once the visitor ticked the fallback checkbox. */
    public string $captchaChallenge = '';

    #[Locked]
    public int $startedAt = 0;

    public function mount(string $mode = 'quote'): void
    {
        $this->startedAt = now()->timestamp;
        $this->mode = $mode === 'contact' ? 'contact' : 'quote';
        $offer = request()->query('offer');
        if (is_string($offer) && in_array($offer, array_column(config('codenyr.offers'), 'name'), true)) {
            $this->project_type = $offer;
        }
        // Configuration chosen in a demo's customisation panel, prefilled as an editable starting point.
        $design = request()->query('design');
        if ($this->mode === 'quote' && is_string($design) && trim($design) !== '') {
            $this->description = "J’ai personnalisé une démonstration :\n".mb_substr(strip_tags(trim($design)), 0, 1500)."\n\n";
        }
    }

    public function submit(): void
    {
        if ($this->submitted) {
            return;
        }
        $form = $this->formName();
        if (SpamGuard::throttled('inquiry')) {
            SpamGuard::tooManyRequests($form);
        }
        // Same visitor, all day long: at most 15 requests, whatever the pace.
        $dailyKey = 'inquiry-day:'.request()->ip();
        if (RateLimiter::tooManyAttempts($dailyKey, 15)) {
            SpamGuard::tooManyRequests($form, 'daily_limit');
        }
        RateLimiter::hit($dailyKey, 86400);
        if ($this->fax !== '') {
            // Silent refusal: the bot sees the same confirmation as a visitor, nothing is stored or sent.
            SpamGuard::reject($form, 'honeypot');
            $this->submitted = true;

            return;
        }
        foreach (['firstname', 'lastname', 'company', 'email', 'phone', 'desired_date', 'website', 'subject'] as $field) {
            $this->{$field} = SpamGuard::cleanLine($this->{$field});
        }
        $this->description = SpamGuard::cleanText($this->description);
        if (SpamGuard::tooFast($this->startedAt)) {
            $this->refuse('too_fast');
            // A real visitor who was just quick only has to click again.
            $this->startedAt = now()->timestamp - (int) config('codenyr.spam.min_seconds');

            return;
        }
        if (SpamGuard::suspiciousName($this->firstname) || SpamGuard::suspiciousName($this->lastname)) {
            $this->refuse('suspicious_name');

            return;
        }
        if (SpamGuard::disposableEmail($this->email)) {
            SpamGuard::reject($form, 'disposable_email');
            $this->addError('email', 'Merci d’utiliser une adresse e-mail durable : nous devons pouvoir vous répondre.');

            return;
        }
        $emailKey = 'inquiry-email:'.mb_strtolower($this->email);
        if (RateLimiter::tooManyAttempts($emailKey, 3)) {
            SpamGuard::reject($form, 'email_limit');
            $this->addError('submit', 'Nous avons déjà bien reçu vos demandes. Nous vous répondons très vite.');

            return;
        }
        if (SpamGuard::tooManyLinks($this->description)) {
            $this->refuse('too_many_links');

            return;
        }
        if (SpamGuard::spamKeyword($this->subject, $this->description, $this->company)) {
            $this->refuse('spam_keyword');

            return;
        }
        $data = $this->validate([
            'firstname' => 'required|string|max:100',
            'lastname' => 'required|string|max:100',
            'company' => 'nullable|string|max:200',
            'email' => 'required|email:rfc|max:254',
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 .()\-]{5,}$/'],
            'project_type' => $this->mode === 'quote' ? ['required', Rule::in([...array_column(config('codenyr.offers'), 'name'), 'Je ne sais pas'])] : ['nullable'],
            // Older open forms may still submit these fields; never persist them.
            'budget' => 'exclude',
            'features' => 'exclude',
            'description' => 'required|string|min:20|max:10000',
            'desired_date' => 'nullable|string|max:150',
            'website' => 'nullable|url:http,https|max:255',
            'subject' => $this->mode === 'contact' ? 'required|string|max:200' : 'nullable|string|max:200',
            'consent' => 'accepted',
        ], [
            'required' => 'Ce champ est obligatoire.', 'email' => 'Saisissez une adresse e-mail valide.',
            'description.min' => 'Décrivez votre demande en au moins 20 caractères.',
            'consent.accepted' => 'Merci de prendre connaissance de la politique de confidentialité.',
            'phone.regex' => 'Saisissez un numéro de téléphone valide.',
            'website.url' => 'Saisissez une adresse commençant par https:// ou http://.',
            'max' => 'Ce champ dépasse la longueur autorisée.', 'in' => 'Sélectionnez une valeur proposée.',
        ]);
        // Last check, so the single-use reCAPTCHA token is only spent on an otherwise valid request.
        $captcha = Recaptcha::assess($form, $this->captcha, $this->captchaChallenge);
        $this->reset('captcha', 'captchaChallenge');
        if ($captcha['verdict'] === Recaptcha::CHALLENGE) {
            SpamGuard::reject($form, (string) $captcha['reason'], $captcha['score']);
            $this->addError('submit', 'Une dernière vérification : cochez la case « Je ne suis pas un robot », puis renvoyez votre demande.');
            $this->dispatch('recaptcha-challenge');

            return;
        }
        if ($captcha['verdict'] === Recaptcha::REJECT) {
            $this->refuse((string) $captcha['reason'], $captcha['score']);
            $this->dispatch('recaptcha-reset');

            return;
        }
        unset($data['consent'], $data['subject']);
        if ($this->mode === 'contact') {
            $data['project_type'] = 'Contact';
            $data['description'] = 'Sujet : '.$this->subject."\n\n".$this->description;
        }
        RateLimiter::hit($emailKey, 3600);
        DB::transaction(function () use ($data): void {
            $lead = Lead::create($data);
            Mail::to(config('codenyr.email'))->queue((new InquiryReceived($lead, false))->afterCommit());
            Mail::to($lead->email)->queue((new InquiryReceived($lead, true))->afterCommit());
        });
        // Shared hosting has no queue worker and its cron runs hourly: send right after the response.
        // Failed attempts stay queued and the scheduled worker retries them.
        if (config('queue.default') === 'database') {
            defer(fn () => Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 20, '--tries' => 3, '--memory' => 512]));
        }
        $this->submitted = true;
        $this->reset('firstname', 'lastname', 'company', 'email', 'phone', 'description', 'website', 'features', 'subject');
    }

    /** Also the reCAPTCHA action of the form. */
    private function formName(): string
    {
        return $this->mode === 'contact' ? 'contact' : 'devis';
    }

    private function refuse(string $reason, ?float $score = null): void
    {
        SpamGuard::reject($this->formName(), $reason, $score);
        $this->addError('submit', SpamGuard::message());
    }

    public function render(): View
    {
        return view('livewire.inquiry-form', ['captchaAction' => $this->formName()])->layout('components.site-layout', [
            'title' => $this->mode === 'contact' ? 'Contactez Codenyr' : 'Demander un devis pour votre site internet',
            'description' => 'Parlez de votre projet web à Codenyr. Une demande simple, un devis personnalisé et un échange sans engagement.',
        ]);
    }
}
