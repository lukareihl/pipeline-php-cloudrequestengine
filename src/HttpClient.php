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

class HttpClient
{
    /**
     * Internal helper method to make a cloud request
     * uses CURL if available, falls back to file_get_contents.
     *
     * Returns an associative array with data and error properties. 'error'
     * contains any errors from the request, 'data' contains the response
     *
     * @param string $type Method use to send HTTP request
     * @param string $url
     * @param null|string $content Data to be sent in the post body
     * @param null|string $originHeader The value to use for the Origin header
     * @return bool|string
     */
    public function makeCloudRequest($type, $url, $content, $originHeader)
    {
        $headerText = '';
        if (!empty($originHeader)) {
            $headerText .= 'Origin: ' . $originHeader;
        }

        if (!function_exists('curl_version')) {
            $context = stream_context_create([
                'http' => [
                    'method' => $type,
                    'ignore_errors' => true,
                    'header' => $headerText,
                    'content' => $content
                ]
            ]);

            $data = @file_get_contents($url, false, $context);
            $statusCode = $this->getHttpCode($http_response_header);

            // Validate cloud response for errors.
            $this->validateResponse($data, $statusCode, $http_response_header, $url);

            return $data;
        }

        /** @var resource $ch */
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        if (!empty($originHeader)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [$headerText]);
        }
        if (!empty($type) && strcasecmp($type, 'POST') === 0) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // Get headers length
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        // Get response header and body using header length
        $headerStr = substr($data, 0, $headerSize);
        $bodyStr = substr($data, $headerSize);
        $responseHeaders = explode("\r\n", $headerStr);

        // Validate cloud response body for errors.
        $this->validateResponse($bodyStr, $httpCode, $responseHeaders, $url);

        curl_close($ch);

        return $bodyStr;
    }

    /**
     * Parser function to get formatted headers.
     *
     * @param array<string> $headers
     * @return array<string, string>
     */
    public function parseHeaders($headers)
    {
        $parsed = [];

        foreach ($headers as $header) {
            $parts = explode(':', $header, 2);

            if (isset($parts[1])) {
                $parsed[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $parsed;
    }

    /**
     * @param array<string> $http_response_header
     * @return int
     */
    private function getHttpCode($http_response_header)
    {
        if (is_array($http_response_header)) {
            $parts = explode(' ', $http_response_header[0]);
            if (count($parts) > 1) { // HTTP/1.0 <code> <text>
                return intval($parts[1]);
            } // Get code
        }

        return 0;
    }

    /**
     * @param string $cloudResponse
     * @param int $httpStatusCode
     * @param array<string> $httpResponseHeaders
     * @param string $url
     * @return void
     * @throws \fiftyone\pipeline\cloudrequestengine\CloudRequestException
     */
    private function validateResponse($cloudResponse, $httpStatusCode, $httpResponseHeaders, $url)
    {
        $message = null;

        if ($cloudResponse) {
            $json = json_decode($cloudResponse, true);

            if (isset($json['errors']) && count($json['errors']) > 0) {
                $message = implode(',', $json['errors']);
            } elseif ($httpStatusCode !== 200) {
                // If there were no errors returned but the response code was non
                // success then throw an exception.
                $message = sprintf(Constants::MESSAGE_ERROR_CODE_RETURNED, $url, $httpStatusCode, $cloudResponse);
            }
        } else {
            // If there were no errors but there was also no other data
            // in the response then add an explanation to the list of
            // messages.
            $message = sprintf(Constants::MESSAGE_NO_DATA_IN_RESPONSE, $url);
        }

        if ($message !== null) {
            // Get the response headers.
            $responseHeaders = $this->parseHeaders($httpResponseHeaders);
            $cloudError = sprintf(Constants::EXCEPTION_CLOUD_ERROR, $message);

            throw new CloudRequestException($cloudError, $httpStatusCode, $responseHeaders);
        }
    }
}
