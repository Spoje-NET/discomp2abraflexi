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

namespace SpojeNet\Discomp;

use AbraFlexi\Functions;
use AbraFlexi\RW;

/**
 * Description of Importer.
 *
 * @author vitex
 */
class Importer extends \Ease\Sand
{
    use \Ease\Logger\Logging;
    public \DateTime $since;
    public \DateTime $until;
    public ApiClient $discomper;

    /**
     * Discomp Tree Root.
     */
    public static string $ROOT = 'STR_CEN';
    private int $new = 0;
    private int $updated = 0;
    private int $skipped = 0;
    private int $errors = 0;
    private array $levels = [];
    private ?\AbraFlexi\StromCenik $category;

    /**
     * @var array CategoryTree cache
     */
    private array $treeCache = [];
    private ?RW $atributType;
    private ?RW $atribut;

    /**
     * Supplier code for \AbraFlexi\Adresar.
     */
    private ?string $suplier;

    /**
     * Pricelist Engine.
     */
    private ?\AbraFlexi\Cenik $sokoban;
    private ?\AbraFlexi\Dodavatel $pricer;
    private int $images = 0;

    /**
     * Discomp Items Importer.
     */
    public function __construct()
    {
        $this->setObjectName();
        $this->discomper = new ApiClient();
        $this->abraFlexiInit();

        if (\Ease\Shared::cfg('APP_DEBUG', false)) {
            $this->logBanner('Login: '.$this->discomper->getUsername());
        }

        $this->addStatusMessage(_('Supplier Exists'), $this->ensureSupplierExists() ? 'success' : 'error');
        $this->addStatusMessage(_('Category Root Exists'), $this->ensureCategoryRootExists() ? 'success' : 'error');
    }

    /**
     * Connect to AbraFlexi.
     */
    public function abraFlexiInit(): void
    {
        $this->sokoban = new \AbraFlexi\Cenik(null, ['ignore404' => true]);
        $this->sokoban->setObjectName('Pricelist');
        $this->suplier = Functions::code(\Ease\Shared::cfg('ABRAFLEXI_DISCOMP_CODE', 'DISCOMP'));
        $this->pricer = new \AbraFlexi\Dodavatel(['firma' => $this->suplier, 'poznam' => 'Import: '.\Ease\Shared::AppName().' '.\Ease\Shared::AppVersion()."\nhttps://github.com/Spoje-NET/discomp2abraflexi"], ['evidence' => 'dodavatel', 'autoload' => false]);
        $this->category = new \AbraFlexi\StromCenik();
        $this->atribut = new RW(null, ['evidence' => 'atribut']);
        $this->atributType = new RW(null, ['evidence' => 'typ-atributu', 'ignore404' => true]);
        $this->atributType->setObjectName('AttributeType');
    }

    /**
     * Try to free resources.
     */
    public function abraFlexiDisconnect(): void
    {
        $this->sokoban = null;
        $this->suplier = null;
        $this->pricer = null;
        $this->category = null;
        $this->atribut = null;
        $this->atributType = null;
    }

    /**
     * Ensure the supplier is present in AddressBook.
     *
     * @return bool
     */
    public function ensureSupplierExists()
    {
        $suplierOk = true;
        $checker = new \AbraFlexi\Adresar(\AbraFlexi\Functions::code($this->suplier), ['ignore404' => true]);

        if ($checker->lastResponseCode === 404) {
            $this->addStatusMessage($this->suplier.' is missing.', 'warning');
            $checker->insertToAbraFlexi([
                'kod' => Functions::uncode($this->suplier),
                'nazev' => 'Discomp s.r.o.',
                'typVztahuK' => 'typVztahu.dodavatel',
                'platceDph' => true,
                'tel' => '377 221 177',
                'datNaroz' => '1999-09-21',
                'ulice' => 'Cvokařská 1216/8',
                'psc' => '312 00',
                'mesto' => 'Plzeň - Lobzy',
                'www' => 'https://www.discomp.cz/',
                'ic' => '25236792',
                'email' => 'info@discomp.cz',
            ]);
            $suplierOk = ($checker->lastResponseCode === 201);
            $this->addStatusMessage('creating '.$this->suplier, $suplierOk ? 'success' : 'error');
        }

        return $suplierOk;
    }

    /**
     * @return array
     */
    public function getFreshItems()
    {
        $this->scopeToInterval(\Ease\Shared::cfg('DISCOMP_SCOPE', 'yesterday'));
        $freshItems = $this->discomper->getResultByFromTo('StoItemShop_El', $this->since, $this->until);
        $this->addStatusMessage('Import Initiated. From: '.$this->since->format('Y-m-d').' To: '.$this->until->format('Y-m-d').' '.sprintf(_('Active Items found: %d'), \count($freshItems)));

        return $freshItems;
    }

    public function freshItems(): array
    {
        $result = [];
        $errors = 0;
        $freshItems = $this->getFreshItems();

        foreach ($freshItems as $pos => $activeItemData) {
            $this->abraFlexiDisconnect();
            $this->abraFlexiInit();

            $discompItemCode = $activeItemData['CODE'];
            $this->sokoban->setObjectName('('.$pos.'/'.\count($freshItems).') StoreItem:'.$discompItemCode);

            if (!\array_key_exists('PART_NUMBER', $activeItemData) || \is_array($activeItemData['PART_NUMBER'])) {
                $this->sokoban->addStatusMessage('NO PART_NUMBER !? '.json_encode($activeItemData), 'debug');

                continue;
            }

            $this->sokoban->setDataValue('kod', $activeItemData['PART_NUMBER']);

            $recordCheck = $this->sokoban->getColumnsFromAbraFlexi(['dodavatel', 'nazev', 'popis', 'pocetPriloh'], ['id' => \AbraFlexi\Functions::code($activeItemData['PART_NUMBER'])]);

            $this->sokoban->setDataValue('dodavatel', $this->suplier);

            if ($activeItemData['ITEM_TYPE'] !== 'product') {
                $this->sokoban->addStatusMessage('NO Product'.json_encode($activeItemData['PART_NUMBER']), 'debug');
            }

            $this->sokoban->setDataValue('typZasobyK', \Ease\Shared::cfg('DISCOMP_TYP_ZASOBY', 'typZasoby.material')); // TODO: ???
            $this->sokoban->setDataValue('skladove', true); // TODO: ???
            // $this->sokoban->setDataValue('eanKod', $activeItemData['EAN']);

            $this->sokoban->setDataValue('nakupCena', ceil((float) $activeItemData['PURCHASE_PRICE']));

            if (\array_key_exists('STANDARD_PRICE', $activeItemData)) {
                $this->sokoban->setDataValue('cenaBezna', $activeItemData['STANDARD_PRICE']);
            }

            $this->sokoban->setDataValue('nazev', $activeItemData['NAME']);

            if (\array_key_exists('WARRANTY', $activeItemData)) {
                $this->sokoban->setDataValue('zaruka', $activeItemData['WARRANTY']);
            }

            $this->sokoban->setDataValue('mjZarukyK', 'mjZaruky.mesic');

            if (\array_key_exists('WEIGHT', $activeItemData)) {
                $this->sokoban->setDataValue('hmotMj', $activeItemData['WEIGHT']);
            }

            if (\array_key_exists('SHORT_DESCRIPTION', $activeItemData)) {
                $this->sokoban->setDataValue('popis', $activeItemData['SHORT_DESCRIPTION']);
            }

            $this->sokoban->setDataValue('mj1', Functions::code($activeItemData['UNIT']));

            if (\array_key_exists('MANUFACTURER', $activeItemData)) {
                $this->sokoban->setDataValue('vyrobce', $this->findManufacturerCode($activeItemData['MANUFACTURER']));
            }

            $result[$this->sokoban->getRecordCode()] = ['name' => $this->sokoban->getDataValue('nazev')];

            if (empty($recordCheck)) {
                $this->discomper->addStatusMessage($activeItemData['CODE'].': '.$activeItemData['NAME'].' new item', $this->sokoban->insertToAbraFlexi() ? 'success' : 'error');

                if (\array_key_exists('IMAGES', $activeItemData) && \array_key_exists('IMAGE', $activeItemData['IMAGES'])) {
                    if (\is_array($activeItemData['IMAGES']['IMAGE'])) {
                        foreach ($activeItemData['IMAGES']['IMAGE'] as $imgPos => $imgUrl) {
                            $stdImg = \AbraFlexi\Priloha::addAttachment($this->sokoban, $discompItemCode.'_'.$imgPos.'.jpg', $this->discomper->getImage($imgUrl), $this->discomper->getResponseMime());
                            $this->sokoban->addStatusMessage(Functions::uncode($this->sokoban->getRecordCode()).' Img: '.$imgUrl, $stdImg->lastResponseCode === 201 ? 'success' : 'error');
                            ++$this->images;
                        }
                    } else {
                        $stdImg = \AbraFlexi\Priloha::addAttachment($this->sokoban, $discompItemCode.'_'. 0 .'.jpg', $this->discomper->getImage($activeItemData['IMAGES']['IMAGE']), $this->discomper->getResponseMime());
                        $this->sokoban->addStatusMessage(Functions::uncode($this->sokoban->getRecordCode()).' Img: '.$activeItemData['IMAGES']['IMAGE'], $stdImg->lastResponseCode === 201 ? 'success' : 'error');
                        ++$this->images;
                    }

                    unset($stdImg);
                }

                if ($this->sokoban->lastResponseCode === 201) {
                    $result[$this->sokoban->getRecordCode()]['status'] = 'success';
                    ++$this->new;
                } else {
                    ++$errors;
                    $result[$this->sokoban->getRecordCode()] = $this->sokoban->getData();
                    $result[$this->sokoban->getRecordCode()]['status'] = 'failed';
                }
            } else {
                $progressInfo = '('.$pos.'/'.\count($freshItems).') '.$activeItemData['CODE'].': '.$activeItemData['NAME'];

                if (\array_key_exists('dodavatel', $recordCheck) && ($recordCheck['dodavatel'] === $this->suplier)) {
                    $this->discomper->addStatusMessage($progressInfo.' update', $this->sokoban->insertToAbraFlexi() ? 'success' : 'error');
                } else {
                    $this->discomper->addStatusMessage($progressInfo.' already enlisted', 'info');
                }
            }

            $this->removeItemFromTree($this->sokoban);

            if (\array_key_exists('CATEGORIES', $activeItemData)) {
                if (\array_key_exists('CATEGORY', $activeItemData['CATEGORIES'])) {
                    if (\is_array($activeItemData['CATEGORIES']['CATEGORY'])) {
                        foreach ($this->prepareCategories($activeItemData['CATEGORIES']['CATEGORY']) as $category) {
                            $this->category->insertToAbraFlexi(['idZaznamu' => Functions::code($activeItemData['PART_NUMBER']), 'uzel' => $this->treeCache[$category]]);
                        }
                    } else {
                        $this->category->addStatusMessage('Bad Category hierarchy: '.json_encode($activeItemData['CATEGORIES']['CATEGORY']), 'warning');
                    }
                } else {
                    $this->category->addStatusMessage('Bad Category structure: '.json_encode($activeItemData['CATEGORIES']), 'warning');
                }
            } else {
                $this->addStatusMessage('No category ?!', 'warning');
            }

            if (\array_key_exists('TEXT_PROPERTIES', $activeItemData)) {
                foreach ($activeItemData['TEXT_PROPERTIES'] as $property) {
                    if (\count($property) === 2 && key($property) === 'NAME') {
                        if (\strlen($property['NAME'])) {
                            $this->syncProperty($property['NAME'], $property['VALUE']);
                        }
                    } else {
                        foreach ($property as $prop) {
                            if (\strlen($prop['NAME'])) {
                                $this->syncProperty($prop['NAME'], $prop['VALUE']);
                            }
                        }
                    }
                }
            }

            $this->updatePrice($activeItemData);

            // Uvolnění objektů a vynucení garbage collectoru kvůli filehandlerům
            $this->sokoban = null;
            $this->pricer = null;
            $this->category = null;
            $this->atribut = null;
            $this->atributType = null;
            gc_collect_cycles();
        }

        return $result;
    }

    /**
     * @param mixed $name
     * @param mixed $value
     */
    public function syncProperty($name, $value): void
    {
        $attributeCode = Functions::code(mb_substr($name, -20));

        if (empty($this->atributType->loadFromAbraFlexi($attributeCode))) {
            $this->atributType->addStatusMessage(Functions::uncode($this->sokoban->getRecordCode()).': Attribute '.$name.' created', $this->atributType->sync(['kod' => \AbraFlexi\Functions::uncode($attributeCode), 'nazev' => $name, 'typAtributK' => 'typAtribut.retezec']) ? 'success' : 'error');
        }

        $this->atribut->dataReset();
        $this->atribut->setDataValue('hodnota', $value);

        if (\is_float($value)) {
            $this->atribut->setDataValue('valNumeric', (float) $value);
        } elseif (\is_int($value)) {
            $this->atribut->setDataValue('valInteger', (int) $value);
        } else {
            $this->atribut->setDataValue('valString', $value);
        }

        $this->atribut->setDataValue('cenik', $this->sokoban);
        $this->atribut->setDataValue('typAtributu', $this->atributType);

        try {
            $this->atribut->addStatusMessage(Functions::uncode($this->sokoban->getRecordCode()).': '.$name.': '.$value, $this->atribut->sync() ? 'success' : 'warning');
        } catch (\AbraFlexi\Exception $exc) {
            $this->atribut->addStatusMessage(Functions::uncode($this->sokoban->getRecordCode()).': '.$name.': '.$value, 'error');
        } // BAD:  {"winstrom":{"@version":"1.0","atribut":{"hodnota":"QCA9531","valString":"QCA9531","cenik":"code:CRS354-48P-4S+2Q+RM","typAtributu":"code:CPU"},"@atomic":false}}
    }

    /**
     * Initial import to fullfill pricelist.
     */
    public function allTimeItems(): array
    {
        $result = [];
        $errors = 0;
        $storageItems = [];
        $activeItems = $this->discomper->getResult('StoItemActive');
        $this->discomper->addStatusMessage(_('AllTime scope: ').' '.sprintf(_('%d Active Items found'), \count($activeItems)), 'success');

        if (\array_key_exists('title', $activeItems)) {
            $result = $activeItems;
        } else {
            foreach ($activeItems as $pos => $activeItemData) {
                $storageItem = $this->discomper->getItemByCode($activeItemData['@attributes']['Code']);
                $discompItemId = $activeItemData['@attributes']['Id'];
                $discompItemCode = $activeItemData['@attributes']['Code'];

                if (\array_key_exists('StoItem', $storageItem['StoItemBase'])) {
                    $stoItem = $storageItem['StoItemBase']['StoItem']['@attributes'];
                    $baseImageUrl = $storageItem['StoItemBase']['@attributes']['UrlBaseImg'].$discompItemId;
                    $thumbnailImageUrl = $storageItem['StoItemBase']['@attributes']['UrlBaseThumbnail'].$discompItemId;
                    $largeImageUrl = $storageItem['StoItemBase']['@attributes']['UrlBaseEnlargement'].$discompItemId;

                    if (\array_key_exists('PartNo', $stoItem)) {
                        $recordCheck = $this->sokoban->getColumnsFromAbraFlexi(['dodavatel', 'nazev', 'popis', 'pocetPriloh'], ['id' => \AbraFlexi\Functions::uncode($stoItem['PartNo'])]);
                        $this->sokoban->dataReset();
                        $this->sokoban->setDataValue('typZasobyK', \Ease\Shared::cfg('DISCOMP_TYP_ZASOBY', 'typZasoby.material')); // TODO: ???
                        $this->sokoban->setDataValue('typZasobyK', 'typZasoby.material'); // TODO: ???
                        $this->sokoban->setDataValue('skladove', true); // TODO: ???
                        $this->sokoban->setDataValue('kod', $stoItem['PartNo']);
                        $this->pricer->unsetDataValue('id');
                        $this->pricer->setDataValue('cenik', Functions::code($stoItem['PartNo']));
                        $this->pricer->setDataValue('nakupCena', $stoItem['PriceOrd']);

                        if (\array_key_exists('QtyFree', $stoItem)) {
                            $this->pricer->setDataValue('stavMJ', $stoItem['QtyFree']);
                        } else {
                            $this->pricer->setDataValue('stavMJ', 0);
                        }

                        $this->sokoban->setDataValue('nakupCena', ceil($stoItem['PriceDea'] + $stoItem['PriceRef']));
                        $this->sokoban->setDataValue('ean', $stoItem['Code']);
                        $this->sokoban->setDataValue('nazev', $stoItem['Name']);

                        if (\array_key_exists('WarDur', $stoItem)) {
                            $this->sokoban->setDataValue('zaruka', $stoItem['WarDur']);
                        }

                        $this->sokoban->setDataValue('mjZarukyK', 'mjZaruky.den');

                        if (\array_key_exists('Weight', $stoItem)) {
                            $this->sokoban->setDataValue('hmotMj', $stoItem['Weight']);
                        }

                        if (\array_key_exists('NameE', $stoItem)) {
                            $this->sokoban->setDataValue('nazevA', $stoItem['NameE']);
                        }

                        if (\array_key_exists('NoteShort', $stoItem)) {
                            $this->sokoban->setDataValue('popis', $stoItem['NoteShort']);
                        }

                        //    if(array_key_exists('Note', $stoItem)){
                        //        $sokoban->setDataValue('popis',$stoItem['Note']); //TODO: Source contains HTML markup
                        //    }
                        $this->sokoban->setDataValue('dodavatel', $this->suplier);

                        if (empty($recordCheck)) {
                            $this->discomper->addStatusMessage($pos.'/'.\count($activeItems).' '.$stoItem['Code'].': '.$stoItem['Name'].' new item', $this->sokoban->insertToAbraFlexi() ? 'success' : 'error');

                            if (\array_key_exists('ImgIs', $stoItem) && $stoItem['ImgIs'] === 1) {
                                $stdImg = \AbraFlexi\Priloha::addAttachment($this->sokoban, $discompItemCode.'.jpg', $this->discomper->getImage($baseImageUrl), $this->discomper->getResponseMime());
                                // $this->sokoban->addStatusMessage($this->sokoban . ' ' . $baseImageUrl, $stdImg->lastResponseCode == 201 ? 'success' : 'error');
                            }

                            if (\array_key_exists('EnlargementIs', $stoItem) && $stoItem['EnlargementIs'] === 1) {
                                $largeImg = \AbraFlexi\Priloha::addAttachment($this->sokoban, $discompItemCode.'.jpg', $this->discomper->getImage($largeImageUrl), $this->discomper->getResponseMime());
                                // $this->sokoban->addStatusMessage(\AbraFlexi\Functions::uncode($this->sokoban) . ' ' . $largeImageUrl, $largeImg->lastResponseCode == 201 ? 'success' : 'error');
                            }

                            if ($this->sokoban->lastResponseCode === 201) {
                                ++$this->new;
                            } else {
                                ++$errors;
                            }
                        } else {
                            if (\array_key_exists('dodavatel', $recordCheck) && ($recordCheck['dodavatel'] === $this->suplier)) {
                                $this->discomper->addStatusMessage($pos.'/'.\count($activeItems).' '.$stoItem['Code'].': '.$stoItem['Name'].' update', $this->sokoban->insertToAbraFlexi() ? 'success' : 'error');
                            } else {
                                $this->discomper->addStatusMessage($pos.'/'.\count($activeItems).' '.$stoItem['Code'].': '.$stoItem['Name'].' already iported', 'info');
                            }
                        }

                        $this->updatePrice($stoItem);
                    } else {
                        $this->discomper->addStatusMessage(sprintf(_('Item %s %s does not contain part number. Skipping'), $stoItem['Code'], $stoItem['Name']), 'warning');
                        ++$this->skipped;
                    }
                } else {
                    $this->discomper->addStatusMessage('Unparsable Response: '.$this->discomper->getLastCurlResponseBody(), 'debug');
                }
            }

            $this->sokoban->addStatusMessage(sprintf(_('New: %d  Updated: %d Skipped: %d Errors: %d'), $this->new, $this->updated, $this->skipped, $this->errors));
        }

        return $result;
    }

    /**
     * @param mixed $activeItemData
     */
    public function updatePrice($activeItemData): void
    {
        $this->pricer->connectionReset();
        $this->pricer->unsetDataValue('id');
        $this->pricer->setDataValue('cenik', $this->sokoban);
        $this->pricer->setDataValue('kodIndi', $activeItemData['CODE']);
        $priceFound = $this->pricer->loadFromAbraFlexi(['cenik' => $this->sokoban, 'firma' => $this->suplier]);

        if (empty($priceFound)) {
            $this->pricer->setDataValue('cenik', $this->sokoban);
            $this->pricer->setDataValue('firma', $this->suplier);
        }

        $this->pricer->setDataValue('nakupCena', $activeItemData['PURCHASE_PRICE']); // TODO: Confirm column
        $this->pricer->setDataValue('mena', Functions::code($activeItemData['CURRENCY']));
        $this->pricer->setDataValue('cenik', Functions::code($activeItemData['PART_NUMBER']));

        if (\array_key_exists('STOCK', $activeItemData) && \array_key_exists('AMOUNT', $activeItemData['STOCK']) && (float) $activeItemData['STOCK']['AMOUNT']) {
            $this->pricer->setDataValue('stavMJ', $activeItemData['STOCK']['AMOUNT']);
        } else {
            $this->pricer->setDataValue('stavMJ', 0);
        }

        try {
            $this->pricer->insertToAbraFlexi();
            $this->pricer->addStatusMessage('supplier price update: '.Functions::uncode($this->sokoban->getRecordCode()).': '.$this->pricer->getDataValue('nakupCena').' '.\AbraFlexi\Functions::uncode($this->pricer->getDataValue('mena')), $this->pricer->lastResponseCode === 201 ? 'success' : 'error');
        } catch (\AbraFlexi\Exception $exc) {
            echo $exc->getTraceAsString();
            ++$this->errors;
        }
    }

    /**
     * Prepare processing interval.
     *
     * @param string $scope
     *
     * @throws \Exception
     */
    public function scopeToInterval($scope): void
    {
        switch ($scope) {
            case 'yesterday':
                $this->since = new \DateTime('yesterday');
                $this->until = new \DateTime('today');

                break;
            case 'last_week':
                $this->since = new \DateTime('monday last week');
                $this->until = new \DateTime('sunday last week');

                break;
            case 'current_month':
                $this->since = new \DateTime('first day of this month');
                $this->until = new \DateTime();

                break;
            case 'last_month':
                $this->since = new \DateTime('first day of last month');
                $this->until = new \DateTime('last day of last month');

                break;
            case 'last_two_months':
                $this->since = (new \DateTime('first day of last month'))->modify('-1 month');
                $this->until = (new \DateTime('last day of last month'));

                break;
            case 'previous_month':
                $this->since = new \DateTime('first day of -2 month');
                $this->until = new \DateTime('last day of -2 month');

                break;
            case 'two_months_ago':
                $this->since = new \DateTime('first day of -3 month');
                $this->until = new \DateTime('last day of -3 month');

                break;
            case 'this_year':
                $this->since = new \DateTime('first day of January '.date('Y'));
                $this->until = new \DateTime('last day of December'.date('Y'));

                break;
            case 'January':  // 1
            case 'February': // 2
            case 'March':    // 3
            case 'April':    // 4
            case 'May':      // 5
            case 'June':     // 6
            case 'July':     // 7
            case 'August':   // 8
            case 'September':// 9
            case 'October':  // 10
            case 'November': // 11
            case 'December': // 12
                $this->since = new \DateTime('first day of '.$scope.' '.date('Y'));
                $this->until = new \DateTime('last day of '.$scope.' '.date('Y'));

                break;

            default:
                if (strstr($scope, '>')) {
                    [$begin, $end] = explode('>', $scope);
                    $this->since = new \DateTime($begin);
                    $this->until = new \DateTime($end);
                } else {
                    if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/', $scope)) {
                        $this->since = new \DateTime($scope);
                        $this->until = (new \DateTime($scope))->setTime(23, 59, 59, 999);

                        break;
                    }

                    throw new \Exception('Unknown scope '.$scope);
                }
        }

        $this->since = $this->since->setTime(0, 0);
        $this->until = $this->until->setTime(0, 0);
    }

    /**
     * Generate > Path > Of > Categories.
     */
    public function prepareCategories(array $categoriesRaw): array
    {
        $categories = [];

        foreach ($categoriesRaw as $tree) {
            $categories[] = $this->categoryBranch(explode(' > ', 'Discomp > '.$tree));
        }

        return $categories;
    }

    /**
     * Create ale nodes in category tree.
     *
     * @param string[] $nodes
     *
     * @return string
     */
    public function categoryBranch(array $nodes)
    {
        $parent = '';

        foreach ($nodes as $level => $node) {
            $parent = $this->createBranchNode($node, $level, $parent, md5(implode('', \array_slice($nodes, 0, $level + 1))));
        }

        return $parent;
    }

    /**
     * Create Branch Node.
     *
     * @param string $kod forced code for branch
     *
     * @return string
     */
    public function createBranchNode(string $node, int $level, string $parent, string $kod)
    {
        $kod = Functions::code(substr($kod, 0, 30));

        if (\array_key_exists($level, $this->levels)) {
            ++$this->levels[$level];
        } else {
            $this->levels[$level] = 1;
        }

        $strom = new \AbraFlexi\Strom($kod, ['ignore404' => true]);

        if ($strom->lastResponseCode === 404) {
            $strom->setDataValue('id', $kod);
            $strom->setDataValue('nazev', $node);
            $strom->setDataValue('strom', Functions::code(self::$ROOT));

            if ($parent) {
                $strom->setDataValue('otec', $parent);
            }

            $strom->setDataValue('poradi', $this->levels[$level]);
            // $strom->setDataValue('hladina',$level);

            $strom->addStatusMessage('Create Category '.$node, $strom->sync() ? 'success' : 'error');
        }

        $this->treeCache[$strom->getRecordCode()] = $strom->getMyKey();

        return $strom->getRecordCode();
    }

    /**
     * @return int
     */
    public function ensureCategoryRootExists()
    {
        $discpmpData = [
            'nazev' => 'Discomp',
            'kod' => self::$ROOT,
            'poznam' => '',
            'primarni' => false,
            'popis' => 'Discomp Import',
            'tabulka' => 'cz.winstrom.vo.cen.Cenik',
        ];
        $root = new RW(Functions::code(self::$ROOT), ['evidence' => 'strom-koren', 'ignore404' => true]);

        return $root->lastResponseCode === 200 ? $root->getMyKey() : $root->insertToAbraFlexi($discpmpData);
    }

    /**
     * @return \AbraFlexi\Adresar
     */
    public function findManufacturerCode(string $manufacturerName)
    {
        $manufacturerCode = Functions::code($manufacturerName);
        $manufacturer = new \AbraFlexi\Adresar($manufacturerCode, ['ignore404' => true]);

        if ($manufacturer->lastResponseCode === 404) {
            $manufacturer->addStatusMessage(sprintf(_('New Manufacturer %s'), $manufacturerName), $manufacturer->sync(['kod' => \AbraFlexi\Functions::uncode($manufacturerName), 'nazev' => $manufacturerName]) ? 'success' : 'error');
        }

        return $manufacturer;
    }

    /**
     * Remove Item from Pricelit Category Tree.
     */
    public function removeItemFromTree(\AbraFlexi\Cenik $item): int
    {
        $done = 0;
        $current = $this->category->getColumnsFromAbraFlexi(['id'], ['idZaznamu' => $item->getRecordID()]);

        foreach ($current as $assigned) {
            if ($this->category->deleteFromAbraFlexi($assigned['id'])) {
                ++$done;
            }
        }

        return $done;
    }
}
