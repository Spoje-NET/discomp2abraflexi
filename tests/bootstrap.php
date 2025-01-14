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

\define('APP_NAME', 'Discomp2AbraFlexiTest');

require_once file_exists('../vendor/autoload.php') ? '../vendor/autoload.php' : './vendor/autoload.php';

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
    file_exists('../.env') ? '../.env' : '.env',
);
