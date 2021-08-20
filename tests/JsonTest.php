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

use Feast\Json\Json;
use Mocks\TestJsonItem;
use PHPUnit\Framework\TestCase;

class JsonTest extends TestCase
{

    public function setUp(): void
    {
        date_default_timezone_set('America/New_York');
    }

    public function testMarshal(): void
    {
        $item = new TestJsonItem();
        $item->firstName = 'FEAST';
        $item->lastName = 'Framework';
        $item->count = 4;
        $item->notIncluded = 'Testing';
        $item->aClass = new stdClass();
        $item->aClass->test = 'ItWorks';

        $item->item = new TestJsonItem();
        $item->item->firstName = 'Jeremy';
        $item->item->lastName = 'Presutti';
        $item->item->calls = 4;
        $item->cards = ['4', 5, ['6']];
        $item2 = new TestJsonItem();
        $item2->firstName = 'PHP';
        $item2->lastName = '7.4';
        $item3 = new TestJsonItem();
        $item3->firstName = 'PHP';
        $item3->lastName = '8.0';
        $item->items[] = $item2;
        $item->items[] = $item3;

        $item2 = new TestJsonItem();
        $item2->firstName = 'Json';
        $item2->lastName = 'Serializer';
        $item3 = new TestJsonItem();
        $item3->firstName = 'Item';
        $item3->lastName = 'Parsing';

        $secondItem = new \Mocks\SecondItem();
        $secondItem->firstName = 'Orlando';
        $secondItem->lastName = 'Florida';

        $item->secondItem = $secondItem;

        $timestampOne = DateTime::createFromFormat('U', '1618534584');
        $timestampTwo = DateTime::createFromFormat('U', '1617619260');

        $timestampOne->setTimezone(new DateTimeZone('America/New_York'));
        $timestampTwo->setTimezone(new DateTimeZone('America/New_York'));
        $item->timestamp = $timestampOne;
        $item->otherTimestamp = $timestampTwo;

        $data = Json::marshal($item);
        $this->assertEquals(
            '{"first_name":"FEAST","last_name":"Framework","test_item":{"first_name":"Jeremy","last_name":"Presutti","calls":4},"second_item":{"also_first_name":"Orlando","also_last_name":"Florida"},"items":[{"first_name":"PHP","last_name":"7.4","calls":null},{"first_name":"PHP","last_name":"8.0","calls":null}],"cards":["4",5,["6"]],"calls":null,"count":4,"aClass":{"test":"ItWorks"},"a_timestamp":"20210415","otherTimestamp":"2021-04-05T06:41:00-0400"}',
            $data
        );
    }

    public function testUnmarshalInvalidConstructor(): void
    {
        $this->expectException(\Feast\Json\Exception\JsonException::class);
        Json::unmarshal('{"test":"test"}', \Mocks\BadJsonItem::class);
    }

    public function testUnmarshalWithObject(): void
    {
        $item = new \Mocks\BadJsonItem('ShouldNotExplode');
        $result = Json::unmarshal('{"first_name":"FEAST","last_name":"Framework"}', $item);
        $this->assertEquals('FEAST', $result->firstName);
        $this->assertEquals('Framework', $result->lastName);
    }

    public function testUnmarshal(): void
    {
        $data = '{"first_name":"FEAST","last_name":"Framework","test_item":{"first_name":"Jeremy","last_name":"Presutti","calls":4},"second_item":{"also_first_name":"Orlando","also_last_name":"Florida"},"items":[{"first_name":"PHP","last_name":"7.4","calls":null},{"first_name":"PHP","last_name":"8.0","calls":null}],"cards":["4",5,["6"]],"calls":null,"count":4,"aClass":{"test":"ItWorks"},"a_timestamp":"20210415","otherTimestamp":"2021-04-05T06:41:00-0400"}';
        /** @var TestJsonItem $result */
        $result = Json::unmarshal($data, TestJsonItem::class);
        $this->assertEquals('FEAST', $result->firstName);
        $this->assertEquals('Framework', $result->lastName);
        $this->assertNull($result->calls);
        $this->assertEquals('Orlando', $result->secondItem->firstName);
        $this->assertEquals('Florida', $result->secondItem->lastName);
        $this->assertEquals(['4', 5, ['6']], $result->cards);
        $result->timestamp->setTimezone(new DateTimeZone('America/New_York'));
        $result->otherTimestamp->setTimezone(new DateTimeZone('America/New_York'));
        $this->assertEquals('20210415', $result->timestamp->format('Ymd'));
        $this->assertEquals('20210405', $result->otherTimestamp->format('Ymd'));
        $this->assertTrue($result->secondItem instanceof \Mocks\SecondItem);
        $this->assertTrue($result->aClass instanceof stdClass);
        $this->assertTrue($result->items[0] instanceof TestJsonItem);
        $this->assertEquals('ItWorks',$result->aClass->test);
    }

    public function testUnmarshalMarshal(): void
    {
        $data = '{"first_name":"FEAST","last_name":"Framework","test_item":{"first_name":"Jeremy","last_name":"Presutti","calls":4},"second_item":{"also_first_name":"Orlando","also_last_name":"Florida"},"items":[{"first_name":"PHP","last_name":"7.4","calls":null},{"first_name":"PHP","last_name":"8.0","calls":null}],"cards":["4",5,["6"]],"calls":null,"count":4,"aClass":{"test":"ItWorks"},"a_timestamp":"20210415","otherTimestamp":"2021-04-05T06:41:00-0400"}';
        $this->assertEquals($data, Json::marshal(Json::unmarshal($data, TestJsonItem::class)));
    }

    public function testUnmarshalMarshalUnmarshalMarshal(): void
    {
        $data = '{"first_name":"FEAST","last_name":"Framework","test_item":{"first_name":"Jeremy","last_name":"Presutti","calls":4},"second_item":{"also_first_name":"Orlando","also_last_name":"Florida"},"items":[{"first_name":"PHP","last_name":"7.4","calls":null},{"first_name":"PHP","last_name":"8.0","calls":null}],"cards":["4",5,["6"]],"calls":null,"count":4,"aClass":{"test":"ItWorks"},"a_timestamp":"20210415","otherTimestamp":"2021-04-05T06:41:00-0400"}';
        $this->assertEquals(
            $data,
            Json::marshal(
                Json::unmarshal(
                    Json::marshal(Json::unmarshal($data, TestJsonItem::class)),
                    TestJsonItem::class
                )
            )
        );
    }
}
