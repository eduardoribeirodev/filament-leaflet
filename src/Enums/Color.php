<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Enums;

use Filament\Support\Contracts\HasLabel;

enum Color: string implements HasLabel
{
    case Blue = 'blue';
    case Red = 'red';
    case Green = 'green';
    case Orange = 'orange';
    case Yellow = 'yellow';
    case Violet = 'violet';
    case Grey = 'grey';
    case Black = 'black';
    case Gold = 'gold';

    public function getLabel(): ?string
    {
        return __($this->name);
    }
}
