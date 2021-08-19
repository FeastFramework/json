<?php

/**
 * Copyright 2021 Jeremy Presutti <Jeremy@Presutti.us>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace Feast\Json;

use DateTime;
use Feast\Json\Attributes\JsonItem;
use Feast\Json\Exception\JsonException;
use ReflectionException;
use ReflectionProperty;

class Json
{

    /**
     * Marshal an object into a JsonString.
     *
     * The field names are kept as is, unless a Feast\Json\Attributes\JsonItem attribute decorates the property.
     *
     * @param object $object
     * @return string
     * @throws ReflectionException
     * @see \Feast\Json\Attributes\JsonItem
     */
    public static function marshal(object $object): string
    {
        $return = new \stdClass();
        $paramInfo = self::getClassParamInfo(get_class($object));
        /**
         * @var string $oldName
         * @var array{name:string|null,type:string|null,dateFormat:string,included:bool} $newInfo
         */
        foreach ($paramInfo as $oldName => $newInfo) {
            if ($newInfo['included'] === false) {
                continue;
            }
            $newName = $newInfo['name'];

            $reflected = new ReflectionProperty($object, $oldName);
            if ($reflected->isInitialized($object)) {
                /** @var scalar|object|array $oldItem */
                $oldItem = $object->{$oldName};
                if (is_array($oldItem) || $oldItem instanceof \stdClass) {
                    $return->{$newName} = self::marshalArray((array)$oldItem);
                } elseif (is_object(
                        $oldItem
                    ) && $oldItem instanceof DateTime === false) {
                    $return->{$newName} = (object)json_decode(self::marshal($oldItem));
                } elseif ($oldItem instanceof DateTime) {
                    $return->{$newName} = $oldItem->format($newInfo['dateFormat']);
                } else {
                    $return->{$newName} = $oldItem;
                }
            }
        }
        return json_encode($return);
    }

    /**
     * Unmarshal a JSON string into a class.
     *
     * Property types can be decorated with the Feast\Json\Attributes\JsonItem attribute.
     * This type info allows layered marshalling.
     *
     * @param string $data
     * @param class-string|object $objectOrClass
     * @return object
     * @throws ReflectionException
     * @throws \Feast\Json\Exception\JsonException
     * @see \Feast\Json\Attributes\JsonItem
     */
    public static function unmarshal(string $data, $objectOrClass): object
    {
        if (is_string($objectOrClass)) {
            try {
                /** @psalm-suppress MixedMethodCall */
                $object = new $objectOrClass();
            } catch (\ArgumentCountError $e) {
                throw new JsonException(
                    'Attempted to unmarshal into a class without a no-argument capable constructor'
                );
            }
        } else {
            $object = $objectOrClass;
        }
        $className = get_class($object);
        /** @var array $jsonData */
        $jsonData = json_decode($data, true, 512,  JSON_THROW_ON_ERROR);
        $paramInfo = self::getClassParamInfo($className);

        $classInfo = new \ReflectionClass($className);
        foreach ($classInfo->getProperties() as $property) {
            $newPropertyName = $property->getName();
            /** @var string $propertyName */
            $propertyName = $paramInfo[$newPropertyName]['name'] ?? $newPropertyName;
            if (!array_key_exists($propertyName, $jsonData)) {
                continue;
            }
            /** @var scalar|array $propertyValue */
            $propertyValue = $jsonData[$propertyName];
            self::unmarshalProperty(
                $property,
                (string)$paramInfo[$newPropertyName]['type'],
                (string)$paramInfo[$newPropertyName]['dateFormat'],
                $propertyValue,
                $object
            );
        }

        return $object;
    }

    /**
     * @param array $items
     * @return array
     * @throws ReflectionException
     */
    protected static function marshalArray(
        array $items
    ): array {
        $return = [];
        /**
         * @var string $key
         * @var scalar|object|array $item
         */
        foreach ($items as $key => $item) {
            if (is_array($item) || $item instanceof \stdClass) {
                $return[$key] = self::marshalArray((array)$item);
            } elseif (is_object($item)) {
                $return[$key] = (array)json_decode(self::marshal($item));
            } else {
                $return[$key] = $item;
            }
        }

        return $return;
    }

    /**
     * @param class-string $class
     * @return array<array{name:string|null,type:string|null,dateFormat:string|null,included:bool}>
     * @throws ReflectionException
     */
    protected static function getClassParamInfo(
        string $class
    ): array {
        $return = [];
        $classInfo = new \ReflectionClass($class);
        foreach ($classInfo->getProperties() as $property) {
            $name = $property->getName();
            $type = null;
            $dateFormat = \DateTimeInterface::ISO8601;
            $included = true;
            $attributes = $property->getDocComment();
            if ( $attributes !== false ) {
                $attributeObject = JsonItem::createFromDocblock($name,$attributes);
            
                $name = $attributeObject->name ?? $name;
                $type = $attributeObject->arrayOrCollectionType;
                $dateFormat = $attributeObject->dateFormat;
                $included = $attributeObject->included;
            }
            $return[$property->getName()] = [
                'name' => $name,
                'type' => $type,
                'dateFormat' => $dateFormat,
                'included' => $included
            ];
        }
        return $return;
    }

    /**
     * @param ReflectionProperty $property
     * @param object $object
     * @param string $propertySubtype
     * @param array $jsonData
     * @throws Exception\JsonException
     * @throws ReflectionException
     */
    protected static function unmarshalArray(
        ReflectionProperty $property,
        object $object,
        string $propertySubtype,
        array $jsonData
    ): void {
        $newProperty = $property->getName();
        $item = [];
        if (class_exists($propertySubtype, true)) {
            /**
             * @var string $key
             * @var scalar|object|array $val
             */
            foreach ($jsonData as $key => $val) {
                $item[$key] = self::unmarshal(
                    json_encode($val),
                    $propertySubtype
                );
            }
        } else {
            $item = $jsonData;
        }
        $object->{$newProperty} = $item;
    }

    /**
     * Unmarshal a property into stdClass.
     * 
     * @param ReflectionProperty $property
     * @param object $object
     * @param array $jsonData
     */
    protected static function unmarshalStdClass(
        ReflectionProperty $property,
        object $object,
        array $jsonData
    ): void {
        $newProperty = $property->getName();
        $object->{$newProperty} = (object)json_decode(json_encode($jsonData));
    }

    /**
     * Unmarshal a property onto the object.
     *
     * @param ReflectionProperty $property
     * @param string $propertySubtype
     * @param string $propertyDateFormat
     * @param scalar|array $propertyValue
     * @param object $object
     * @throws \Feast\Json\Exception\JsonException
     * @throws \ReflectionException
     */
    protected static function unmarshalProperty(
        ReflectionProperty $property,
        string $propertySubtype,
        string $propertyDateFormat,
        $propertyValue,
        object $object
    ): void {
        $propertyType = (string)$property->getType();

        if ($propertyType === 'array' && is_array($propertyValue)) {
            self::unmarshalArray(
                $property,
                $object,
                $propertySubtype,
                $propertyValue,
            );
        } elseif ( $propertyType === \stdClass::class && is_array($propertyValue) ) {
            self::unmarshalStdClass($property,$object,$propertyValue);
        }
        elseif (is_a($propertyType, DateTime::class, true) && is_scalar($propertyValue)) {
            $object->{$property->getName()} = DateTime::createFromFormat($propertyDateFormat, (string)$propertyValue);
        } elseif (class_exists($propertyType, true)) {
            $object->{$property->getName()} = self::unmarshal(
                json_encode($propertyValue),
                $propertyType
            );
        } else {
            $object->{$property->getName()} = $propertyValue;
        }
    }
}
