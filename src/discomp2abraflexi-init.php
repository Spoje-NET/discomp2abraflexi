<?php

declare(strict_types=1);

/**
 * This file is part of the discomp2abraflexi package
 *
 * https://github.com/Spoje-NET/discomp2abraflexi
 *
 * (c) SpojeNet s.r.o. <http://spojenet.cz/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SpojeNet;

/**
 * Discomp pricelist importer to AbraFlexi.
 *
 * @author     Vítězslav Dvořák <info@vitexsoftware.cz>
 * @copyright  2023 Vitex Software
 */
\define('APP_NAME', 'Discomp2AbraFlexi Init');

require_once '../vendor/autoload.php';
\Ease\Shared::init(
    [
        'ABRAFLEXI_URL',
        'ABRAFLEXI_LOGIN',
        'ABRAFLEXI_PASSWORD',
        'ABRAFLEXI_COMPANY',
        'ABRAFLEXI_STORAGE',
        'DISCOMP_USERNAME',
        'DISCOMP_PASSWORD',
    ],
    '../.env',
);

$importer = new Discomp\Importer();
$importer->addStatusMessage(_('Supplier Exists'), $importer->ensureSupplierExists() ? 'success' : 'error');
$importer->addStatusMessage(_('Category Root Exists'), $importer->ensureCategoryRootExists() ? 'success' : 'error');

// Todo Attach Discomp logo to ROOT
