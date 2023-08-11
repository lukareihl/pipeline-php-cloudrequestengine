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

use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestException;
use fiftyone\pipeline\core\PipelineBuilder;

class CloudResponse extends CloudRequestEngineTestsBase
{
    /**
     * Test cloud request engine adds correct information to post request
     * and returns the response in the ElementData.
     */
    public function testProcess()
    {
        $httpClient = $this->mockHttp();

        $engine = new CloudRequestEngine([
            'resourceKey' => CloudResponse::resourceKey,
            'httpClient' => $httpClient
        ]);

        $pipeline = (new PipelineBuilder())->add($engine)->build();
        $data = $pipeline->createFlowData();
        $data->evidence->set('query.User-Agent', CloudResponse::userAgent);

        $data->process();

        $result = $data->getFromElement($engine)->cloud;
        $this->assertSame(CloudResponse::jsonResponse, $result);

        $jsonObj = json_decode($result, true);
        $this->assertEquals(1, $jsonObj['device']['value']);
    }

    /**
     * Verify that the CloudRequestEngine can correctly parse a
     * response from the accessible properties endpoint that contains
     * meta-data for sub-properties.
     */
    public function testSubProperties()
    {
        $httpClient = $this->mockHttp();

        $engine = new CloudRequestEngine([
            'resourceKey' => 'subpropertieskey',
            'httpClient' => $httpClient
        ]);

        $this->assertCount(2, $engine->flowElementProperties);

        $deviceProperties = $engine->flowElementProperties['device'];
        $this->assertCount(2, $deviceProperties);
        $this->assertTrue($this->propertiesContainName($deviceProperties, 'IsMobile'));
        $this->assertTrue($this->propertiesContainName($deviceProperties, 'IsTablet'));
        $devicesProperties = $engine->flowElementProperties['devices'];
        $this->assertCount(1, $devicesProperties);
        $this->assertTrue(isset($devicesProperties['devices']));
        $this->assertTrue($this->propertiesContainName($devicesProperties['devices']['itemproperties'], 'IsMobile'));
        $this->assertTrue($this->propertiesContainName($devicesProperties['devices']['itemproperties'], 'IsTablet'));
    }

    /**
     * Test cloud request engine handles errors from the cloud service
     * as expected.
     * An exception should be thrown by the cloud request engine
     * containing the errors from the cloud service
     * and the pipeline is configured to throw any exceptions up
     * the stack.
     * We also check that the exception message includes the content
     * from the JSON response.
     */
    public function testValidateErrorHandlingInvalidResourceKey()
    {
        $httpClient = $this->mockHttp();

        $exception = null;

        try {
            $engine = new CloudRequestEngine([
                'resourceKey' => CloudResponse::invalidKey,
                'httpClient' => $httpClient
            ]);
        } catch (CloudRequestException $ex) {
            $exception = $ex;
        }

        $this->assertNotNull('Expected exception to occur', $exception);
        $this->assertStringContainsString(CloudResponse::invalidKeyMessage, $exception->getMessage());
    }

    /**
     * Test cloud request engine handles a lack of data from the
     * cloud service as expected.
     * An exception should be thrown by the cloud request engine
     * containing the errors from the cloud service
     * and the pipeline is configured to throw any exceptions up
     * the stack.
     * We also check that the exception message includes the content
     * from the JSON response.
     */
    public function testValidateErrorHandlingNoData()
    {
        $httpClient = $this->mockHttp();

        $exception = null;

        try {
            $engine = new CloudRequestEngine([
                'resourceKey' => CloudResponse::noDataKey,
                'httpClient' => $httpClient
            ]);
        } catch (CloudRequestException $ex) {
            $exception = $ex;
        }

        $this->assertNotNull('Expected exception to occur', $exception);
        $this->assertSame($exception->getMessage(), CloudResponse::noDataKeyMessageComplete);
    }
}
