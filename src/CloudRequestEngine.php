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

namespace fiftyone\pipeline\cloudrequestengine;

use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;
use fiftyone\pipeline\engines\AspectDataDictionary;
use fiftyone\pipeline\engines\Engine;

/**
 * Engine that makes a call to the 51Degrees cloud service
 * Returns raw JSON as a "cloud" property under "cloud" dataKey.
 */
class CloudRequestEngine extends Engine
{
    /**
     * @var string
     */
    public $dataKey = 'cloud';

    /**
     * Default base url.
     *
     * @var string
     */
    public $baseURL;

    /**
     * @var null|string
     */
    public $cloudRequestOrigin;

    /**
     * @var array<int|string, array<string, mixed>>
     */
    public $flowElementProperties = [];

    /**
     * @var string
     */
    public $resourceKey;

    /**
     * @var \fiftyone\pipeline\cloudrequestengine\HttpClient
     */
    private $httpClient;

    /**
     * @var array<mixed>
     */
    private $evidenceKeys;

    /**
     * Constructor for CloudRequestEngine.
     * Settings should contain a resourceKey and optionally:
     *  1) a cloudEndPoint to overwrite the default baseurl
     *  2) an cloudRequestOrigin to use when sending requests to cloud.
     *
     * @param array<string, mixed> $settings
     * @throws \Exception
     */
    public function __construct($settings)
    {
        if (!isset($settings['resourceKey'])) {
            throw new \Exception('CloudRequestEngine needs a resource key');
        }

        $this->resourceKey = $settings['resourceKey'];

        if (isset($settings['cloudEndPoint'])) {
            $this->baseURL = $settings['cloudEndPoint'];
        } else {
            // Check if base URL is set via environment variable
            $envVarURL = getenv(Constants::FOD_CLOUD_API_URL);
            if (!empty($envVarURL)) {
                $this->baseURL = $envVarURL;
            } else {
                // Use default if nothing else is set
                $this->baseURL = Constants::BASE_URL_DEFAULT;
            }
        }

        // Make sure the base URL end with '/'
        $length = strlen($this->baseURL);
        if ($length > 0 && substr($this->baseURL, $length - 1) !== '/') {
            $this->baseURL = $this->baseURL . '/';
        }

        $this->httpClient = $settings['httpClient'] ?? new HttpClient();

        $this->cloudRequestOrigin = $settings['cloudRequestOrigin'] ?? null;

        $this->flowElementProperties = $this->getEngineProperties();

        $this->evidenceKeys = $this->getEvidenceKeys();
    }

    /**
     * Instance of EvidenceKeyFilter based on the evidence keys fetched
     * from the cloud service by the private getEvidenceKeys() method.
     *
     * @return BasicListEvidenceKeyFilter
     */
    public function getEvidenceKeyFilter()
    {
        return new BasicListEvidenceKeyFilter($this->evidenceKeys);
    }

    /**
     * Processing function for the CloudRequestEngine
     * Makes a request to the cloud service with the supplied resource key
     * and evidence and returns a JSON object that is then parsed by cloud engines
     * placed later in the pipeline.
     *
     * @param \fiftyone\pipeline\core\FlowData $flowData
     */
    public function processInternal($flowData)
    {
        $url = $this->baseURL . $this->resourceKey . '.json?';

        $content = http_build_query($this->getContent($flowData));

        $result = $this->httpClient->makeCloudRequest('POST', $url, $content, $this->cloudRequestOrigin);

        $data = new AspectDataDictionary($this, ['cloud' => $result]);

        $flowData->setElementData($data);
    }

    /**
     * Generate the Content to send in the POST request. The evidence keys
     * e.g. 'query.' and 'header.' have an order of precedence. These are
     * added to the evidence in reverse order, if there is conflict then
     * the queryData value is overwritten.
     *
     * 'query.' evidence should take precedence over all other evidence.
     * If there are evidence keys other than 'query.' that conflict then
     * this is unexpected so a warning will be logged.
     *
     * @param \fiftyone\pipeline\core\FlowData $flowData
     * @return array<string, mixed>
     */
    public function getContent($flowData)
    {
        $queryData = [];

        $evidence = $flowData->evidence->getAll();

        // Add evidence in reverse alphabetical order, excluding special keys.
        $queryData = $this->addQueryData($queryData, $evidence, $this->getSelectedEvidence($evidence, Constants::EVIDENCE_OTHER));
        // Add cookie evidence.
        $queryData = $this->addQueryData($queryData, $evidence, $this->getSelectedEvidence($evidence, Constants::EVIDENCE_COOKIE_PREFIX));
        // Add header evidence.
        $queryData = $this->addQueryData($queryData, $evidence, $this->getSelectedEvidence($evidence, Constants::EVIDENCE_HTTPHEADER_PREFIX));
        // Add query evidence.
        return $this->addQueryData($queryData, $evidence, $this->getSelectedEvidence($evidence, Constants::EVIDENCE_QUERY_PREFIX));
    }

    /**
     * Add query data to the evidence.
     *
     * @param array<string, mixed> $queryData Destination array to add query data to
     * @param array<string, mixed> $allEvidence All evidence in the flow data. This is used to report which evidence keys are conflicting.
     * @param array<string, mixed> $evidence Evidence to add to the query data
     * @return array<string, mixed>
     */
    public function addQueryData($queryData, $allEvidence, $evidence)
    {
        foreach ($evidence as $evidenceKey => $evidenceValue) {
            // Get the key parts
            $evidenceKeyParts = explode(Constants::EVIDENCE_SEPERATOR, $evidenceKey);
            $prefix = strtolower($evidenceKeyParts[0]);
            $suffix = strtolower(end($evidenceKeyParts));

            // Check and add the evidence to the query parameters.
            if (array_key_exists($suffix, $queryData)) {
                // The queryParameter exists already.

                // Get the conflicting pieces of evidence and then log a
                // warning, if the evidence prefix is not query. Otherwise a
                // warning is not needed as query evidence is expected
                // to overwrite any existing evidence with the same suffix.
                if (strcmp($prefix, Constants::EVIDENCE_QUERY_PREFIX) !== 0) {
                    $conflicts = [];
                    $conflictStr = '';

                    foreach ($allEvidence as $key => $value) {
                        if (strcasecmp($key, $evidenceKey) !== 0 && stripos($key, $suffix) !== false) {
                            $conflicts[$key] = $value;
                        }
                    }
                    $warningMessage = sprintf(Constants::WARNING_MESSAGE, $evidenceKey, $evidenceValue);

                    foreach ($conflicts as $key => $value) {
                        if (!empty($conflictStr)) {
                            $conflictStr .= ', ';
                        }
                        $conflictStr .= sprintf('%s=>%s', $key, $value);
                    }
                    if (!empty($conflictStr)) {
                        trigger_error($warningMessage . $conflictStr, E_USER_WARNING);
                    }
                }

                // Overwrite the existing queryParameter value.
            }
            $queryData[$suffix] = $evidenceValue;
        }

        return $queryData;
    }

    /**
     * Get evidence with specified prefix.
     * @param array<string, mixed> $evidence All evidence in the flow data
     * @param string $type Required evidence key prefix
     * @return array<string, mixed>
     */
    public function getSelectedEvidence($evidence, $type)
    {
        $selectedEvidence = [];
        if (strcmp($type, Constants::EVIDENCE_OTHER) === 0) {
            foreach ($evidence as $key => $value) {
                if (
                    !$this->keyHasPrefix($key, Constants::EVIDENCE_QUERY_PREFIX) &&
                    !$this->keyHasPrefix($key, Constants::EVIDENCE_HTTPHEADER_PREFIX) &&
                    !$this->keyHasPrefix($key, Constants::EVIDENCE_COOKIE_PREFIX)
                ) {
                    $selectedEvidence[$key] = $value;
                }
            }
            krsort($selectedEvidence);
        } else {
            foreach ($evidence as $key => $value) {
                if ($this->keyHasPrefix($key, $type)) {
                    $selectedEvidence[$key] = $value;
                }
            }
        }

        return $selectedEvidence;
    }

    /**
     * Check that the key of a KeyValuePair has the given prefix.
     *
     * @param string $itemKey Key to check
     * @param string $prefix The prefix to check for
     * @return bool
     */
    public function keyHasPrefix($itemKey, $prefix)
    {
        $key = explode(Constants::EVIDENCE_SEPERATOR, $itemKey);

        return strcasecmp($key[0], $prefix) == 0;
    }

    /**
     * Internal function for getting evidence keys used by cloud engines.
     *
     * @return array<mixed>
     */
    private function getEvidenceKeys()
    {
        $evidenceKeyRequest = $this->httpClient->makeCloudRequest(
            'GET',
            $this->baseURL . 'evidencekeys',
            null,
            $this->cloudRequestOrigin
        );

        return json_decode($evidenceKeyRequest, true);
    }

    /**
     * Internal method to get properties for cloud engines from the cloud service.
     *
     * @return array<int|string, array<string, mixed>>
     */
    private function getEngineProperties()
    {
        // Get properties for all engines

        $propertiesURL = $this->baseURL . 'accessibleProperties?resource=' . $this->resourceKey;

        $properties = $this->httpClient->makeCloudRequest(
            'GET',
            $propertiesURL,
            null,
            $this->cloudRequestOrigin
        );

        $properties = json_decode($properties, true);

        $properties = $this->lowerCaseArrayKeys($properties);

        $flowElementProperties = [];

        // Change indexes to be by name
        foreach ($properties['products'] as $dataKey => $elementProperties) {
            foreach ($elementProperties['properties'] as $meta) {
                $flowElementProperties[$dataKey][strtolower($meta['name'])] = $meta;
            }
        }

        return $flowElementProperties;
    }

    /**
     * Internal helper method to lowercase keys returned from the
     * cloud service.
     *
     * @param array<string, mixed> $arr
     * @return array<string, mixed>
     */
    private function lowerCaseArrayKeys($arr)
    {
        return array_map(
            function ($item) {
                if (is_array($item)) {
                    $item = $this->lowerCaseArrayKeys($item);
                }

                return $item;
            },
            array_change_key_case($arr)
        );
    }
}
