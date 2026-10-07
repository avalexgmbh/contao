<?php

/**
 * avalex Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 * @copyright Copyright (c) 2026, avalex GmbH
 */


namespace numero2\AvalexBundle\Util;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use numero2\AvalexBundle\Api\AvalexClient;
use numero2\AvalexBundle\Exception\AvalexApiException;
use Psr\Log\LoggerInterface;


class AvalexUtil {


    /**
     * Frontend module / content element types and their corresponding API endpoints
     */
    public const TYPES = [
        'avalex_privacy_policy' => '/avx-datenschutzerklaerung'
    ,   'avalex_imprint' => '/avx-impressum'
    ,   'avalex_terms_conditions' => '/avx-bedingungen'
    ,   'avalex_cancellation_policy' => '/avx-widerruf'
    ];

    /**
     * Tables containing avalex records
     */
    public const TABLES = ['tl_module', 'tl_content'];

    /**
     * Texts older than this (in seconds) will be updated
     */
    public const MAX_AGE = 6 * 3600;

    /**
     * Time (in seconds) to wait after a failed update before the frontend tries to fetch missing texts again
     */
    public const RETRY_AFTER = 300;

    /**
     * Language used if the text is not available in the requested language
     */
    public const FALLBACK_LANGUAGE = 'de';

    /**
     * Definition of the fields added to each of the tables
     */
    public const DCA_FIELDS = [
        'avalex_domain' => [
            'inputType'         => 'text'
        ,   'translate'         => false
        ,   'eval'              => ['mandatory'=>true, 'maxlength'=>255, 'decodeEntities'=>true, 'placeholder'=>'example.com', 'tl_class'=>'w50']
        ,   'sql'               => "varchar(255) NOT NULL default ''"
        ]
    ,   'avalex_apikey' => [
            'inputType'         => 'text'
        ,   'translate'         => false
        ,   'eval'              => ['mandatory'=>true, 'maxlength'=>255, 'decodeEntities'=>true, 'tl_class'=>'w50']
        ,   'sql'               => "varchar(255) NOT NULL default ''"
        ]
    ,   'avalex_cache' => [
            'eval'              => ['doNotCopy'=>true, 'versionize'=>false]
        ,   'sql'               => "mediumblob NULL"
        ]
    ];


    private AvalexClient $client;

    private Connection $connection;

    private LoggerInterface $logger;

    /**
     * Either Contao\CoreBundle\Cache\CacheTagManager or Contao\CoreBundle\Cache\EntityCacheTags (Contao 5.3)
     */
    private ?object $cacheTagManager;

    /**
     * Available languages per API key and domain, cached for the current request
     */
    private array $languages = [];

    /**
     * Fetched texts per API key, domain, endpoint and language, cached for the current request
     */
    private array $texts = [];


    public function __construct( AvalexClient $client, Connection $connection, LoggerInterface $logger, ?object $cacheTagManager=null ) {

        $this->client = $client;
        $this->connection = $connection;
        $this->logger = $logger;
        $this->cacheTagManager = $cacheTagManager;
    }


    /**
     * Checks if the given type is handled by this bundle
     *
     * @param string|null $type
     *
     * @return bool
     */
    public static function isAvalexType( ?string $type ): bool {

        return $type !== null && \array_key_exists($type, self::TYPES);
    }


    /**
     * Checks if the given record has an API key and a domain
     *
     * @param array $record
     *
     * @return bool
     */
    public static function isConfigured( array $record ): bool {

        return trim((string) ($record['avalex_apikey'] ?? '')) !== '' && trim((string) ($record['avalex_domain'] ?? '')) !== '';
    }


    /**
     * Clears the data cached for the current request, called by the
     * kernel between requests in long-running processes
     */
    public function reset(): void {

        $this->languages = [];
        $this->texts = [];
    }


    /**
     * Returns all avalex records of the given table
     *
     * @param string $table
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll( string $table ): array {

        $this->assertValidTable($table);

        return $this->connection->fetchAllAssociative(
            "SELECT * FROM $table WHERE type IN (?) ORDER BY id"
        ,   [array_keys(self::TYPES)]
        ,   [ArrayParameterType::STRING]
        );
    }


    /**
     * Returns the avalex record with the given ID
     *
     * @param string $table
     * @param int $id
     *
     * @return array<string, mixed>|null
     */
    public function find( string $table, int $id ): ?array {

        $this->assertValidTable($table);

        $record = $this->connection->fetchAssociative("SELECT * FROM $table WHERE id=?", [$id]);

        if( !$record || !self::isAvalexType($record['type']) ) {
            return null;
        }

        return $record;
    }


    /**
     * Returns the cached text of the given record in the given language
     *
     * @param array $record
     * @param string|null $language
     *
     * @return string|null
     */
    public function getContent( array $record, ?string $language ): ?string {

        $cache = $this->getCache($record);

        if( empty($cache['content']) ) {
            return null;
        }

        // single-language cache (created by version < 2.0.0)
        if( \is_string($cache['content']) ) {
            return $cache['content'];
        }

        $language = strtolower(substr((string) $language, 0, 2));

        // make sure an empty text does not prevent the fallback
        $contents = array_filter($cache['content'], static fn( $text ) => \is_string($text) && trim($text) !== '');

        return $contents[$language]
            ?? $contents[self::FALLBACK_LANGUAGE]
            ?? reset($contents)
            ?: null;
    }


    /**
     * Returns the cached text of the given record, if there is no text at
     * all yet (e.g. the record has just been created) it will be fetched
     *
     * @param string $table
     * @param array $record
     * @param string|null $language
     *
     * @return string|null
     */
    public function getOrFetchContent( string $table, array $record, ?string $language ): ?string {

        $content = $this->getContent($record, $language);

        if( $content !== null ) {
            return $content;
        }

        // nothing to fetch as long as the record is not fully set up
        if( !self::isConfigured($record) ) {
            return null;
        }

        // do not hit the API on every single request if it's not working
        if( $this->hasRecentlyFailed($record) ) {
            return null;
        }

        try {

            $this->update($table, $record);

            return $this->getContent($record, $language);

        } catch( AvalexApiException $e ) {

            $this->logFailedUpdate($table, $record, $e);
        }

        return null;
    }


    /**
     * Returns the timestamp of the last successful update
     *
     * @param array $record
     *
     * @return int|null
     */
    public function getLastUpdate( array $record ): ?int {

        $cache = $this->getCache($record);

        return !empty($cache['date']) ? (int) $cache['date'] : null;
    }


    /**
     * Returns the timestamp of the last update attempt if it failed
     *
     * @param array $record
     *
     * @return int|null
     */
    public function getLastFailure( array $record ): ?int {

        $cache = $this->getCache($record);

        return !empty($cache['failed']) ? (int) $cache['failed'] : null;
    }


    /**
     * Checks if the texts of the given record need to be updated
     *
     * @param array $record
     *
     * @return bool
     */
    public function needsUpdate( array $record ): bool {

        $cache = $this->getCache($record);

        if( empty($cache['content']) || empty($cache['date']) ) {
            return true;
        }

        return (time() - (int) $cache['date']) > self::MAX_AGE;
    }


    /**
     * Updates the texts of the given record, a failed attempt will be
     * remembered while the existing texts are kept
     *
     * @param string $table
     * @param array $record Contains the new texts after a successful update
     *
     * @return bool Whether the texts have changed
     *
     * @throws \numero2\AvalexBundle\Exception\AvalexApiException
     */
    public function update( string $table, array &$record ): bool {

        $this->assertValidTable($table);

        if( !self::isAvalexType($record['type'] ?? null) ) {
            throw new \InvalidArgumentException(sprintf('Record %s.%s is not an avalex record', $table, $record['id'] ?? '?'));
        }

        try {

            return $this->fetchAndStore($table, $record);

        } catch( AvalexApiException $e ) {

            $this->markAsFailed($table, $record);

            throw $e;
        }
    }


    /**
     * Updates all records with outdated texts
     *
     * @param bool $force Update regardless of the age of the texts
     */
    public function updateAll( bool $force=false ): void {

        // make sure we do not store texts fetched by a previous run
        $this->reset();

        foreach( self::TABLES as $table ) {

            foreach( $this->findAll($table) as $record ) {

                if( !self::isConfigured($record) ) {
                    continue;
                }

                if( !$force && !$this->needsUpdate($record) ) {
                    continue;
                }

                try {
                    $this->update($table, $record);
                } catch( AvalexApiException $e ) {
                    $this->logFailedUpdate($table, $record, $e);
                }
            }
        }
    }


    /**
     * Writes a failed update of the given record to the system log
     *
     * @param string $table
     * @param array $record
     * @param \numero2\AvalexBundle\Exception\AvalexApiException $e
     */
    public function logFailedUpdate( string $table, array $record, AvalexApiException $e ): void {

        $this->logger->error(sprintf('Could not update avalex texts of %s.%s: %s', $table, $record['id'] ?? '?', $e->getMessage()));
    }


    /**
     * Fetches the texts of the given record in all available languages and stores them
     *
     * @param string $table
     * @param array $record Contains the new texts afterwards
     *
     * @return bool Whether the texts have changed
     *
     * @throws \numero2\AvalexBundle\Exception\AvalexApiException
     */
    private function fetchAndStore( string $table, array &$record ): bool {

        $apiKey = trim((string) ($record['avalex_apikey'] ?? ''));
        $domain = trim((string) ($record['avalex_domain'] ?? ''));

        if( $apiKey === '' || $domain === '' ) {
            throw new AvalexApiException(sprintf('No API key or domain configured for %s.%s', $table, $record['id'] ?? '?'));
        }

        $endpoint = self::TYPES[$record['type']];
        // the list of languages references the texts by their endpoint without the prefix
        $textName = substr($endpoint, \strlen('/avx-'));

        $contents = [];
        $isAvailable = false;

        foreach( $this->getLanguages($apiKey, $domain) as $language => $texts ) {

            if( !\is_array($texts) || !\array_key_exists($textName, $texts) ) {
                continue;
            }

            $isAvailable = true;

            $key = implode('|', [$apiKey, $domain, $endpoint, $language]);

            $text = $this->texts[$key] ??= $this->client->getText($endpoint, $apiKey, $domain, (string) $language);

            if( trim($text) === '' ) {
                continue;
            }

            // store the texts the same way they are looked up in getContent()
            $contents[strtolower(substr((string) $language, 0, 2))] ??= $text;
        }

        if( !$isAvailable ) {
            throw new AvalexApiException(sprintf('The text %s is not available for domain %s', $endpoint, $domain), 400);
        }

        if( empty($contents) ) {
            throw new AvalexApiException(sprintf('The API returned an empty text for %s (domain %s)', $endpoint, $domain));
        }

        $oldCache = $this->getCache($record);
        $hasChanged = ($oldCache['content'] ?? null) !== $contents;

        $cache = [
            'date' => time()
        ,   'source' => $this->getSourceHash($record)
        ,   'content' => $contents
        ];

        try {
            $json = json_encode($cache, JSON_THROW_ON_ERROR);
        } catch( \JsonException $e ) {
            throw new AvalexApiException(sprintf('The texts of %s (domain %s) could not be encoded: %s', $endpoint, $domain, $e->getMessage()), 0, $e);
        }

        $this->connection->update($table, ['avalex_cache' => $json], ['id' => (int) $record['id']]);

        $record['avalex_cache'] = $json;

        if( $hasChanged ) {
            $this->cacheTagManager?->invalidateTagsFor(['contao.db.'.$table.'.'.$record['id']]);
        }

        return $hasChanged;
    }


    /**
     * Remembers the time of the failed update while keeping the existing texts
     *
     * @param string $table
     * @param array $record
     */
    private function markAsFailed( string $table, array $record ): void {

        if( empty($record['id']) ) {
            return;
        }

        $cache = $this->getCache($record) ?: ['source' => $this->getSourceHash($record)];
        $cache['failed'] = time();

        $this->connection->update($table, ['avalex_cache' => json_encode($cache)], ['id' => (int) $record['id']]);
    }


    /**
     * Checks if the last update of the given record failed less than RETRY_AFTER seconds ago
     *
     * @param array $record
     *
     * @return bool
     */
    private function hasRecentlyFailed( array $record ): bool {

        $lastFailure = $this->getLastFailure($record);

        return $lastFailure !== null && (time() - $lastFailure) < self::RETRY_AFTER;
    }


    /**
     * Returns the available languages for the given API key and domain
     *
     * @param string $apiKey
     * @param string $domain
     *
     * @return array
     *
     * @throws \numero2\AvalexBundle\Exception\AvalexApiException
     */
    private function getLanguages( string $apiKey, string $domain ): array {

        $key = $apiKey.'|'.$domain;

        return $this->languages[$key] ??= $this->client->getLanguages($apiKey, $domain);
    }


    /**
     * Returns the decoded cache of the given record, ignores caches that were
     * created for a different API key / domain combination
     *
     * @param array $record
     *
     * @return array
     */
    private function getCache( array $record ): array {

        if( empty($record['avalex_cache']) ) {
            return [];
        }

        $cache = json_decode((string) $record['avalex_cache'], true);

        if( !\is_array($cache) ) {
            return [];
        }

        // caches created by version < 3.0.0 have no source
        if( isset($cache['source']) && $cache['source'] !== $this->getSourceHash($record) ) {
            return [];
        }

        return $cache;
    }


    /**
     * Generates a hash identifying the API key / domain combination of a record
     *
     * @param array $record
     *
     * @return string
     */
    private function getSourceHash( array $record ): string {

        return hash('sha256', trim((string) ($record['avalex_apikey'] ?? '')).'|'.trim((string) ($record['avalex_domain'] ?? '')));
    }


    /**
     * Makes sure only our known tables end up in queries
     *
     * @param string $table
     *
     * @throws \InvalidArgumentException
     */
    private function assertValidTable( string $table ): void {

        if( !\in_array($table, self::TABLES, true) ) {
            throw new \InvalidArgumentException(sprintf('Table "%s" is not supported', $table));
        }
    }
}
