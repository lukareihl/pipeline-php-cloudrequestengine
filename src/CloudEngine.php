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

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\engines\AspectDataDictionary;
use fiftyone\pipeline\engines\CloudEngineBase;

/**
 * This is a template for all 51Degrees cloud engines.
 * It requires the 51Degrees cloudRequestEngine to be placed in a
 * pipeline before it. It takes that raw JSON response and
 * parses it to extract the device part.
 * It also uses this data to generate a list of properties and an evidence key filter.
 */
class CloudEngine extends CloudEngineBase
{
    /**
     * This should be overridden.
     *
     * @var string
     */
    public $dataKey = 'CloudEngineBase';

    /**
     * Callback called when an engine is added to a pipeline
     * In this case sets up the properties list for the element from
     * data in the CloudRequestEngine.
     *
     * @param \fiftyone\pipeline\core\Pipeline $pipeline
     * @return void
     * @throws \Exception
     */
    public function onRegistration($pipeline)
    {
        if (!isset($pipeline->flowElementsList['cloud'])) {
            throw new \Exception('CloudRequestEngine needs to be placed before cloud elements in Pipeline');
        }

        /** @var \fiftyone\pipeline\cloudrequestengine\CloudRequestEngine $engine */
        $engine = $pipeline->flowElementsList['cloud'];

        $this->properties = $engine->flowElementProperties[$this->dataKey];
    }

    /**
     * @param \fiftyone\pipeline\core\FlowData $flowData
     * @return void
     */
    public function processInternal($flowData)
    {
        $cloudData = $flowData->get('cloud')->get('cloud');

        if ($cloudData) {
            $cloudData = json_decode($cloudData, true);

            $result = [];

            foreach ($cloudData[$this->dataKey] as $key => $value) {
                if (isset($cloudData[$this->dataKey][$key . 'nullreason'])) {
                    $result[$key] = new AspectPropertyValue($cloudData[$this->dataKey][$key . 'nullreason']);
                } else {
                    $result[$key] = new AspectPropertyValue(null, $value);
                }
            }

            $data = new AspectDataDictionary($this, $result);

            $flowData->setElementData($data);
        }
    }
}
