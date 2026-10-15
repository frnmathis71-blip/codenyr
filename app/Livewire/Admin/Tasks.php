<?php

namespace App\Livewire\Admin;

use App\Models\AdminTask;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class Tasks extends AdminComponent
{
    public string $title = '';

    public string $details = '';

    public string $category = 'Administratif';

    public function add(): void
    {
        $data = $this->validate([
            'title' => 'required|string|max:200',
            'details' => 'nullable|string|max:2000',
            'category' => ['required', Rule::in(AdminTask::CATEGORIES)],
        ]);
        AdminTask::create([...$data, 'details' => $data['details'] ?: null, 'position' => (int) AdminTask::max('position') + 1]);
        $this->reset('title', 'details');
    }

    public function toggle(int $id): void
    {
        $task = AdminTask::findOrFail($id);
        // A task completed by its automatic condition stays done until the condition changes.
        if ($task->autoDone()) {
            return;
        }
        $task->update(['done_at' => $task->done_at ? null : now()]);
    }

    public function delete(int $id): void
    {
        AdminTask::findOrFail($id)->delete();
    }

    public function render(): View
    {
        AdminTask::syncAutomatic();
        $tasks = AdminTask::orderBy('position')->get();

        return view('livewire.admin.tasks', [
            'open' => $tasks->reject->isDone()->groupBy('category'),
            'done' => $tasks->filter->isDone()->sortByDesc('done_at'),
            'total' => $tasks->count(),
        ])->layout('components.admin-layout', ['title' => 'À faire']);
    }
}
