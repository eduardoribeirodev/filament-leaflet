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

    public function hex(): string
    {
        return match ($this) {
            self::Blue => '#2A81CB',
            self::Red => '#CB2B3E',
            self::Green => '#2AAD27',
            self::Orange => '#CB8427',
            self::Yellow => '#CAC428',
            self::Violet => '#9C2BCB',
            self::Grey => '#7B7B7B',
            self::Black => '#3D3D3D',
            self::Gold => '#FFD326',
        };
    }
}