<?php

namespace Frontender\Core\Model;

class AbstractModel implements \ArrayAccess
{
    protected $adapter;
    protected $name;
    protected $links = [];
    protected $container;

    private $state;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getAdapter()
    {
        return $this->getAdapter();
    }

    public function setAdapter($adapter)
    {
        $this->adapter = $adapter;

        return $this;
    }

    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * If no name has been set extract it from the class name.
     *
     * @return string
     */
    public function getName(): string
    {
        if (!$this->name) {
            $path = explode('\\', get_called_class());
            $pieces = preg_split('/(?=[A-Z])/', end($path), -1, PREG_SPLIT_NO_EMPTY);

            $this->setName(strtolower(array_shift($pieces)));
        }

        return $this->name;
    }

    public function setState(array $values)
    {
        $this->getState()->setValues($values);

        return $this;
    }

    public function state()
    {
        return $this->getState()->getValues();
    }

    public function getState(): State
    {
        if (!$this->state instanceof State) {
            $this->state = new State($this->container);
        }

        return $this->state;
    }

    public function fetch()
    {
        throw new Error('Fetch should be overwritten');
    }
}
