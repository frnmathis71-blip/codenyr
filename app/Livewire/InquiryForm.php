<?php

namespace App\Livewire;

use App\Mail\InquiryReceived;
use App\Models\Lead;
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

    public function mount(string $mode = 'quote'): void
    {
        $this->mode = $mode === 'contact' ? 'contact' : 'quote';
        $offer = request()->query('offer');
        if (is_string($offer) && in_array($offer, array_column(config('codenyr.offers'), 'name'), true)) {
            $this->project_type = $offer;
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
        if ($this->fax !== '') {
            $this->addError('submit', 'La demande n’a pas pu être envoyée.');

            return;
        }
        foreach (['firstname', 'lastname', 'company', 'email', 'phone', 'description', 'desired_date', 'website', 'subject'] as $field) {
            $this->{$field} = trim($this->{$field});
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
        DB::transaction(function () use ($data): void {
            $lead = Lead::create($data);
            Mail::to(config('codenyr.email'))->queue((new InquiryReceived($lead, false))->afterCommit());
            Mail::to($lead->email)->queue((new InquiryReceived($lead, true))->afterCommit());
        });
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
