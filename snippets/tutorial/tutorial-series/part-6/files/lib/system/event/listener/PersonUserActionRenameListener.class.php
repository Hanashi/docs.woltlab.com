<?php

namespace wcf\system\event\listener;

/**
 * Updates person information during user renaming.
 *
 * @author  Matthias Schmidt
 * @copyright   2001-2021 WoltLab GmbH
 * @license GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package WoltLabSuite\Core\System\Event\Listener
 */
class PersonUserActionRenameListener extends AbstractUserActionRenameListener
{
    /**
     * @inheritDoc
     */
    protected $databaseTables = [
        'wcf{WCF_N}_person_information',
    ];
}
