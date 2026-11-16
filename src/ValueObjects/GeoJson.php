<?php

namespace EduardoRibeiroDev\FilamentLeaflet\ValueObjects;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class GeoJson implements Arrayable, Castable
{
    public function __construct(protected string $filePath, protected ?string $disk = null) {}

    public function filePath(string $filePath): static { $this->filePath = $filePath; return $this; }
    public function disk(?string $disk): static { $this->disk = $disk; return $this; }
    public function getFilePath(): string { return $this->filePath; }
    public function getDisk(): ?string { return $this->disk; }

    public function toArray(): array
    {
        return array_filter(['file_path' => $this->filePath, 'disk' => $this->disk], fn ($value) => $value !== null);
    }

    public static function fromArray(array $data, ?string $disk = null): ?static
    {
        return isset($data['file_path']) ? new static($data['file_path'], $disk ?? $data['disk'] ?? null) : null;
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class($arguments[0] ?? null) implements CastsAttributes
        {
            public function __construct(private ?string $disk) {}

            public function get(Model $model, string $key, mixed $value, array $attributes): ?GeoJson
            {
                if ($value === null) return null;
                $data = is_string($value) ? json_decode($value, true) : $value;
                return is_array($data) ? GeoJson::fromArray($data, $this->disk) : null;
            }

            public function set(Model $model, string $key, mixed $value, array $attributes): array
            {
                if ($value === null) return [$key => null];
                $value = is_array($value) ? GeoJson::fromArray($value, $this->disk) : $value;
                if (!$value instanceof GeoJson) {
                    throw new InvalidArgumentException('Value must be an instance of ' . GeoJson::class . '.');
                }
                return [$key => json_encode($value->toArray())];
            }
        };
    }
}
