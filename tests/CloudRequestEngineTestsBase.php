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

use fiftyone\pipeline\cloudrequestengine\CloudRequestException;
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use PHPUnit\Framework\TestCase;

class CloudRequestEngineTestsBase extends TestCase
{
    public const expectedUrl = 'https://cloud.51degrees.com/api/v4/resource_key.json';
    public const jsonResponse = '{"device":{"value":"1"}}';
    public const evidenceKeysResponse = '["query.User-Agent"]';
    public const accessiblePropertiesResponse =
            '{"Products": {"device": {"DataTier": "tier","Properties": [{"Name": "value","Type": "String","Category": "Device"}]}}}';
    public const invalidKey = 'invalidkey';
    public const invalidKeyMessage = '58982060: ' . self::invalidKey . ' not a valid resource key';
    public const invalidKeyResponse = '{ "errors":["' . self::invalidKeyMessage . '"]}';
    public const noDataKey = 'nodatakey';
    public const noDataKeyResponse = '{}';
    public const noDataKeyMessageComplete = "Error returned from 51Degrees cloud service: 'No data in response " .
        "from cloud service at https://cloud.51degrees.com/api/v4/accessibleProperties?resource=nodatakey'";
    public const accessibleSubPropertiesResponse =
        "{\n" .
        "    \"Products\": {\n" .
        "        \"device\": {\n" .
        "            \"DataTier\": \"CloudV4TAC\",\n" .
        "            \"Properties\": [\n" .
        "                {\n" .
        "                    \"Name\": \"IsMobile\",\n" .
        "                        \"Type\": \"Boolean\",\n" .
        "                        \"Category\": \"Device\"\n" .
        "                },\n" .
        "                {\n" .
        "                    \"Name\": \"IsTablet\",\n" .
        "                        \"Type\": \"Boolean\",\n" .
        "                        \"Category\": \"Device\"\n" .
        "                }\n" .
        "            ]\n" .
        "        },\n" .
        "        \"devices\": {\n" .
        "            \"DataTier\": \"CloudV4TAC\",\n" .
        "            \"Properties\": [\n" .
        "                {\n" .
        "                    \"Name\": \"Devices\",\n" .
        "                    \"Type\": \"Array\",\n" .
        "                    \"Category\": \"Unspecified\",\n" .
        "                    \"ItemProperties\": [\n" .
        "                        {\n" .
        "                            \"Name\": \"IsMobile\",\n" .
        "                            \"Type\": \"Boolean\",\n" .
        "                            \"Category\": \"Device\"\n" .
        "                        },\n" .
        "                        {\n" .
        "                            \"Name\": \"IsTablet\",\n" .
        "                            \"Type\": \"Boolean\",\n" .
        "                            \"Category\": \"Device\"\n" .
        "                        }\n" .
        "                    ]\n" .
        "                }\n" .
        "            ]\n" .
        "        }\n" .
        "    }\n" .
        '}';
    public const resourceKey = 'resource_key';
    public const userAgent = 'iPhone';

    public static function getResponse()
    {
        $args = func_get_args();
        $url = $args[1];
        if (strpos($url, 'accessibleProperties') !== false) {
            if (strpos($url, 'subpropertieskey') !== false) {
                return self::accessibleSubPropertiesResponse;
            }
            if (strpos($url, self::invalidKey)) {
                throw new CloudRequestException(self::invalidKeyResponse);
            }
            if (strpos($url, self::noDataKey)) {
                throw new CloudRequestException(self::noDataKeyMessageComplete);
            }

            return self::accessiblePropertiesResponse;
        }

        if (strpos($url, 'evidencekeys') !== false) {
            return self::evidenceKeysResponse;
        }

        if (strpos($url, 'resource_key.json') !== false) {
            return self::jsonResponse;
        }

        throw new CloudRequestException("this should not have been called with the URL '" . $url . "'");
    }

    protected function propertiesContainName($properties, $name)
    {
        foreach ($properties as $property) {
            if (strcasecmp($property['name'], $name) === 0) {
                return true;
            }
        }

        return false;
    }

    protected function mockHttp()
    {
        $client = $this->createMock(HttpClient::class);
        $client->method('makeCloudRequest')
            ->will($this->returnCallback('fiftyone\\pipeline\\cloudrequestengine\\tests\\CloudRequestEngineTestsBase::getResponse'));

        return $client;
    }
}
