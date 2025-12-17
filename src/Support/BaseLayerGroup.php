<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support;

use EduardoRibeiroDev\FilamentLeaflet\Traits\HasOptions;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;

abstract class BaseLayerGroup implements Arrayable, Jsonable
{
    use Conditionable;
    use Macroable;
    use HasOptions;

    protected ?string $id = null;
    protected ?array $layers = null;
    protected ?string $name = null;

    public function __construct(?array $layers = null, ?string $id = null, ?string $name = null)
    {
        $this->layers = $layers;
        $this->id = $id;
        $this->name = $name;
    }

    /**
     * @param array<BaseLayer> $layers
     */
    public static function make(?array $layers = null): static
    {
        return new static($layers);
    }

    /**
     * Obtém os layers do grupo.
     * @return array<BaseLayer>
     */
    public function getLayers(): array
    {
        return array_map($this->modifyLayerUsing(...), $this->layers);
    }

    /**
     * Define o nome do grupo.
     */
    public function name(?string $name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Gera ID determinístico baseado nos dados do layer
     */
    private function generateDeterministicId(): string
    {
        return $this->getType() . '-' . md5($this->getDeterministicIdData());
    }

    /**
     * Retorna os dados usados para gerar o ID determinístico
     */
    protected function getDeterministicIdData(): string
    {
        return json_encode($this->getLayerGroupData());
    }

    /**
     * Define o ID do grupo.
     */
    public function id(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Retorna o ID do grupo.
     */
    public function getId(): ?string
    {
        return $this->id ?? $this->generateDeterministicId();
    }

    /*
    |--------------------------------------------------------------------------
    | Métodos abstratos
    |--------------------------------------------------------------------------
    */

    /**
     * Retorna o tipo de layer para o frontend (marker, circle, polygon, etc)
     */
    abstract public function getType(): string;

    /**
     * Modifica cada layer do grupo ao ser serializado.
     */
    protected function modifyLayerUsing(BaseLayer $layer): BaseLayer
    {
        return $layer->group($this);
    }

    /*
    |--------------------------------------------------------------------------
    | Serialization
    |--------------------------------------------------------------------------
    */

    /**
     * Retorna os dados específicos do layer para serialização
     */
    protected function getLayerGroupData(): array
    {
        return [
            'type' => $this->getType(),
            'options' => $this->getOptions(),
            'name' => $this->getName(),
        ];
    }

    public function toArray(): array
    {
        $data = array_merge(
            $this->getLayerGroupData(),
            ['id' => $this->getId()]
        );

        return array_filter($data);
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function __toString(): string
    {
        return sprintf(
            '%s [%s]',
            class_basename($this),
            $this->id
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Getters
    |--------------------------------------------------------------------------
    */

    /**
     * Retorna a contagem de layers no grupo.
     */
    public function count(): int
    {
        return count($this->layers ?? []);
    }

    /**
     * Retorna o nome do grupo do layer.
     */
    public function getName()
    {
        return $this->name;
    }
}
