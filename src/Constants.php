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

class Constants
{
    /**
     * Environment variable to set cloud endpoint.
     */
    public const FOD_CLOUD_API_URL = 'FOD_CLOUD_API_URL';

    /**
     * Default base url.
     */
    public const BASE_URL_DEFAULT = 'https://cloud.51degrees.com/api/v4/';

    /**
     * No Data in response message to be set in exception when cloud neither
     * return any data nor any error messages.
     */
    public const MESSAGE_NO_DATA_IN_RESPONSE = 'No data in response from cloud service at %s';

    /**
     * Message when multiple errors are returned from cloud service.
     */
    public const EXCEPTION_CLOUD_ERRORS_MULTIPLE =
            'Multiple errors returned from 51Degrees cloud service. See inner ' .
            'exceptions for details.';

    /**
     * Message when single error is returned from cloud service.
     */
    public const EXCEPTION_CLOUD_ERROR =
            "Error returned from 51Degrees cloud service: '%s'";

    /**
     * Error message when non-success status is returned.
     */
    public const MESSAGE_ERROR_CODE_RETURNED = "Cloud service at '%s' returned status code '%s' with content %s";

    /**
     * Evidence key seperator.
     */
    public const EVIDENCE_SEPERATOR = '.';

    /**
     * Used to prefix evidence that is obtained from HTTP headers.
     */
    public const EVIDENCE_HTTPHEADER_PREFIX = 'header';

    /**
     * Used to prefix evidence that is obtained from HTTP bookies.
     */
    public const EVIDENCE_COOKIE_PREFIX = 'cookie';

    /**
     * Used to prefix evidence that is obtained from an HTTP request's query
     * string or is passed into the pipeline for off-line processing.
     */
    public const EVIDENCE_QUERY_PREFIX = 'query';

    /**
     * other evidence constant.
     */
    public const EVIDENCE_OTHER = 'other';

    /**
     * warning message to be shown for conflicted evidences.
     */
    public const WARNING_MESSAGE = "'%s=>%s' evidence conflicts with ";

    /**
     * The suffix that is used to identify a TAC in evidence.
     *
     * @url https://en.wikipedia.org/wiki/Type_Allocation_Code
     */
    public const EVIDENCE_TAC_SUFFIX = 'tac';

    /**
     * The complete key for supplying a TAC as evidence.
     */
    public const EVIDENCE_QUERY_TAC_KEY =
        Constants::EVIDENCE_QUERY_PREFIX .
        Constants::EVIDENCE_SEPERATOR .
        Constants::EVIDENCE_TAC_SUFFIX;

    /**
     * The suffix that is used to identify a native model name in evidence.
     * This is the text returned by
     * https://developer.android.com/reference/android/os/Build#MODEL
     * for Android devices and by
     * https://gist.github.com/soapyigu/c99e1f45553070726f14c1bb0a54053b#file-machinename-swift
     * for iOS devices.
     */
    public const EVIDENCE_NATIVE_MODEL_SUFFIX = 'nativemodel';

    /**
     * The complete key for supplying a native model name as evidence.
     */
    public const EVIDENCE_QUERY_NATIVE_MODEL_KEY =
        Constants::EVIDENCE_QUERY_PREFIX .
        Constants::EVIDENCE_SEPERATOR .
        Constants::EVIDENCE_NATIVE_MODEL_SUFFIX;
}
