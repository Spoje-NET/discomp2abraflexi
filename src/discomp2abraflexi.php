<?php

declare(strict_types=1);

namespace SpojeNet;

/**
 * Discomp pricelist importer to AbraFlexi
 *
 * @author     Vítězslav Dvořák <info@vitexsoftware.cz>
 * @copyright  2023 Vitex Software
 */

define('APP_NAME', 'Discomp2AbraFlexi');
require_once '../vendor/autoload.php';
\Ease\Shared::init(
    [
            'ABRAFLEXI_URL',
            'ABRAFLEXI_LOGIN',
            'ABRAFLEXI_PASSWORD',
            'ABRAFLEXI_COMPANY',
            'ABRAFLEXI_STORAGE',
            'DISCOMP_USERNAME',
            'DISCOMP_PASSWORD'
        ],
    '../.env'
);

$importer = new Discomp\Importer();

if (\Ease\Shared::cfg('DISCOMP_SCOPE') == 'all') {
    $importer->allTimeItems();
} else {
    $importer->freshItems();
}
