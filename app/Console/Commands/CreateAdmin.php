<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'codenyr:admin';

    protected $description = 'Créer un administrateur Codenyr sans inscription publique';

    public function handle(): int
    {
        $data = ['name' => $this->ask('Nom'), 'email' => $this->ask('E-mail'), 'password' => $this->secret('Mot de passe (12 caractères minimum)')];
        $validator = Validator::make($data, ['name' => 'required|string|max:255', 'email' => 'required|email|unique:users', 'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $user = new User($data);
        $user->is_admin = true;
        $user->email_verified_at = now();
        $user->save();
        $this->info('Administrateur créé. Connexion : /login');

        return self::SUCCESS;
    }
}
