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

$discomper = new Discomp\ApiClient();
$sokoban = new \AbraFlexi\Cenik(null, ['ignore404' => true]);
$suplier = \AbraFlexi\RO::code(\Ease\Shared::cfg('ABRAFLEXI_DISCOMP_CODE', 'DISCOMP'));
if (\Ease\Shared::cfg('APP_DEBUG', false)) {
    $sokoban->logBanner();
}

$activeItems = $discomper->getResult('StoItemActive');
$discomper->addStatusMessage(sprintf(_('%d Active Items found'), count($activeItems)), 'success');
$storageItems = [];

$new = 0;
$updated = 0;
$skipped = 0;
$errors = 0;

foreach ($activeItems as $pos => $activeItemData) {
    $storageItem = $discomper->getItemByCode($activeItemData['@attributes']['Code']);
    if (array_key_exists('StoItem', $storageItem['StoItemBase'])) {
        $stoItem = $storageItem['StoItemBase']['StoItem']['@attributes'];
        if (array_key_exists('PartNo', $stoItem)) {
            $recordCheck = $sokoban->getColumnsFromAbraFlexi(['dodavatel', 'nazev'], ['id' => \AbraFlexi\RO::code($stoItem['PartNo'])]);
            //$newProduct = ($sokoban->lastResponseCode == 404);
            /*
              Id="number | jednoznacna identifikace produktu (aut. cislo), zretezenim s attributy Url* lze dostat vysledny link"
              Code="string | jednoznacna identifikace produktu jako text (slouzi k vyhledavani a pod.)"
              Code2="string | alternativni identifikace produktu"
              PartNo="string | kod vyrobce"
              PartNo2="string | alternativni kod vyrobce"
              Name="string | nazev produktu"
              NameAdd="string | dodatkovy nazev produktu"
              NameE="string | nazev produktu - mezinarodni verze (napr.: anglicky)"
              ManName="string | nazev vyrobce"
              PriceDea="number | cena produktu bez pripadnych poplatku(recyklacnich, autorskych) a bez DPH"
              PriceRef="number | vysledna hodnota recyklacniho poplatku bez DPH"
              RefProName="string | nazev poskytovatele/typu recyklacniho poplatku"
              RefCode="string | kod/zatrizeni recyklacniho poplatku"
              PriceRef2="number | vysledna hodnota autorskeho poplatku bez DPH"
              RefProName2="string | nazev poskytovatele/typu autorskeho poplatku"
              RefCode2="string | kod/zatrizeni autorskeho poplatku"
              WeightRef="number | vaha vstupujici do vypoctu recyklacniho poplatku"
              MeasureRef2="number | pocet jednotek vstupujicich do vypoctu autorskeho poplatku "
              TaxRate="number | sazba DPH"
              QtyFreeIs="bit | info o produktu skladem ve smyslu ANO/NE"
              QtyFree="number | pocet produktu skladem"
              QtyPack="number | pocet produktu v baleni"
              WarDur="number | doba zaruky prepoctena na dny - pro podnikatele"
              WarDurEU="number | doba zaruky prepoctena na dny - pro konc. uzivatele"
              SNTrack="bit | informace o tom, jestli se u produktu sleduji seriova cisla"
              ThumbnailIs="bit | informace o tom, ma-li produkt obrazek - maly"
              ThumbnailSize="number | informace o velikosti obrazku - maly"
              ImgIs="bit | informace o tom, ma-li produkt obrazek - bezny"
              ImgSize="number | informace o velikosti obrazku - bezny"
              EnlargementIs="bit | informace o tom, ma-li produkt obrazek - velky"
              EnlargementSize="number | informace o velikosti obrazku - velky"
              SisName="string | stav produktu (novinka, vyprodej a pod.)"
              NoteShort="string | zkracena poznamka"
              Note="string | poznamka"
             */
            //print_r($storageItem);

            $sokoban->dataReset();
            $sokoban->setDataValue('typZasobyK', 'typZasoby.zbozi'); //TODO: ???
            $sokoban->setDataValue('kod', $stoItem['PartNo']);
            $sokoban->setDataValue('nazev', $stoItem['Name']);
            if (array_key_exists('NameE', $stoItem)) {
                $sokoban->setDataValue('nazevA', $stoItem['NameE']);
            }
            if (array_key_exists('NoteShort', $stoItem)) {
                $sokoban->setDataValue('popis', $stoItem['NoteShort']);
            }

//    if(array_key_exists('Note', $stoItem)){
//        $sokoban->setDataValue('popis',$stoItem['Note']); //TODO: Source contains HTML markup
//    }
            $sokoban->setDataValue('dodavatel', $suplier);

            //print_r($sokoban->getData());

            if (empty($recordCheck)) {
                $discomper->addStatusMessage($pos . '/' . count($activeItems) . ' ' . $stoItem['Code'] . ': ' . $stoItem['Name'] . ' new item', $sokoban->insertToAbraFlexi() ? 'success' : 'error');
                if ($sokoban->lastResponseCode == 201) {
                    $new++;
                } else {
                    $errors++;
                }
            } else {
                if (array_key_exists('dodavatel', $recordCheck) && ($recordCheck['dodavatel'] == $suplier)) {
                    $discomper->addStatusMessage($pos . '/' . count($activeItems) . ' ' . $stoItem['Code'] . ': ' . $stoItem['Name'] . ' update', $sokoban->insertToAbraFlexi() ? 'success' : 'error');
                } else {
                    $discomper->addStatusMessage($pos . '/' . count($activeItems) . ' ' . $stoItem['Code'] . ': ' . $stoItem['Name'] . ' already iported', 'info');
                }
            }
        } else {
            $discomper->addStatusMessage(sprintf(_('Item %s %s does not contain part number. Skipping'), $stoItem['Code'], $stoItem['Name']), 'warning');
            $skipped++;
        }
    } else {
        $discomper->addStatusMessage('Unparsable Response: ' . $discomper->lastCurlResponse, 'debug');
    }
}

$sokoban->addStatusMessage(sprintf(_('New: %d  Updated: %d Skipped: %d Errors: %d'), $new, $updated, $skipped, $errors));