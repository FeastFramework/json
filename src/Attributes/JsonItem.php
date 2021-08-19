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

namespace Feast\Json\Attributes;

class JsonItem
{
    public ?string $name = null;
        public ?string $arrayOrCollectionType = null;
        public string $dateFormat = \DateTimeInterface::ISO8601;
        public bool $included = true;
    /**
     * JsonItem constructor.
     *
     * @param string|null $name
     * @param string|null $arrayOrCollectionType
     * @param string $dateFormat - Only used if the actual property type is a Date. This will specify the format it should be converted to in the json string.
     * @param bool $included - If included is false, JSON strings from the decorated object will not include this property.
     */
    public function __construct(
        ?string $name = null,
        ?string $arrayOrCollectionType = null,
        string $dateFormat = \DateTimeInterface::ISO8601,
        bool $included = true
    ) {
        $this->name = $name;
        $this->arrayOrCollectionType = $arrayOrCollectionType;
        $this->dateFormat = $dateFormat;
        $this->included = $included;
    }

    public static function createFromDocblock(string $name, string $docblock): self {
        $included = true;
        $dateTimeFormat = \DateTimeInterface::ISO8601;
        $arrayOrCollectionType = null;

        $regex = '/@JsonItem:([a-zA-z]*) (.*)/';
        $matches = [];
        preg_match_all($regex,$docblock,$matches,PREG_SET_ORDER);

        foreach($matches as $match) {
            switch($match[1]) {
                case 'name':
                    $name = $match[2];
                    break;
                case 'dateFormat':
                    $dateTimeFormat = $match[2];
                    break;
                case 'included':
                    $included = $match[2] === 'true';
                    break;
                case 'arrayOrCollectionType':
                    $arrayOrCollectionType = $match[2];
                    break;
            }
        }
        return new self($name,$arrayOrCollectionType,$dateTimeFormat,$included);
    }
}
