<?php

namespace App\Livewire;

use App\Mail\InquiryReceived;
use App\Models\Lead;
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
        $key = 'inquiry:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('submit', 'Trop de tentatives. Merci de réessayer dans quelques minutes.');

            return;
        }
        RateLimiter::hit($key, 600);
        // Same visitor, all day long: at most 15 requests, whatever the pace.
        $dailyKey = 'inquiry-day:'.request()->ip();
        if (RateLimiter::tooManyAttempts($dailyKey, 15)) {
            $this->addError('submit', 'Trop de demandes envoyées aujourd’hui. Écrivez-nous directement à '.config('codenyr.email').'.');

            return;
        }
        RateLimiter::hit($dailyKey, 86400);
        if ($this->fax !== '') {
            $this->addError('submit', 'La demande n’a pas pu être envoyée.');

            return;
        }
        foreach (['firstname', 'lastname', 'company', 'email', 'phone', 'description', 'desired_date', 'website', 'subject'] as $field) {
            $this->{$field} = trim($this->{$field});
        }
        if (SpamGuard::tooFast($this->startedAt)) {
            $this->addError('submit', 'Votre demande a été envoyée très rapidement. Vérifiez vos informations puis renvoyez-la.');
            // A real visitor who was just quick only has to click again.
            $this->startedAt = now()->timestamp - (int) config('codenyr.spam.min_seconds');

            return;
        }
        if (SpamGuard::suspiciousName($this->firstname) || SpamGuard::suspiciousName($this->lastname)) {
            $this->addError('submit', 'La demande n’a pas pu être envoyée.');

            return;
        }
        if (SpamGuard::disposableEmail($this->email)) {
            $this->addError('email', 'Merci d’utiliser une adresse e-mail durable : nous devons pouvoir vous répondre.');

            return;
        }
        $emailKey = 'inquiry-email:'.mb_strtolower($this->email);
        if (RateLimiter::tooManyAttempts($emailKey, 3)) {
            $this->addError('submit', 'Nous avons déjà bien reçu vos demandes. Nous vous répondons très vite.');

            return;
        }
        if (preg_match_all('~https?://~i', $this->description) > 5) {
            $this->addError('description', 'Votre message contient trop de liens. Décrivez votre projet en quelques mots.');

            return;
        }
        $data = $this->validate([
            'firstname' => 'required|string|max:100',
            'lastname' => 'required|string|max:100',
            'company' => 'nullable|string|max:200',
            'email' => 'required|email:rfc|max:254',
            'phone' => 'nullable|string|max:30',
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
            'website.url' => 'Saisissez une adresse commençant par https:// ou http://.',
            'max' => 'Ce champ dépasse la longueur autorisée.', 'in' => 'Sélectionnez une valeur proposée.',
        ]);
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

    public function render(): View
    {
        return view('livewire.inquiry-form')->layout('components.site-layout', [
            'title' => $this->mode === 'contact' ? 'Contactez Codenyr' : 'Demander un devis pour votre site internet',
            'description' => 'Parlez de votre projet web à Codenyr. Une demande simple, un devis personnalisé et un échange sans engagement.',
        ]);
    }
}
