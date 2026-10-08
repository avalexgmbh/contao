<?php

/**
 * avalex Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 * @copyright Copyright (c) 2026, avalex GmbH
 */


namespace numero2\AvalexBundle\Api;

use Composer\InstalledVersions;
use numero2\AvalexBundle\Exception\AvalexApiException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;


class AvalexClient {


    /**
     * API hosts
     */
    public const API_HOST = 'https://avalex.de';
    public const API_HOST_FALLBACK = 'https://proxy.avalex.de';

    /**
     * Name of this package, used to determine the installed version
     */
    public const PACKAGE_NAME = 'avalexgmbh/contao';

    /**
     * Added to the major version of this bundle, the 2.x releases
     * already sent "3.0.1" so the version sent must not fall behind
     */
    public const MAJOR_VERSION_OFFSET = 1;

    /**
     * Version sent along with each request if the installed one
     * can't be determined (e.g. dev-master), offset already included
     */
    public const FALLBACK_VERSION = '4.0.1';

    /**
     * Endpoint returning the available languages and texts of a domain
     */
    public const ENDPOINT_LANGUAGES = '/avx-get-domain-langs';

    /**
     * Request timeout in seconds
     */
    private const TIMEOUT = 5;

    /**
     * Time (in seconds) an unreachable host will be skipped
     */
    private const HOST_RETRY_AFTER = 60;


    private HttpClientInterface $httpClient;

    /**
     * Timestamps of the last failed connection attempt per host
     */
    private array $unreachableHosts = [];

    /**
     * Installed version of this bundle
     */
    private ?string $bundleVersion = null;


    public function __construct( HttpClientInterface $httpClient ) {

        $this->httpClient = $httpClient;
    }


    /**
     * Forgets the unreachable hosts, called by the kernel between
     * requests in long-running processes
     */
    public function reset(): void {

        $this->unreachableHosts = [];
    }


    /**
     * Returns the available languages and the texts available per language
     *
     * @param string $apiKey
     * @param string $domain
     *
     * @return array<string, array<string, mixed>> e.g. ['de' => ['datenschutzerklaerung' => …, 'impressum' => …]]
     *
     * @throws \numero2\AvalexBundle\Exception\AvalexApiException
     */
    public function getLanguages( string $apiKey, string $domain ): array {

        $response = $this->request(self::ENDPOINT_LANGUAGES, $apiKey, $domain, 'de');

        $languages = json_decode($response, true);

        if( !\is_array($languages) ) {
            throw new AvalexApiException(sprintf('Unexpected response from avalex endpoint %s', self::ENDPOINT_LANGUAGES));
        }

        return $languages;
    }


    /**
     * Returns the text of the given endpoint in the given language
     *
     * @param string $endpoint
     * @param string $apiKey
     * @param string $domain
     * @param string $language
     *
     * @return string
     *
     * @throws \numero2\AvalexBundle\Exception\AvalexApiException
     */
    public function getText( string $endpoint, string $apiKey, string $domain, string $language ): string {

        return $this->request($endpoint, $apiKey, $domain, $language);
    }


    /**
     * Sends a request to the API, falls back to the proxy host if the main host can't be reached or fails
     *
     * @param string $endpoint
     * @param string $apiKey
     * @param string $domain
     * @param string $language
     *
     * @return string
     *
     * @throws \numero2\AvalexBundle\Exception\AvalexApiException
     */
    private function request( string $endpoint, string $apiKey, string $domain, string $language ): string {

        $query = [
            'apikey' => $apiKey
        ,   'domain' => $domain
        ,   'version' => $this->getBundleVersion()
        ,   'lang' => $language
        ];

        $lastException = null;

        foreach( [self::API_HOST, self::API_HOST_FALLBACK] as $host ) {

            // do not wait for the timeout of an unreachable host over and over again
            if( isset($this->unreachableHosts[$host]) && (time() - $this->unreachableHosts[$host]) < self::HOST_RETRY_AFTER ) {
                continue;
            }

            try {

                $response = $this->httpClient->request('GET', $host . $endpoint, [
                    'query' => $query
                ,   'timeout' => self::TIMEOUT
                ,   'max_duration' => self::TIMEOUT
                ]);

                $statusCode = $response->getStatusCode();
                $content = $response->getContent(false);

            } catch( TransportExceptionInterface $e ) {

                // host not reachable, try the next one
                $this->unreachableHosts[$host] = time();
                $lastException = $e;
                continue;
            }

            unset($this->unreachableHosts[$host]);

            if( $statusCode !== 200 ) {

                $e = new AvalexApiException(trim(sprintf('Error while retrieving data from avalex (%s %d) %s', $endpoint, $statusCode, $this->getErrorDetails($content, $apiKey))), $statusCode);

                // the host itself has a problem, try the next one
                if( $statusCode >= 500 ) {
                    $lastException = $e;
                    continue;
                }

                throw $e;
            }

            return $content;
        }

        if( $lastException instanceof AvalexApiException ) {
            throw $lastException;
        }

        throw new AvalexApiException(sprintf('Error while retrieving data from avalex (%s) %s', $endpoint, $this->maskApiKey($lastException?->getMessage() ?? 'API hosts not reachable', $apiKey)), 0, $lastException);
    }


    /**
     * Returns the installed version of this bundle (major version raised
     * by the offset) in the format x[.y.z] expected by the API
     *
     * @return string
     */
    private function getBundleVersion(): string {

        if( $this->bundleVersion !== null ) {
            return $this->bundleVersion;
        }

        $version = null;

        try {
            $version = InstalledVersions::getPrettyVersion(self::PACKAGE_NAME);
        } catch( \OutOfBoundsException $e ) {
            // package not installed via composer
        }

        // branches like "dev-master" or "3.0.x-dev" do not have a usable version
        if( $version !== null && preg_match('/^v?(\d+)((?:\.\d+\.\d+)?)$/', $version, $matches) ) {
            return $this->bundleVersion = ((int) $matches[1] + self::MAJOR_VERSION_OFFSET) . $matches[2];
        }

        return $this->bundleVersion = self::FALLBACK_VERSION;
    }


    /**
     * Extracts a short error description from the response body, error pages
     * of the API are full HTML documents which are of no use in the log
     *
     * @param string $content
     * @param string $apiKey
     *
     * @return string
     */
    private function getErrorDetails( string $content, string $apiKey ): string {

        $content = trim($content);

        if( $content === '' || preg_match('/<(html|body|script|style|div)\b/i', $content) ) {
            return '';
        }

        $content = preg_replace('/\s+/', ' ', strip_tags($content));

        if( mb_strlen($content) > 200 ) {
            $content = mb_substr($content, 0, 200).'…';
        }

        return $this->maskApiKey($content, $apiKey);
    }


    /**
     * Makes sure the API key does not end up in log files
     *
     * @param string $message
     * @param string $apiKey
     *
     * @return string
     */
    private function maskApiKey( string $message, string $apiKey ): string {

        if( $apiKey === '' ) {
            return $message;
        }

        return str_replace([$apiKey, rawurlencode($apiKey)], substr($apiKey, 0, 4).'***', $message);
    }
}
