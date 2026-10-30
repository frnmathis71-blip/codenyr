<?php

namespace App\Livewire\Admin;

use App\Models\Outreach;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

class Outreaches extends AdminComponent
{
    private const FIELDS = ['name', 'activity', 'address', 'phone', 'offer', 'status', 'channel', 'last_contact_on', 'next_action', 'next_action_on', 'notes'];

    public string $search = '';

    #[Url]
    public string $filter = '';

    /** Null while adding a new business, its id while editing one. */
    #[Locked]
    public ?int $editing = null;

    public bool $showForm = false;

    public string $name = '';

    public string $activity = '';

    public string $address = '';

    public string $phone = '';

    public string $offer = '';

    public string $status = 'todo';

    public string $channel = '';

    public string $last_contact_on = '';

    public string $next_action = '';

    public string $next_action_on = '';

    public string $notes = '';

    public function create(): void
    {
        $this->reset(...self::FIELDS);
        $this->editing = null;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $outreach = Outreach::findOrFail($id);
        foreach (self::FIELDS as $field) {
            $value = $outreach->{$field};
            $this->{$field} = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
        }
        $this->editing = $outreach->id;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function close(): void
    {
        $this->showForm = false;
        $this->editing = null;
    }

    public function updatedStatus(): void
    {
        // Changing the outcome means a contact just happened.
        if ($this->status !== 'todo' && $this->last_contact_on === '') {
            $this->last_contact_on = today()->format('Y-m-d');
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => 'required|string|max:160',
            'activity' => 'nullable|string|max:160',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:40',
            'offer' => ['nullable', Rule::in(array_keys(config('codenyr.offers')))],
            'status' => ['required', Rule::in(array_keys(Outreach::STATUSES))],
            'channel' => ['nullable', Rule::in(array_keys(Outreach::CHANNELS))],
            'last_contact_on' => 'nullable|date',
            'next_action' => 'nullable|string|max:200',
            'next_action_on' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
        ]);
        $data = array_map(fn ($value) => $value === '' ? null : $value, $data);

        $this->editing ? Outreach::findOrFail($this->editing)->update($data) : Outreach::create($data);
        session()->flash('success', $this->editing ? 'Fiche mise à jour.' : 'Entreprise ajoutée.');
        $this->close();
    }

    public function delete(int $id): void
    {
        Outreach::findOrFail($id)->delete();
        if ($this->editing === $id) {
            $this->close();
        }
    }

    public function render(): View
    {
        $outreaches = Outreach::query()
            ->when($this->search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('activity', 'like', '%'.$this->search.'%')
                ->orWhere('address', 'like', '%'.$this->search.'%')))
            ->when($this->filter === 'due', fn ($query) => $query->due())
            ->when(array_key_exists($this->filter, Outreach::STATUSES), fn ($query) => $query->where('status', $this->filter))
            // Closed businesses sink to the bottom; follow-ups come first, by date.
            ->orderByRaw("CASE WHEN status IN ('won', 'refused') THEN 1 ELSE 0 END")
            ->orderByRaw('CASE WHEN next_action_on IS NULL THEN 1 ELSE 0 END')
            ->orderBy('next_action_on')
            ->orderBy('name')
            ->get();
        $counts = collect(Outreach::STATUSES)->map(fn () => 0)
            ->merge(Outreach::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'));

        return view('livewire.admin.outreaches', [
            'outreaches' => $outreaches,
            'counts' => $counts,
            'total' => $counts->sum(),
            'due' => Outreach::due()->count(),
        ])->layout('components.admin-layout', ['title' => 'Démarchage']);
    }
}
