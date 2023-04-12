<?php

namespace Lucian\FieldsOptions\Test\Fixture;

trait MagicSetterTrait
{
    public function __get($property)
    {
        if ($this->propertyExists($property)) {
            return $this->$property;
        }

        throw new \LogicException(sprintf('Invalid property "%s"', $property));
    }

    public function __set($property, $value)
    {
        if (!$this->propertyExists($property)) {
            throw new \LogicException(sprintf('Invalid property "%s"', $property));
        }

        $method = 'set'. ucfirst($property);
        if (method_exists($this, $method)) {
            call_user_func([$this, $method], $value);
            return $this;
        }

        throw new \LogicException(sprintf('Cannot set property "%s"', $property));
    }

    public function __isset($property)
    {
        return $this->propertyExists($property) && isset($this->$property);
    }

    /**
     * Checks if a property exists with this name
     *
     * @param string $property
     * @return bool
     */
    public function propertyExists($property)
    {
        return property_exists($this, $property) && (strpos($property, '__') !== 0);
    }
}
