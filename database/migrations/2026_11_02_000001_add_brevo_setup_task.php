<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('admin_tasks')->insert([
            'category' => 'Mise en ligne',
            'title' => 'Envoyer les e-mails avec Brevo',
            'details' => 'Créer le compte Brevo, authentifier le domaine codenyr.fr (enregistrements SPF, DKIM et DMARC à ajouter chez OVH), puis dans le .env de production : MAIL_MAILER=brevo, BREVO_SMTP_LOGIN et BREVO_SMTP_KEY (Brevo > SMTP & API > SMTP). Ajouter Brevo aux sous-traitants de la politique de confidentialité.',
            'position' => (int) DB::table('admin_tasks')->max('position') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('admin_tasks')->where('title', 'Envoyer les e-mails avec Brevo')->delete();
    }
};
