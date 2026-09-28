<?php

namespace App\Livewire\Admin;

use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Projects extends AdminComponent
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    #[Locked]
    public ?int $editing = null;

    public bool $showForm = false;

    /** @var array<string, mixed> */
    public array $form = [];

    public ?TemporaryUploadedFile $image = null;

    /** @var array<int, TemporaryUploadedFile> */
    public array $screenshots = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset('editing', 'image', 'screenshots');
        $this->form = ['name' => '', 'slug' => '', 'client' => '', 'category' => '', 'short_description' => '', 'description' => '', 'problem' => '', 'solution' => '', 'features' => '', 'website_url' => '', 'technologies' => 'Laravel, Livewire, Tailwind CSS', 'published' => false, 'featured' => false, 'is_demo' => false];
        $this->showForm = true;
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $this->create();
        $project = Project::findOrFail($id);
        $this->editing = $project->id;
        $this->form = $project->only(array_keys($this->form));
        $this->form['technologies'] = implode(', ', $project->technologies ?? []);
    }

    public function save(): void
    {
        if (empty($this->form['slug'])) {
            $this->form['slug'] = Str::slug($this->form['name'] ?? '');
        }
        $data = $this->validate([
            'form.name' => 'required|string|max:200',
            'form.slug' => ['required', 'string', 'max:220', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('projects', 'slug')->ignore($this->editing)],
            'form.client' => 'required|string|max:200',
            'form.category' => 'required|string|max:100',
            'form.short_description' => 'required|string|max:500',
            'form.description' => 'required|string|max:20000',
            'form.problem' => 'required|string|max:10000',
            'form.solution' => 'required|string|max:10000',
            'form.features' => 'nullable|string|max:10000',
            'form.website_url' => 'nullable|url:http,https|max:255',
            'form.technologies' => 'nullable|string|max:1000',
            'form.published' => 'required|boolean',
            'form.featured' => 'required|boolean',
            'form.is_demo' => 'required|boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,avif|max:4096|dimensions:max_width=5000,max_height=5000',
            'screenshots' => 'array|max:6',
            'screenshots.*' => 'image|mimes:jpg,jpeg,png,webp,avif|max:4096|dimensions:max_width=5000,max_height=5000',
        ]);
        $attributes = $data['form'];
        $attributes['technologies'] = array_values(array_filter(array_map('trim', explode(',', $attributes['technologies'] ?? ''))));
        $attributes['website_url'] = $attributes['website_url'] ?: null;
        $project = $this->editing ? Project::findOrFail($this->editing) : new Project;
        $oldImage = $project->image;
        $oldScreenshots = $project->screenshots ?? [];
        if ($this->image) {
            $attributes['image'] = $this->image->store('projects', 'public');
        }
        if ($this->screenshots !== []) {
            $attributes['screenshots'] = array_map(fn ($image) => $image->store('projects', 'public'), $this->screenshots);
        }
        $project->fill($attributes)->save();
        if ($this->image && $oldImage) {
            Storage::disk('public')->delete($oldImage);
        }
        if ($this->screenshots !== []) {
            Storage::disk('public')->delete($oldScreenshots);
        }
        $this->showForm = false;
        $this->reset('image', 'screenshots');
        session()->flash('success', 'Réalisation enregistrée.');
    }

    public function removeImage(): void
    {
        $project = Project::findOrFail($this->editing);
        $path = $project->image;
        $project->update(['image' => null]);
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    public function removeScreenshots(): void
    {
        $project = Project::findOrFail($this->editing);
        $paths = $project->screenshots ?? [];
        $project->update(['screenshots' => []]);
        Storage::disk('public')->delete($paths);
    }

    public function delete(int $id): void
    {
        $project = Project::findOrFail($id);
        $paths = array_filter([$project->image, ...($project->screenshots ?? [])]);
        $project->delete();
        Storage::disk('public')->delete($paths);
        $this->showForm = false;
        session()->flash('success', 'Réalisation supprimée.');
    }

    public function render(): View
    {
        return view('livewire.admin.projects', ['projects' => Project::when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.mb_substr($this->search, 0, 200).'%'))->latest()->paginate(12), 'currentProject' => $this->editing ? Project::find($this->editing) : null])->layout('components.admin-layout', ['title' => 'Réalisations']);
    }
}
