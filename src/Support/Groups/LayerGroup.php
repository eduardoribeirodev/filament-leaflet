<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support\Groups;

use EduardoRibeiroDev\FilamentLeaflet\Support\BaseLayerGroup;

class LayerGroup extends BaseLayerGroup
{
    /*
    |--------------------------------------------------------------------------
    | Métodos abstratos do Layer Group
    |--------------------------------------------------------------------------
    */

    public function getType(): string
    {
        return 'group';
    }
}
