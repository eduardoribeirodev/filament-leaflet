<?php

namespace EduardoRibeiroDev\FilamentLeaflet\LayerGroups;

use EduardoRibeiroDev\FilamentLeaflet\LayerGroups\BaseLayerGroup;

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
