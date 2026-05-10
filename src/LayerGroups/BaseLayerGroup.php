<?php

namespace EduardoRibeiroDev\FilamentLeaflet\LayerGroups;

use EduardoRibeiroDev\FilamentLeaflet\Layers\BaseLayer;
use Filament\Support\Concerns\EvaluatesClosures;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;

abstract class BaseLayerGroup implements Arrayable, Jsonable
{
    use Conditionable;
    use Macroable;
    use EvaluatesClosures;

    protected ?string $id = null;
    protected array $layers = [];
    protected ?string $name = null;
    protected ?bool $isEditable = null;

    public function __construct(array $layers = [], ?string $id = null, ?string $name = null)
    {
        $this->layers = $layers;
        $this->id = $id;
        $this->name = $name;
    }

    /**
     * Convenience method to create a BaseLayerGroup instance with given layers. The $layers parameter is an array of BaseLayer instances that will be part of the group. This method provides a simple way to instantiate a layer group with its associated layers in one step.
     * @param array<BaseLayer>|null $layers An array of BaseLayer instances to be included in the group. This parameter is optional, and if not provided, the group will be initialized without any layers.
     * @return static A new instance of a class that extends BaseLayerGroup, initialized with the specified layers.
     */
    public static function make(array $layers = []): static
    {
        return new static($layers);
    }

    /**
     * Retorna os layers do grupo, aplicando modificações usando o método modifyLayerUsing() para cada layer. O método getLayers() é responsável por fornecer a lista de layers que pertencem ao grupo, garantindo que cada layer seja modificado de acordo com as regras definidas em modifyLayerUsing() antes de ser retornado.
     * @return array<BaseLayer>
     */
    public function getLayers(): array
    {
        return array_map($this->modifyLayerUsing(...), $this->layers);
    }

    /**
     * Set the name of the layer group. The $name parameter is a string that represents the name of the group, which can be used for organizational purposes or to display in a layer control on the frontend. This method allows you to assign a human-readable name to the group of layers, making it easier to identify and manage them within the application.
     * @param string|null $name The name of the layer group. This parameter can be a string representing the name you want to assign to the group, or null if you want to remove any existing name.
     * @return $this The current instance of the BaseLayerGroup with the updated name.
     */
    public function name(?string $name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set whether the layers in the group should be editable. The $editable parameter is a boolean that indicates whether the layers within the group can be edited on the frontend. When set to true, it allows users to modify the layers (e.g., move markers, reshape polygons) directly on the map interface. This method provides a way to control the editability of all layers within the group with a single setting.
     * @param bool|null $editable A boolean value indicating whether the layers in the group should be editable. If true, users will be able to edit the layers on the frontend. If false or null, the layers will not be editable.
     * @return $this The current instance of the BaseLayerGroup with the updated editability setting.
     */
    public function editable(?bool $editable = true): static
    {
        $this->isEditable = $editable;
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
     * Set the ID of the layer group. The $id parameter is a string that uniquely identifies the group of layers. This method allows you to assign a specific ID to the layer group, which can be useful for referencing the group in JavaScript or for managing it within the application. If an ID is not explicitly set, a deterministic ID will be generated based on the group's data.
     * @param string $id The unique identifier for the layer group. This should be a string that uniquely identifies the group of layers, and it can be used for referencing the group in JavaScript or for managing it within the application.
     * @return $this The current instance of the BaseLayerGroup with the updated ID.
     * @example $layerGroup->id('my-unique-layer-group'); // Sets the ID of the layer group to 'my-unique-layer-group'.
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
        return $layer
            ->group($this)
            ->when(
                $this->isEditable !== null,
                fn($layer) => $layer->editable($this->isEditable)
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Serialization
    |--------------------------------------------------------------------------
    */

    protected function getLayerGroupOptions(): array
    {
        return [];
    }

    /**
     * Retorna os dados específicos do layer para serialização
     */
    protected function getLayerGroupData(): array
    {
        return [
            'type'    => $this->getType(),
            'name'    => $this->getName(),
            'options' => $this->getLayerGroupOptions()
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
        return count($this->layers);
    }

    /**
     * Retorna o nome do grupo do layer.
     */
    public function getName()
    {
        return $this->name;
    }
}
