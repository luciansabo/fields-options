<?php

namespace Lucian\FieldsOptions\Test\Fixture;

/**
 * AbstractDto - base class for any DTO
 *
 * ### Accessing properties
 *
 * Properties should be defined as protected, they will be made accessible as read-only from this base class
 *
 * ```
 * echo $dto->someProperty;
 * ```
 *
 *
 * ### Changing properties
 *
 * Properties can be changed at runtime if a protected setter method is defined in the DTO
 *
 * ```
 * $dto->someProperty = 3; // internally calls $dto::setSomeProperty(3)
 * ```
 *
 * Do not define  public setters ! The magic __get automatically calls the setter.
 *
 * ### Exporting to JSON
 *
 * The DTO should export to json because it implements JsonSerializable
 * For custom logic override `jsonSerialize()`
 *
 * Changed:
 * - $__exportedProperties, $__excludedProperties made protected
 * - changes in setExportedProperties()
 * - changes in getExportedProperties(), also made public
 */
abstract class AbstractDto implements \JsonSerializable, \IteratorAggregate
{
    use MagicSetterTrait;

    protected ?array $__exportedProperties = null;
    protected array $__excludedProperties = [];

    /**
     * Verifies a property is set
     *
     * @param string $name The name of the property
     * @return bool
     */
    public function __isset($name)
    {
        return $this->propertyExists($name) && isset($this->$name);
    }

    /**
     * Get an array version for json serialization
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $properties = iterator_to_array($this);
        foreach ($properties as $name => $value) {
            if ($value instanceof \DateTimeInterface) {
                $properties[$name] = $value->format('Y-m-d H:i:s');
            }
        }

        return $properties;
    }

    /**
     * @param array $properties
     */
    public function setExportedProperties(?array $properties)
    {
        if (is_null($properties)) {
            $this->__exportedProperties = null;
            return;
        }

        $this->__exportedProperties = [];
        foreach ($properties as $property) {
            if (!$this->propertyExists($property)) {
                throw new \RuntimeException("Invalid property: $property");
            }

            $this->__exportedProperties[$property] = true;
        }
    }

    /**
     * @param array $properties
     */
    public function setExcludedProperties(array $properties)
    {
        $this->__excludedProperties = [];
        foreach ($properties as $property) {
            if (!$this->propertyExists($property)) {
                throw new \RuntimeException("Invalid property: $property");
            }

            $this->__excludedProperties[$property] = true;
        }
    }

    /**
     * @return bool
     */
    public function hasSetProperties()
    {
        return !is_null($this->__exportedProperties);
    }

    /**
     * Get an array version. Compatible with Zend ArraySerializableInterface
     *
     * @return array
     */
    public function getArrayCopy(): array
    {
        return $this->jsonSerialize();
    }

    public function getIterator(): \Traversable
    {
        $properties = $this->getExportedProperties();

        return new \ArrayIterator($properties);
    }

    /**
     * @return array
     */
    protected function getExportedProperties(): array
    {
        $properties = get_object_vars($this);
        $exported = [];

        foreach ($properties as $name => $value) {
            if (
                $this->propertyExists($name) &&
                (!$this->hasSetProperties() || !empty($this->__exportedProperties[$name])) &&
                empty($this->__excludedProperties[$name])
            ) {
                $exported[$name] = $value;
            }
        }

        return $exported;
    }
}
