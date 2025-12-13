<?php

namespace EduardoRibeiroDev\FilamentLeaflet\Support;

use Closure;
use Exception;
use ReflectionFunction;

class CallbackResolver
{
    protected ?Closure $callback = null;
    protected array $parameters = [];

    public function __construct(?Closure $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Cria uma instância do resolvedor de callback a partir de uma Closure
     * @param Closure $callback
     * @return static
     */
    public static function from(Closure $callback)
    {
        return new static($callback);
    }

    /**
     * Define parâmetros adicionais para a resolução do callback
     * @param array $parameters
     * @return $this
     */
    public function parameters(array $parameters)
    {
        $this->parameters = array_merge($this->parameters, $parameters);
        return $this;
    }

    /**
     * Define um parâmetro adicional para a resolução do callback
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function parameter(string $name, mixed $value)
    {
        $this->parameters[$name] = $value;
        return $this;
    }

    /**
     * Resolve o callback, injetando os parâmetros necessários
     * @param array $parameters
     * @return mixed
     * @throws Exception
     */
    public function resolve(array $parameters = []): mixed
    {
        $reflection = new ReflectionFunction($this->callback);
        $this->parameters($parameters);
        $resolvedArguments = [];

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $this->parameters)) {
                $resolvedArguments[] = $this->parameters[$name];
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $resolvedArguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new Exception("Unable to resolve dependency: \${$name}");
        }

        return call_user_func($this->callback, ...$resolvedArguments);
    }
}
