<?php

/**
 * avalex Bundle for Contao Open Source CMS
 *
 * @author    Benny Born <benny.born@numero2.de>
 * @license   LGPL-3.0-or-later
 * @copyright Copyright (c) 2026, numero2 - Agentur für digitales Marketing GbR
 * @copyright Copyright (c) 2026, avalex GmbH
 */


namespace numero2\AvalexBundle\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use numero2\AvalexBundle\Util\AvalexUtil;


/**
 * The legacy mod_avalex*.html5 templates have been replaced by Twig templates,
 * custom templates based on them would break the frontend - so we reset them
 */
class LegacyTemplateMigration extends AbstractMigration {


    private Connection $connection;


    public function __construct( Connection $connection ) {

        $this->connection = $connection;
    }


    public function shouldRun(): bool {

        if( !$this->connection->createSchemaManager()->tablesExist(['tl_module']) ) {
            return false;
        }

        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM tl_module WHERE type IN (?) AND customTpl LIKE 'mod\\_avalex%'"
        ,   [array_keys(AvalexUtil::TYPES)]
        ,   [ArrayParameterType::STRING]
        ) > 0;
    }


    public function run(): MigrationResult {

        $count = $this->connection->executeStatement(
            "UPDATE tl_module SET customTpl='' WHERE type IN (?) AND customTpl LIKE 'mod\\_avalex%'"
        ,   [array_keys(AvalexUtil::TYPES)]
        ,   [ArrayParameterType::STRING]
        );

        return $this->createResult(true, sprintf('Reset the legacy custom template of %d avalex module(s), please recreate them as Twig templates.', $count));
    }
}
