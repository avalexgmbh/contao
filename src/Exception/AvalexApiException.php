<?php

/**
 * avalex Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 * @copyright Copyright (c) 2026, avalex GmbH
 */


namespace numero2\AvalexBundle\Exception;


class AvalexApiException extends \RuntimeException {


    /**
     * HTTP status code returned by the API, 0 if the API could not be reached at all
     */
    private int $statusCode;


    public function __construct( string $message, int $statusCode=0, ?\Throwable $previous=null ) {

        parent::__construct($message, $statusCode, $previous);

        $this->statusCode = $statusCode;
    }


    public function getStatusCode(): int {

        return $this->statusCode;
    }


    /**
     * The API key is invalid
     */
    public function isInvalidKey(): bool {

        return $this->statusCode === 401;
    }


    /**
     * The requested text is not part of the license or the key does not match the domain
     */
    public function isInsufficientLicense(): bool {

        return $this->statusCode === 400;
    }
}
