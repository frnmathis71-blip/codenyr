<?php

return [
    // Match the commercial import limit during Livewire's temporary upload step.
    'temporary_file_upload' => [
        'rules' => ['required', 'file', 'max:20480'],
    ],
];
