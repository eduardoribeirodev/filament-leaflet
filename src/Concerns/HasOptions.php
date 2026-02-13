<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Concerns;

trait HasOptions
{
    protected array $options = [];

    public function option(string $key, mixed $value, string $group = 'default'): static
    {
        $this->options[$group][$key] = $value;
        return $this;
    }

    public function options(array $options, string $group = 'default', bool $merge = true): static
    {
        if ($merge) {
            $this->options[$group] = array_merge($this->options, $options);
        } else {
            $this->options[$group] = $options;
        }
        return $this;
    }

    public function getOptions(string $group = 'default'): array
    {
        return $this->options[$group] ?? [];
    }
}
