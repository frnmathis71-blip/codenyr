<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;

abstract class AdminComponent extends Component
{
    public function boot(): void
    {
        Gate::authorize('admin');
    }
}
