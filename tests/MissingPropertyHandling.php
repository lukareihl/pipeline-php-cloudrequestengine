<?php
/* *********************************************************************
 * This Original Work is copyright of 51 Degrees Mobile Experts Limited.
 * Copyright 2023 51 Degrees Mobile Experts Limited, Davidson House,
 * Forbury Square, Reading, Berkshire, United Kingdom RG1 3EU.
 *
 * This Original Work is licensed under the European Union Public Licence
 * (EUPL) v.1.2 and is subject to its terms as set out below.
 *
 * If a copy of the EUPL was not distributed with this file, You can obtain
 * one at https://opensource.org/licenses/EUPL-1.2.
 *
 * The 'Compatible Licences' set out in the Appendix to the EUPL (as may be
 * amended by the European Commission) shall be deemed incompatible for
 * the purposes of the Work and the provisions of the compatibility
 * clause in Article 5 of the EUPL shall not apply.
 *
 * If using the Work as, or as part of, a network application, by
 * including the attribution notice(s) required under Article 5 of the EUPL
 * in the end user terms of the application under an appropriate heading,
 * such notice(s) shall fulfill the requirements of that article.
 * ********************************************************************* */

namespace fiftyone\pipeline\cloudrequestengine\tests;

use fiftyone\pipeline\cloudrequestengine\CloudEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\engines\AspectDataDictionary;
use fiftyone\pipeline\engines\MissingPropertyMessages;
use PHPUnit\Framework\TestCase;

class MissingPropertyHandling extends TestCase
{
    public const expectedNullReason = 'this is the null reason';

    public const nullValueJson =
        "{\n" .
        "  \"testElement\": {\n" .
        "    \"property1\": \"a value\",\n" .
        "    \"property2\": null,\n" .
        '    "property2nullreason": "' . self::expectedNullReason . "\"\n" .
        "  },\n" .
        "  \"javascriptProperties\": []\n" .
        '}';

    private $properties =
        [
            'property1' => [
                'name' => 'property1',
                'type' => 'string',
                'available' => true
            ],
            'property2' => [
                'name' => 'property2',
                'type' => 'string',
                'available' => true
            ]
        ];
    private $cloudProperties =
        [
            'cloud' => [
                'name' => 'cloud',
                'type' => 'string',
                'available' => true
            ]
        ];

    /**
     * Test that a cloud response which has a null value for a property is
     * mapped into an AspectPropertyValue with the 'no value reason' set from
     * the 'nullvaluereason' in the cloud response.
     */
    public function testPropertyInResourceNullValue()
    {
        $engine = new CloudEngine();
        $engine->dataKey = 'testElement';

        $cloudRequestEngine = $this->createMock(CloudRequestEngine::class);
        $cloudRequestEngine->method('getProperties')->willReturn($this->cloudProperties);
        $cloudRequestEngine->flowElementProperties = [
            'testElement' => $this->properties
        ];
        $cloudRequestEngine->dataKey = 'cloud';

        $builder = new PipelineBuilder();

        $pipeline = $builder->add($cloudRequestEngine)->add($engine)->build();

        $flowData = $pipeline->createFlowData();

        $this->addResponse($cloudRequestEngine, $flowData, self::nullValueJson);

        $engine->aspectProperties = $this->properties;
        $engine->dataKey = 'testElement';
        $engine->processInternal($flowData);

        $data = $flowData->get('testElement');

        $property2 = $data->get('property2');
        $this->assertNotNull($property2);
        $this->assertFalse($property2->hasValue);
        $this->assertSame(self::expectedNullReason, $property2->noValueMessage);
    }

    /**
     * Test that a cloud response which has no value for a property throws a
     * PropertyMissingException.
     */
    public function testPropertyNotInResource()
    {
        $engine = new CloudEngine();
        $engine->dataKey = 'testElement';

        $cloudRequestEngine = $this->createMock(CloudRequestEngine::class);
        $cloudRequestEngine->method('getProperties')->willReturn($this->cloudProperties);
        $cloudRequestEngine->flowElementProperties = [
            'testElement' => $this->properties
        ];
        $cloudRequestEngine->dataKey = 'cloud';

        $builder = new PipelineBuilder();

        $pipeline = $builder->add($cloudRequestEngine)->add($engine)->build();

        $flowData = $pipeline->createFlowData();

        $this->addResponse($cloudRequestEngine, $flowData, self::nullValueJson);

        $engine->aspectProperties = $this->properties;
        $engine->dataKey = 'testElement';
        $engine->processInternal($flowData);

        $data = $flowData->get('testElement');

        try {
            $property = $data->get('property3');
            $this->fail();
        } catch (\Exception $ex) {
            $this->assertSame(
                sprintf(
                    MissingPropertyMessages::PREFIX .
                    MissingPropertyMessages::PROPERTY_NOT_IN_CLOUD_RESOURCE,
                    'property3',
                    'testElement',
                    'testElement',
                    'property1, property2'
                ),
                $ex->getMessage()
            );
        }
    }

    private function addResponse($cloud, $flowData, $json)
    {
        $cloudData = new AspectDataDictionary($cloud, ['cloud' => $json]);
        $flowData->setElementData($cloudData);
    }
}
