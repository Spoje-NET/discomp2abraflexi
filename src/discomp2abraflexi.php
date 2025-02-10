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

use Ease\Shared;

/**
 * Discomp pricelist importer to AbraFlexi.
 *
 * @author     Vítězslav Dvořák <info@vitexsoftware.cz>
 * @copyright  2023-2025 SpojeNet
 */
\define('APP_NAME', 'Discomp2AbraFlexi');

require_once '../vendor/autoload.php';

/**
 * Get today's Statements list.
 */
$options = getopt('o::e::', ['output::environment::']);
Shared::init(
    [
        'ABRAFLEXI_URL',
        'ABRAFLEXI_LOGIN',
        'ABRAFLEXI_PASSWORD',
        'ABRAFLEXI_COMPANY',
        'ABRAFLEXI_STORAGE',
        'DISCOMP_USERNAME',
        'DISCOMP_PASSWORD',
    ],
    \array_key_exists('environment', $options) ? $options['environment'] : (\array_key_exists('e', $options) ? $options['e'] : '../.env'),
);
$destination = \array_key_exists('o', $options) ? $options['o'] : (\array_key_exists('output', $options) ? $options['output'] : \Ease\Shared::cfg('RESULT_FILE', 'php://stdout'));

$importer = new Discomp\Importer();

try {
    if (\Ease\Shared::cfg('DISCOMP_SCOPE', false) === 'all') {
        $report = $importer->allTimeItems();
    } else {
        $report = $importer->freshItems();
    }
} catch (\Exception $exc) {
    $report['message'] = $exc->getMessage();
    $importer->addStatusMessage($report['message'], 'error');

    $exitcode = $exc->getCode();
}

$report['exitcode'] = $exitcode;
$written = file_put_contents($destination, json_encode($report, Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE : 0));
$importer->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($exitcode);
