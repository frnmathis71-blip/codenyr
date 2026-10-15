<div>
    <p class="eyebrow">Codenyr / Organisation</p>
    <div class="dash-head">
        <div>
            <h1>À faire</h1>
            <p>Les démarches pour lancer l’activité et mettre le site en ligne. {{ $done->count() }} sur {{ $total }} terminée(s).</p>
        </div>
    </div>
    @if($total)<progress class="tasks-progress" max="{{ $total }}" value="{{ $done->count() }}" aria-label="Avancement : {{ $done->count() }} tâche(s) terminée(s) sur {{ $total }}"></progress>@endif

    @forelse($open as $category => $tasks)
        <section class="admin-panel tasks-group" aria-labelledby="tasks-{{ \Illuminate\Support\Str::slug($category) }}">
            <h2 id="tasks-{{ \Illuminate\Support\Str::slug($category) }}">{{ $category }} <small>{{ $tasks->count() }}</small></h2>
            <ul class="tasks-list">
                @foreach($tasks as $task)
                    <li wire:key="task-{{ $task->id }}">
                        <input type="checkbox" id="task-{{ $task->id }}" wire:click="toggle({{ $task->id }})">
                        <div>
                            <label for="task-{{ $task->id }}">{{ $task->title }}</label>
                            @if($task->details)<p>{{ $task->details }}</p>@endif
                            @if($task->auto_check)<small class="tasks-auto">Se coche automatiquement</small>@endif
                        </div>
                        <button type="button" class="tasks-delete" wire:click="delete({{ $task->id }})" wire:confirm="Supprimer cette tâche ?" aria-label="Supprimer « {{ $task->title }} »"><x-ui-icon name="close" /></button>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <section class="admin-panel"><p class="dash-empty">Tout est fait. Bravo !</p></section>
    @endforelse

    <section class="admin-panel">
        <h2>Ajouter une tâche</h2>
        <form wire:submit="add" class="form-grid">
            <div class="field"><label for="task-title">Tâche</label><input id="task-title" wire:model="title" maxlength="200" required>@error('title')<span class="error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="task-category">Catégorie</label><select id="task-category" wire:model="category">@foreach(\App\Models\AdminTask::CATEGORIES as $option)<option>{{ $option }}</option>@endforeach</select></div>
            <div class="field full"><label for="task-details">Détails (facultatif)</label><textarea id="task-details" wire:model="details" rows="2" maxlength="2000"></textarea></div>
            <div class="field full"><button class="button button-primary" wire:loading.attr="disabled">Ajouter</button></div>
        </form>
    </section>

    @if($done->isNotEmpty())
        <details class="admin-panel accounting-fold" wire:ignore.self>
            <summary>Terminées <small>{{ $done->count() }}</small></summary>
            <ul class="tasks-list is-done">
                @foreach($done as $task)
                    <li wire:key="done-{{ $task->id }}">
                        <input type="checkbox" id="done-{{ $task->id }}" checked wire:click="toggle({{ $task->id }})" @disabled($task->autoDone())>
                        <div><label for="done-{{ $task->id }}">{{ $task->title }}</label><small>{{ $task->autoDone() ? 'Validée automatiquement' : 'Terminée le '.$task->done_at?->format('d/m/Y') }}</small></div>
                        <button type="button" class="tasks-delete" wire:click="delete({{ $task->id }})" wire:confirm="Supprimer cette tâche ?" aria-label="Supprimer « {{ $task->title }} »"><x-ui-icon name="close" /></button>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</div>
