<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InquiryReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public Lead $lead, public bool $confirmation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->confirmation ? 'Codenyr — Votre demande a bien été reçue' : 'Codenyr — Nouvelle demande de '.$this->lead->firstname.' '.$this->lead->lastname,
            replyTo: [new Address($this->confirmation ? config('codenyr.email') : $this->lead->email)],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.inquiry');
    }
}
