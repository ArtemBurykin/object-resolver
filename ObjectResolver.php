<?php

namespace AveSystems\ObjectResolverBundle;

use AveSystems\ObjectResolverBundle\Exception\ClassNotFoundException;
use AveSystems\ObjectResolverBundle\Exception\UnableToGetClassNameException;
use AveSystems\ObjectResolverBundle\Exception\UnableToGetValueException;
use AveSystems\ObjectResolverBundle\Exception\UnableToSetIdException;
use AveSystems\ObjectResolverBundle\Exception\UnableToSetValueException;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Util\Inflector;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Class merges entity with it's persisted copy or just unserialize object to specific class.
 * Converts some values to appropriate type.
 *
 * @author Artem Burykin <nisoartem@gmail.com>
 */
class ObjectResolver
{
    /** PROPERTY TYPES */
    const SIMPLE = 1;

    const OBJECT = 2;

    const COLLECTION = 3;

    /** FLAGS */
    /**
     * If set throws an Exception if impossible to set ID to target.
     */
    const ERROR_IF_CANT_SET_ID_FLAG = 0b0001;

    /**
     * If set performs recursive persist on resolved target.
     */
    const PERSIST_FLAG = 0b0010;

    /**
     * If set class will not try to resolve provided class name using ORM.
     */
    const SKIP_RESOLVING_CLASS_NAME_FLAG = 0b0100;

    private $_em;

    private $_pAccessor;

    private $_reader;

    private $_serializedNameAnnotation;

    private $_classReflection = [];

    private $_ormMeta = [];

    private $_flags;

    public function __construct(EntityManagerInterface $em, Reader $reader = null, $serializedNameAnnotation = null)
    {
        $this->_em = $em;
        $this->_reader = $reader;
        $this->_pAccessor = PropertyAccess::createPropertyAccessor();
        $this->_serializedNameAnnotation = $serializedNameAnnotation;
    }

    /**
     * Unserializes and|or merges plain object to it's persisted copy.
     *
     * @param stdClass $source    simple object, may be just decoded JSON. Source of data
     * @param string   $className target class name for unserialization
     * @param type     $target    you may provide target object to which it should merge source data
     * @param int      $flags     combination of flags. Use | to combine flags.
     *
     * @throws UnableToSetIdException        some objects of object tree don't have setters for ID property, but incoming data
     *                                       provides it
     * @throws UnableToGetValueException     if can't get value from source
     * @throws UnableToSetValueException     if can't set value to target
     * @throws UnableToGetClassNameException resolver can't determine target class name using provided arguments
     * @throws ClassNotFoundException        if provided class can not be found
     *
     * @return object - merged entity
     */
    public function resolveObject(
        $source, $className = null, $target = null, $flags = (self::PERSIST_FLAG | self::ERROR_IF_CANT_SET_ID_FLAG)
    ) {
        $this->_flags = $flags;

        if (!$className) {
            $className = get_class($source);
        }

        if (!$className && $target) {
            $className = get_class($target);
        }

        if ('stdClass' === $className || !$className) {
            throw new UnableToGetClassNameException();
        }

        if (!class_exists($className) && !interface_exists($className)) {
            throw new ClassNotFoundException();
        }

        if (!($flags & self::SKIP_RESOLVING_CLASS_NAME_FLAG)) {
            $className = $this->getClass($className);
        }

        $source = $this->resolveNode($source, $className, $target);

        return $source;
    }

    /**
     * Get class name from object.
     *
     * @param $entity
     *
     * @return string
     */
    private function getClass($entity)
    {
        if (is_object($entity)) {
            $entity = get_class($entity);
        }

        $meta = $this->_em->getClassMetadata($entity);

        return $meta->getName();
    }

    /**
     * Picks item from collection with ID = $id.
     *
     * @param $collection
     * @param $id
     *
     * @throws UnableToGetValueException
     *
     * @return object|null
     */
    private function getFromCollectionById($collection, $id)
    {
        foreach ($collection as $object) {
            $objectId = $this->getValue($object, 'id');

            if (empty($objectId)) {
                continue;
            }

            if ($objectId === $id) {
                return $object;
            }
        }

        return null;
    }

    /**
     * Get serialized name variations for object's property.
     *
     * @param string $propertyName property name
     * @param string $className    source object class
     *
     * @throws ClassNotFoundException
     * @throws UnableToGetValueException
     *
     * @return array - name variations
     */
    private function getSerializedName($propertyName, $className)
    {
        if (!isset($this->_classReflection[$className])) {
            try {
                $class = new \ReflectionClass($className);
                $this->_classReflection[$className] = $class;
            } catch (\ReflectionException $ex) {
                throw new ClassNotFoundException('Class '.$className.' not found', 0, $ex);
            }
        }

        $meta = $this->_classReflection[$className];
        $prop = $meta->getProperty($propertyName);

        if ($this->_serializedNameAnnotation && $this->_reader) {
            $serializedName = $this->_reader->getPropertyAnnotation(
                $prop, $this->_serializedNameAnnotation
            );
        } else {
            $serializedName = null;
        }

        if (!$serializedName) {
            return [$propertyName];
        }

        $names = null;

        try {
            $names = $this->_pAccessor->getValue($serializedName, 'name');
        } catch (\Exception $ex) {
            throw new UnableToGetValueException();
        }

        if (is_array($names)) {
            array_push($names, $propertyName);
        } else {
            $names = [$names, $propertyName];
        }

        return $names;
    }

    /**
     * Reads value from source's property.
     *
     * @param string $propertyName target class property name
     * @param type   $source       source object
     * @param string $className    class name of target used to get possible names of property of source object with assumption
     *                             that source was created by serialization of target
     *
     * @throws ClassNotFoundException
     * @throws UnableToGetValueException
     *
     * @return array - two items - value and flag depicting that value was set
     */
    private function readProperty($propertyName, $source, $className)
    {
        $names = $this->getSerializedName($propertyName, $className);
        $value = '';
        $valueSet = false;

        foreach ($names as $name) {
            $value = $this->getValue($source, $name);
            if (null !== $value) {
                $valueSet = true;
                break;
            }
        }

        return [$value, $valueSet];
    }

    /**
     * Transforms serialized datetime ISO8601 value to DateTime object.
     *
     * @param $value
     *
     * @return \DateTime|null
     */
    private function toDate($value)
    {
        $dateRx = '(?P<y>\d\d\d\d)-(?P<m>\d\d)-(?P<d>\d\d)';
        $timeRx = '(?P<h>\d\d):(?P<min>\d\d)(?P<s>:\d\d)?(\.(?P<ms>\d+))?';
        $tzRx = '(?P<tzt>\w{1,3})|(?P<tzo>\+?\d\d\d\d)|(?P<tzp>\+?\d\d:\d\d)';
        $rx = "/^$dateRx\s*T?\s*($timeRx)?\s*($tzRx)?$/i";
        $m = [];
        preg_match($rx, $value, $m);

        if (empty($m)) {
            return null;
        }

        $value = $m['y'].'-'.$m['m'].'-'.$m['d'].'T'.
                (isset($m['h']) ? sprintf('%02d', $m['h']) : '00').':'.
                (isset($m['min']) ? sprintf('%02d', $m['min']) : '00').':'.
                (isset($m['s']) ? sprintf('%02d', $m['s']) : '00');

        if (isset($m['tzp'])) {
            $value .= $m['tzp'];
            $format = \DateTime::W3C;
        } elseif (isset($m['tzo'])) {
            $value .= $m['tzo'];
            $format = \DateTime::ISO8601;
        } elseif (isset($m['tzt'])) {
            $tz = $m['tzt'];

            if ('Z' === $tz) {
                $value .= '+0000';
                $format = \DateTime::ISO8601;
            } else {
                $value .= $tz;
                $format = 'Y-m-d\TH:i:sT';
            }
        } else {
            $format = 'Y-m-d\TH:i:s';
        }

        $res = \DateTime::createFromFormat($format, $value);

        return $res ?: null;
    }

    /**
     * Transforms serialized $value to $type in order to set it to unserialized object.
     *
     * @param $value - serialized value
     * @param $type - string representing type
     *
     * @return mixed
     */
    private function toType($value, $type)
    {
        if ('datetime' === $type || 'date' === $type) {
            return $this->toDate($value);
        }

        // Json decode correctly handles other types
        return $value;
    }

    /**
     * Sets simple properties from source data.
     *
     * @param $source
     * @param $target
     * @param $className
     *
     * @throws UnableToSetIdException
     * @throws ClassNotFoundException
     * @throws UnableToGetValueException
     * @throws UnableToSetValueException
     */
    private function setSimpleFields($source, &$target, $className)
    {
        $simpleFields = $this->getProperties($className, self::SIMPLE);

        foreach ($simpleFields as $field => $type) {
            if ('id' === $field && !$this->_pAccessor->isWritable($target, 'id')) {
                if (!empty($this->getValue($target, 'id')) &&
                    $this->getValue($target, 'id') === $this->getValue($source, 'id')) {
                    continue;
                }

                if (!empty($this->getValue($source, 'id')) &&
                    ($this->_flags & self::ERROR_IF_CANT_SET_ID_FLAG)) {
                    throw new UnableToSetIdException(get_class($target));
                }

                continue;
            }

            list($rawValue, $valueSet) = $this->readProperty($field, $source, $className);

            if (!$valueSet) {
                continue;
            }

            $value = $this->toType($rawValue, $type);

            try {
                $this->_pAccessor->setValue($target, $field, $value);
            } catch (\Exception $ex) {
                throw new UnableToSetValueException();
            }
        }
    }

    /**
     * Returns name variations for class property name - CamelCase and snake_case.
     *
     * @param string $property
     *
     * @return array
     */
    private function getVariationsOfPropertyTitle($property)
    {
        $skVariation = $this->snakeToCamel($property);
        $ckVariation = $this->camelToSnake($property);
        $variations = [$skVariation, $ckVariation];

        return $variations;
    }

    /**
     * Converts string from camel to string case.
     *
     * @param $string
     *
     * @return string
     */
    private function camelToSnake($string)
    {
        return strtolower(preg_replace('/[A-Z]/', '_\\0', lcfirst($string)));
    }

    /**
     * Converts string from snake to camel case.
     *
     * @param $string
     *
     * @return null|string|string[]
     */
    private function snakeToCamel($string)
    {
        $camelCasedName = preg_replace_callback('/(^|_|\.)+(.)/', function ($match) {
            return ('.' === $match[1] ? '_' : '').strtoupper($match[2]);
        }, $string);

        $camelCasedName = lcfirst($camelCasedName);

        return $camelCasedName;
    }

    /**
     * Returns property value of object or array.
     *
     * @param array|object $source
     * @param string       $propertyName
     *
     * @throws UnableToGetValueException
     *
     * @return mixed
     */
    private function getValue($source, $propertyName)
    {
        $value = null;
        $variations = $this->getVariationsOfPropertyTitle($propertyName);

        try {
            foreach ($variations as $variation) {
                if ($this->_pAccessor->isReadable($source, $variation)) {
                    $value = $this->_pAccessor->getValue($source, $variation);
                }

                if (!$value && is_array($source) &&
                    $this->_pAccessor->isReadable($source, '['.$variation.']')) {
                    $value = $this->_pAccessor->getValue($source, '['.$variation.']');
                }

                if (null !== $value) {
                    break;
                }
            }

            return $value;
        } catch (\Exception $ex) {
            throw new UnableToGetValueException();
        }
    }

    /**
     * @param $source
     * @param $target
     * @param $className
     *
     * @throws UnableToSetIdException
     * @throws ClassNotFoundException
     * @throws UnableToGetValueException
     * @throws UnableToSetValueException
     */
    private function setSingleValuedAssociationFields($source, &$target, $className)
    {
        $objectsFields = $this->getProperties($className, self::OBJECT);
        $meta = $this->_em->getClassMetadata($className);

        foreach ($objectsFields as $field => $type) {
            list($value, $valueSet) = $this->readProperty($field, $source, $className);

            if (!$valueSet) {
                continue;
            }

            $subClassName = $meta->getAssociationTargetClass($field);
            $resolvedObject = $this->resolveNode($value, $subClassName);

            try {
                $this->_pAccessor->setValue($target, $field, $resolvedObject);
            } catch (\Exception $ex) {
                throw new UnableToSetValueException('', 0, $ex);
            }
        }
    }

    /**
     * @param $collectionSource
     * @param $collectionTarget
     * @param $target
     * @param $assocClass
     * @param $field
     *
     * @throws UnableToSetIdException
     * @throws UnableToGetValueException
     * @throws ClassNotFoundException
     * @throws UnableToSetValueException
     */
    private function updateCollection($collectionSource, $collectionTarget, $target, $assocClass, $field)
    {
        if (empty($collectionTarget)) {
            $collectionTarget = [];
        }

        foreach ($collectionTarget as $t) {
            $same = $this->getFromCollectionById($collectionSource, $t->getId());

            if (!$same) {
                $target->{'remove'.$field}($t);
            } else {
                $new = $this->resolveNode($same, $assocClass);
                $target->{'remove'.$field}($t);
                $target->{'add'.$field}($new);
            }
        }

        foreach ($collectionSource as $s) {
            $itemId = $this->getValue($s, 'id');
            $same = false;

            if (!empty($itemId)) {
                $same = $this->getFromCollectionById($collectionTarget, $itemId);
            }

            if (!$same) {
                $new = $this->resolveNode($s, $assocClass);
                $target->{'add'.$field}($new);
            }
        }
    }

    /**
     * Merges collection fields from source to target.
     *
     * @param $source
     * @param $target
     * @param $className
     *
     * @throws UnableToSetIdException
     * @throws UnableToGetValueException
     * @throws ClassNotFoundException
     * @throws UnableToSetValueException
     */
    private function setCollectionValuedAssociationFields($source, &$target, $className)
    {
        $collectionFields = $this->getProperties($className, self::COLLECTION);

        foreach ($collectionFields as $field => $type) {
            list($collectionSource, $valueSet) = $this->readProperty($field, $source, $className);

            if (!$valueSet) {
                continue;
            }

            if (!$collectionSource) {
                $collectionSource = [];
            }

            $collectionTarget = $this->getValue($target, $field);
            $meta = $this->_em->getClassMetadata($className);
            $assocClass = $meta->getAssociationTargetClass($field);
            $methodSuffix = Inflector::singularize($field);

            $this->updateCollection(
                $collectionSource, $collectionTarget, $target,
                $assocClass, $methodSuffix
            );
        }
    }

    /**
     * Returns saved analogue of source object if it's possible.
     *
     * @param $source - source object
     * @param string $className - FQCN of entity
     *
     * @throws UnableToGetValueException
     *
     * @return object $target
     */
    private function getTarget($source, $className)
    {
        // Object without ID here
        if (empty($this->getValue($source, 'id'))) {
            $target = new $className();
        } else {
            // Object with ID here
            $id = $this->getValue($source, 'id');
            $resolved = $this->_em->find($className, $id);

            if (empty($resolved)) {
                $target = new $className();
            } else {
                $target = $resolved;
            }
        }

        return $target;
    }

    /**
     * This function gets saved analogue of source object or new target object
     * and updates it according to source object data.
     *
     * @param $source - Source object, to be resolved
     * @param string $className - FQCN of entity class, used to create resolved entity
     * @param $target - if you want source object to be source of updates for particular object
     * you can provide this value, and updates will be applied on it
     *
     * @throws UnableToSetIdException
     * @throws UnableToGetValueException
     * @throws ClassNotFoundException
     * @throws UnableToSetValueException
     *
     * @return object
     */
    private function resolveNode($source, $className, $target = null)
    {
        if (empty($source)) {
            return null;
        }

        if (!$className) {
            //It is not entity so we don't need to merge with object from database/
            return $source;
        }

        if (!$target && ($this->_flags & self::PERSIST_FLAG)) {
            $target = $this->getTarget($source, $className);
        } elseif (!$target) {
            $target = new $className();
        }

        $this->setSimpleFields($source, $target, $className);
        $this->setSingleValuedAssociationFields($source, $target, $className);
        $this->setCollectionValuedAssociationFields($source, $target, $className);

        if ($this->_flags & self::PERSIST_FLAG) {
            $this->_em->persist($target);
        }

        return $target;
    }

    /**
     * Gives structure filled with entity fields grouped by their type.
     *
     * @param string $class
     *
     * @return array
     */
    private function collectPropertiesForClass($class)
    {
        $metadata = $this->_em->getClassMetadata($class);
        $fields = [];
        $objects = [];
        $collections = [];

        foreach ($metadata->getFieldNames() as $field) {
            $fields[$field] = $metadata->getTypeOfField($field);
        }

        foreach ($metadata->getAssociationNames() as $field) {
            if ($metadata->isCollectionValuedAssociation($field)) {
                $collections[$field] = null;
            } else {
                $objects[$field] = null;
            }
        }

        return [
            self::SIMPLE => $fields,
            self::OBJECT => $objects,
            self::COLLECTION => $collections,
        ];
    }

    /**
     * Gets properties of class specified by type.
     *
     * @param $className - class name
     * @param int $propertyType - Use class constants to provide this value
     *
     * @return array
     */
    private function getProperties($className, $propertyType)
    {
        if (!isset($this->_ormMeta[$className])) {
            $this->_ormMeta[$className] = $this->collectPropertiesForClass($className);
        }

        return $this->_ormMeta[$className][$propertyType];
    }
}
