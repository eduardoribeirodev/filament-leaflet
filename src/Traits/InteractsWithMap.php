<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Traits;

use Filament\Actions\Action;
use Livewire\Attributes\On;

trait InteractsWithMap
{
    #[On('marker-created')]
    public function resetTable(): void
    {
        parent::resetTable();
    }

    protected function afterActionCalled(Action $action): void
    {
        $this->dispatch('update-map');
    }
}
