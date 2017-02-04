<?php

namespace Dto;

use Dto\Exceptions\InvalidArrayOperationException;
use Dto\Exceptions\InvalidDataTypeException;
use Dto\Exceptions\InvalidPropertyException;
use Dto\Exceptions\UnstorableValueException;

/**
 * Class Dto (Data Transfer Object).
 *
 * Allows object schemas to be defined and helps to normalize object access.
 *
 * See http://php.net/manual/en/class.arrayobject.php, some ideas from
 * https://symfony.com/doc/current/components/property_access/introduction.html#installation
 * others from http://json-schema.org/
 *
 * @category
 */
class Dto extends \ArrayObject implements DtoInterface
{
    /**
     * Optional in-class schema definition
     *
     * @var mixed
     */
    protected $schema;


    /**
     * @var RegulatorInterface
     */
    protected $regulator;

    /**
     * Tracks which index of the array we are writing to
     * @var integer
     */
    protected $array_index = 0;

    /**
     * @var string
     */
    protected $type;

    /**
     * Dto constructor.
     *
     * @param mixed $input value
     * @param mixed $schema
     * @param mixed $regulator
     */
    public function __construct($input = null, $schema = null, RegulatorInterface $regulator = null)
    {
        $this->setFlags(0);

        $this->regulator = $this->getDefaultRegulator($regulator);

        // Resolve Schema references
        $this->schema = $this->regulator->compileSchema((is_null($schema)) ? $this->schema : $schema);

        $this->hydrate($input);
    }

    /**
     * @param mixed $regulator
     * @return RegulatorInterface
     */
    protected function getDefaultRegulator($regulator)
    {
        if (is_null($regulator)) {
           $container = include 'container.php';
           return new JsonSchemaRegulator($container);
        }

        return $regulator;
    }

    /**
     * Used for object notation, e.g. print $dto->foo
     *
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->offsetGet($name);
    }


    /**
     * Accessed when the object is written to via object notation.
     *
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $this->set($name, $value);
    }
    
    /**
     * This deals ONLY with objects (not arrays).
     * @param $key mixed
     * @param $value mixed
     * @throws InvalidDataTypeException
     */
    public function set($key, $value)
    {
        if ($this->isScalar()) {
            throw new InvalidDataTypeException('The set() method cannot be used on scalar objects.  Use hydrate() instead.');
        }

        if (parent::offsetExists($key)) {
            parent::offsetGet($key)->hydrate($value);
        }
        else {
            parent::offsetSet($key, $this->getHydratedChildDto($value, $this->regulator->getSchemaAtKey($key)));
        }
    }
    
    /**
     * Called by array access.
     *
     * @param mixed $index
     * @param mixed $value
     *
     * @throws InvalidArrayOperationException
     */
    final public function offsetSet($index, $value)
    {
        //print '>>>: '; var_dump($index); var_dump($value); print "\n";
        //print var_dump($this->regulator->isArray()); print "\n";
        //print var_dump($this->regulator->isScalar()); exit;
        //print var_dump($this->regulator->isArray());
        //print var_dump($index);
        //print var_dump($this->regulator->isObject()); exit;
        // TODO: restrict this to arrays only
        //if ($this->type !== 'array') {
//        if (!$this->regulator->isArray()) {
//            //print var_dump($this->regulator->isScalar()); exit;
//            //print 'last value: '. var_dump($value); exit;
//            print 'final: '; var_dump($value); exit;
//            //print var_dump($this->regulator->isArray()); exit;
//            //print var_dump($this->regulator->isObject()); exit;
//            throw new InvalidArrayOperationException('This operation is reserved for arrays only.');
//        }

        //    parent::offsetSet($index, $this->getHydratedChildDto($value, $this->regulator->getSchemaAtIndex($index)));


        // Does the property name match the regex? etc.
        if (is_null($index)) {
            // array -- retrieve the schema for the item, not for the index
            $schema = $this->regulator->getSchemaAtIndex($this->array_index);
            $this->array_index = $this->array_index + 1;
            parent::offsetSet(null, $this->getHydratedChildDto($value, $schema));
            return;
        }
        elseif (parent::offsetExists($index)) {
            parent::offsetGet($index)->hydrate($value);
            return;
        }
        else {
            $schema = $this->regulator->getSchemaAtIndex($index);
            parent::offsetSet($index, $this->getHydratedChildDto($value, $schema));
            return;
        }



    }

    /**
     * @param $index string attribute name
     *
     * @return bool
     */
    public function __isset($index)
    {
        return $this->offsetExists($index);
    }
    
    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toScalar();
    }
    
    /**
     * Append a value to the end of an array.  Defers to offsetSet to determine if location is valid for appending.
     * @link http://php.net/manual/en/arrayobject.append.php.
     * @throws InvalidDataTypeException
     * @param mixed $val
     */
    public function append($val)
    {
        if (!$this->regulator->isArray()) {
            throw new InvalidDataTypeException('Array operations are not allowed by the current schema.');
        }

        // validate value

        $this->offsetSet(null, $val);
    }

    /**
     * @link https://stackoverflow.com/questions/6875080/php-how-to-array-unshift-on-an-arrayobject
     * @param $val
     * @throws InvalidDataTypeException
     */
    public function prepend($val)
    {
        if ($this->regulator->isArray()) {
            throw new InvalidDataTypeException('Array operations are not allowed by the current schema.');
        }
        // TODO
    }

    /**
     * @link https://stackoverflow.com/questions/6627266/array-slice-or-other-array-functions-on-arrayobject
     * @param $offset
     * @param null $length
     * @throws InvalidDataTypeException
     */
    public function slice($offset, $length = null)
    {
        if ($this->regulator->isArray()) {
            throw new InvalidDataTypeException('Array operations are not allowed by the current schema.');
        }
        // TODO
    }

    public function get($index)
    {
        return $this->offsetGet($index);
    }

    public function getSchema()
    {
        return $this->schema;
    }

    public function offsetUnset($index)
    {
        parent::offsetUnset($index); // TODO: Change the autogenerated stub
    }

    public function offsetExists($index)
    {
        parent::offsetExists($index); // TODO: Change the autogenerated stub
    }

    /**
     * The not-so-obvious role of this function is to trigger the dynamic deepening of the object structure.
     * @param mixed $index
     * @return mixed
     *
     * @throws InvalidPropertyException
     */
    final public function offsetGet($index)
    {
        // TODO
//        // Already has property
//        // this might get weird for "dual" types, e.g. we set it to a string, then try to use it as an object.
//        //if (array_key_exists($index, $this)) {
//        if (parent::offsetExists($index)) {
//            return parent::offsetGet($index);
//        }
//
//        // We only want to deepen the structure if the data type is an object
//        $schema = $this->regulator->getPropertySchemaAsArray($index);
//
//        $this->deepenStructure($index, $schema);
//        return parent::offsetGet($index);
    }

    protected function deepenStructure($index, $schema)
    {
        $child = $this->getHydratedChildDto($index, $schema);
        $this->offsetSet($index, $child);
    }

    protected function getHydratedChildDto($input = null, $schema = []) {
        // TODO: can we pass a reference to THIS object instead of creating a new instance?
        $className = get_called_class();
        return new $className($input, $schema, $this->regulator);
    }


    /**
     * Fill the current object with data
     * @param $value mixed
     * @throws UnstorableValueException
     */
    public function hydrate($value)
    {
        $value = $this->regulator->getDefault($value);

        $value = $this->regulator->filter($value, $this->schema);

        if ($this->regulator->isObject()) {
            $this->hydrateObject($value);
        }
        elseif ($this->regulator->isArray()) {
            $this->hydrateArray($value);
        }
        else {
            $this->hydrateScalar($value);
        }
    }


    protected function hydrateObject($value)
    {
        $this->type = 'object';

        parent::exchangeArray([]);

        foreach ($value as $k => $v) {

            parent::offsetSet(
                $k,
                $this->getHydratedChildDto($v, $this->regulator->getSchemaAtKey($k))
            );
        }
    }

    protected function hydrateArray($value)
    {
        $this->type = 'array';

        // clear the array,
        parent::exchangeArray([]);
        // append to it
        foreach ($value as $v) {
            parent::offsetSet(
                null,
                $this->getHydratedChildDto($v,
                    $this->regulator->getSchemaAtIndex($this->array_index)
                )
            );
            $this->array_index = $this->array_index + 1;
        }
    }

    /**
     * Scalar values are stored in the zeroth place of the ArrayObject
     * @param $value
     */
    protected function hydrateScalar($value)
    {
        //print __FUNCTION__.':'.$value; exit;
        $this->type = 'scalar';
        parent::offsetSet(0, $value);
    }

    public function toObject()
    {
        if ($this->isScalar()) {
            throw new InvalidDataTypeException('Object representation is not possible for scalar values.');
        }

        $output = new \stdClass();
        foreach ($this as $k => $v) {
            $output->{$k} = ($v->isScalar()) ? $v->toScalar() : $v->toObject();
        }

        return $output;
    }
    
    /**
     * Convert the specified arrayObj to JSON.  Ultimately, this is a decorator around the toArray() method.
     * TODO: consider overriding the serialize() method
     * @param bool $pretty
     *
     * @return string
     */
    public function toJson($pretty = false)
    {
        // JSON can represent scalars!
        if ($this->isScalar()) {
            return json_encode(parent::offsetGet(0), JSON_PRETTY_PRINT);
        }
        else {
            // Disambiguate for empty arrays vs empty objects: [] vs {}
            return json_encode($this->toArray(), JSON_PRETTY_PRINT);
        }
    }


    /**
     * return (array) $this; // is too simplistic, unfortunately
     * @return array
     * @throws InvalidDataTypeException
     */
    public function toArray()
    {
        if ($this->isScalar()) {
            throw new InvalidDataTypeException('Array representation is not possible for scalar values.');
        }

        $output = [];
        foreach ($this as $k => $v) {
            $output[$k] = ($v->isScalar()) ? $v->toScalar() : $v->toArray();
        }

        return $output;
    }

    /**
     * Scalar values are stored in the zeroth place of the ArrayObject
     * @return mixed
     * @throws \Exception
     */
    public function toScalar()
    {
        if (!$this->isScalar()) {
            throw new InvalidDataTypeException('This DTO stores aggregate data and cannot be represented as a scalar value.');
        }

        return parent::offsetGet(0);
    }

    public function isScalar()
    {
        return ($this->type === 'scalar');
    }
}
