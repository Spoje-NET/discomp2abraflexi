<?php

declare(strict_types=1);

/**
 * Discomp Data Importer
 *
 * @author     Vítězslav Dvořák <info@vitexsoftware.cz>
 * @copyright  2023 Vitex Software
 */

namespace SpojeNet\Discomp;

/**
 * Description of Importer
 *
 * @author vitex
 */
class Importer extends \Ease\Sand
{
    use \Ease\Logger\Logging;

    /**
     *
     * @var DateTime
     */
    public $since;

    /**
     *
     * @var DateTime
     */
    public $until;

    /**
     *
     * @var ApiClient
     */
    public $discpomper;

    /**
     *
     * @var int
     */
    private $new = 0;

    /**
     *
     * @var int
     */
    private $updated = 0;

    /**
     *
     * @var int
     */
    private $skipped = 0;

    /**
     *
     * @var int
     */
    private $errors = 0;

    /**
     * Discomp Tree Root
     * @var string
     */
    static $ROOT = 'STR_CEN';

    /**
     *
     * @var array
     */
    private $levels = [];

    /**
     *
     * @var \AbraFlexi\StromCenik
     */
    private $category;

    /**
     *
     * @var CategoryTree cache
     */
    private $treeCache = [];

    /**
     *
     * @var  \AbraFlexi\RW
     */
    private $atributType;

    /**
     *
     * @var  \AbraFlexi\RW
     */
    private $atribut;

    /**
     *
     * @var \AbraFlexi\Adresar
     */
    private $suplier;

    /**
     * Pricelist Engine
     * @var \AbraFlexi\Cenik
     */
    private $sokoban;

    /**
     * Discomp Items Importer
     */
    public function __construct()
    {
        $this->setObjectName();
        $this->discomper = new ApiClient();
        $this->sokoban = new \AbraFlexi\Cenik(null, ['ignore404' => true]);
        $this->sokoban->setObjectName('Pricelist');
        $this->suplier = \AbraFlexi\RO::code(\Ease\Shared::cfg('ABRAFLEXI_DISCOMP_CODE', 'DISCOMP'));
        $this->pricer = new \AbraFlexi\Dodavatel(['firma' => $this->suplier, 'poznam' => 'Import: ' . \Ease\Shared::AppName() . ' ' . \Ease\Shared::AppVersion()], ['evidence' => 'dodavatel', 'autoload' => false]);
        $this->category = new \AbraFlexi\StromCenik();
        $this->atribut = new \AbraFlexi\RW(null, ['evidence' => 'atribut']);
        $this->atributType = new \AbraFlexi\RW(null, ['evidence' => 'typ-atributu', 'ignore404' => true]);

        if (\Ease\Shared::cfg('APP_DEBUG', false)) {
            $this->logBanner();
        }

        $this->addStatusMessage(_('Supplier Exists'), $this->ensureSupplierExists() ? 'success' : 'error');
        $this->addStatusMessage(_('Category Root Exists'), $this->ensureCategoryRootExists() ? 'success' : 'error');
    }

    /**
     * Ensure the supplier is present in AddressBook
     *
     * @return bool
     */
    public function ensureSupplierExists()
    {
        $suplierOk = true;
        $checker = new \AbraFlexi\Adresar($this->suplier, ['ignore404' => true]);
        if ($checker->lastResponseCode == 404) {
            $this->addStatusMessage($this->suplier . ' is missing.', 'warning');
            $checker->insertToAbraFlexi([
                'kod' => \AbraFlexi\RO::uncode($this->suplier),
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
                'email' => 'info@discomp.cz'
            ]);
            $suplierOk = $checker->lastResponseCode == 201;
            $this->addStatusMessage('creating ' . $this->suplier, $suplierOk ? 'success' : 'error');
        }
        return $suplierOk;
    }

    /**
     *
     * @return array
     */
    public function getFreshItems()
    {
        $this->scopeToInterval(\Ease\Shared::cfg('DISCOMP_SCOPE', 'yesterday'));
        $freshItems = $this->discomper->getResultByFromTo('StoItemShop_El', $this->since, $this->until);
        $this->addStatusMessage('Import Initiated. From: ' . $this->since->format('Y-m-d') . ' To: ' . $this->until->format('Y-m-d') . ' ' . sprintf(_('Active Items found: %d'), count($freshItems)));
        return $freshItems;
    }

    public function freshItems()
    {
        $freshItems = $this->getFreshItems();
        foreach ($freshItems as $pos => $activeItemData) {
            $discompItemCode = $activeItemData['CODE'];

            if (is_array($activeItemData['PART_NUMBER'])) {
                $this->addStatusMessage('WTF? ' . json_encode($activeItemData['PART_NUMBER']), 'debug');
            }

            $recordCheck = $this->sokoban->getColumnsFromAbraFlexi(['dodavatel', 'nazev', 'popis', 'pocetPriloh'], ['id' => \AbraFlexi\RO::code($activeItemData['PART_NUMBER'])]);

            $this->sokoban->dataReset();
            $this->sokoban->setDataValue('dodavatel', $this->suplier);
            if ($activeItemData['ITEM_TYPE'] != 'product') {
                echo ''; //TODO
            }
            $this->sokoban->setDataValue('typZasobyK', \Ease\Shared::cfg('DISCOMP_TYP_ZASOBY', 'typZasoby.material')); //TODO: ???
            $this->sokoban->setDataValue('skladove', true); //TODO: ???
            $this->sokoban->setDataValue('kod', $activeItemData['PART_NUMBER']);
            $this->sokoban->setDataValue('ean', $discompItemCode);

            $this->sokoban->setDataValue('nakupCena', ceil(floatval($activeItemData['PURCHASE_PRICE'])));
            $this->sokoban->setDataValue('nazev', $activeItemData['NAME']);

            if (array_key_exists('WARRANTY', $activeItemData)) {
                $this->sokoban->setDataValue('zaruka', $activeItemData['WARRANTY']);
            }
            $this->sokoban->setDataValue('mjZarukyK', 'mjZaruky.mesic');
            if (array_key_exists('WEIGHT', $activeItemData)) {
                $this->sokoban->setDataValue('hmotMj', $activeItemData['WEIGHT']);
            }
            if (array_key_exists('SHORT_DESCRIPTION', $activeItemData)) {
                $this->sokoban->setDataValue('popis', $activeItemData['SHORT_DESCRIPTION']);
            }

            $this->sokoban->setDataValue('mj1', \AbraFlexi\RO::code($activeItemData['UNIT']));

            if ($activeItemData['MANUFACTURER']) {
                $this->sokoban->setDataValue('vyrobce', $this->findManufacturerCode($activeItemData['MANUFACTURER']));
            }

            if (empty($recordCheck)) {
                $this->discomper->addStatusMessage($pos . '/' . count($freshItems) . ' ' . $activeItemData['CODE'] . ': ' . $activeItemData['NAME'] . ' new item', $this->sokoban->insertToAbraFlexi() ? 'success' : 'error');
                if (array_key_exists('IMAGES', $activeItemData) && array_key_exists('IMAGE', $activeItemData['IMAGES'])) {
                    if (is_array($activeItemData['IMAGES']['IMAGE'])) {
                        foreach ($activeItemData['IMAGES']['IMAGE'] as $imgPos => $imgUrl) {
                            $stdImg = \AbraFlexi\Priloha::addAttachment($this->sokoban, $discompItemCode . '_' . $imgPos . '.jpg', $this->discomper->getImage($imgUrl), $this->discomper->getResponseMime());
                            $this->sokoban->addStatusMessage('Img: ' . $this->sokoban . ' ' . $imgUrl, $stdImg->lastResponseCode == 201 ? 'success' : 'error');
                        }
                    } else {
                        $stdImg = \AbraFlexi\Priloha::addAttachment($this->sokoban, $discompItemCode . '_' . 0 . '.jpg', $this->discomper->getImage($activeItemData['IMAGES']['IMAGE']), $this->discomper->getResponseMime());
                        $this->sokoban->addStatusMessage('Img: ' . $this->sokoban . ' ' . $activeItemData['IMAGES']['IMAGE'], $stdImg->lastResponseCode == 201 ? 'success' : 'error');
                    }
                }
                if ($this->sokoban->lastResponseCode == 201) {
                    $this->new++;
                } else {
                    $errors++;
                }
            } else {
                if (array_key_exists('dodavatel', $recordCheck) && ($recordCheck['dodavatel'] == $this->suplier)) {
                    $this->discomper->addStatusMessage($pos . '/' . count($freshItems) . ' ' . $activeItemData['CODE'] . ': ' . $activeItemData['NAME'] . ' update', $this->sokoban->insertToAbraFlexi() ? 'success' : 'error');
                } else {
                    $this->discomper->addStatusMessage($pos . '/' . count($freshItems) . ' ' . $activeItemData['CODE'] . ': ' . $activeItemData['NAME'] . ' already iported', 'info');
                }
            }

            if (array_key_exists('CATEGORIES', $activeItemData)) {
                foreach ($this->prepareCategories($activeItemData['CATEGORIES']['CATEGORY']) as $category) {
                    $this->category->insertToAbraFlexi(['idZaznamu' => \AbraFlexi\RO::code($activeItemData['PART_NUMBER']), 'uzel' => $this->treeCache[$category]]);
                }
            } else {
                $this->addStatusMessage('No category ?!', 'warning');
            }

            if (array_key_exists('TEXT_PROPERTIES', $activeItemData)) {
                foreach ($activeItemData['TEXT_PROPERTIES'] as $property) {
                    if (count($property) == 2 && key($property) == 'NAME') {
                        if (strlen($property['NAME'])) {
                            $this->syncProperty($property['NAME'], $property['VALUE']);
                        }
                    } else {
                        foreach ($property as $prop) {
                            if (strlen($prop['NAME'])) {
                                $this->syncProperty($prop['NAME'], $prop['VALUE']);
                            }
                        }
                    }
                }
            }

            $this->updatePrice($activeItemData);
        }
    }

    /**
     *
     */
    public function syncProperty($name, $value)
    {
        $attributeCode = \AbraFlexi\RO::code(mb_substr($name, -20));
        if (empty($this->atributType->loadFromAbraFlexi($attributeCode))) {
            $this->atributType->addStatusMessage($this->sokoban . ': Attribute ' . $name . ' created', $this->atributType->sync(['kod' => \AbraFlexi\RO::uncode($attributeCode), 'nazev' => $name, 'typAtributK' => 'typAtribut.retezec']) ? 'success' : 'error');
        }
        $this->atribut->dataReset();
        $this->atribut->setDataValue('valString', $value);
        $this->atribut->setDataValue('cenik', $this->sokoban);
        $this->atribut->setDataValue('typAtributu', $this->atributType);
        $this->atribut->addStatusMessage($this->sokoban . ': ' . $name . ': ' . $value, $this->atribut->sync() ? 'success' : 'error');
    }

    /**
     * Initial import to fullfill pricelist
     */
    public function allTimeItems()
    {
        $storageItems = [];
        $activeItems = $this->discomper->getResult('StoItemActive');
        $this->discomper->addStatusMessage(_('AllTime scope: ') . ' ' . sprintf(_('%d Active Items found'), count($activeItems)), 'success');

        foreach ($activeItems as $pos => $activeItemData) {
            $storageItem = $this->discomper->getItemByCode($activeItemData['@attributes']['Code']);
            $discompItemId = $activeItemData['@attributes']['Id'];
            $discompItemCode = $activeItemData['@attributes']['Code'];
            if (array_key_exists('StoItem', $storageItem['StoItemBase'])) {
                $stoItem = $storageItem['StoItemBase']['StoItem']['@attributes'];
                $baseImageUrl = $storageItem['StoItemBase']['@attributes']['UrlBaseImg'] . $discompItemId;
                $thumbnailImageUrl = $storageItem['StoItemBase']['@attributes']['UrlBaseThumbnail'] . $discompItemId;
                $largeImageUrl = $storageItem['StoItemBase']['@attributes']['UrlBaseEnlargement'] . $discompItemId;

                if (array_key_exists('PartNo', $stoItem)) {
                    $recordCheck = $this->sokoban->getColumnsFromAbraFlexi(['dodavatel', 'nazev', 'popis', 'pocetPriloh'], ['id' => \AbraFlexi\RO::code($stoItem['PartNo'])]);
                    //$newProduct = ($sokoban->lastResponseCode == 404);
                    /*
                      Id="number | jednoznacna identifikace produktu (aut. cislo), zretezenim s attributy Url* lze dostat vysledny link"

                      CutCode = Celní zařazení

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

                    $this->sokoban->dataReset();
                    $this->sokoban->setDataValue('typZasobyK', \Ease\Shared::cfg('DISCOMP_TYP_ZASOBY', 'typZasoby.material')); //TODO: ???
                    $this->sokoban->setDataValue('typZasobyK', 'typZasoby.material'); //TODO: ???
                    $this->sokoban->setDataValue('skladove', true); //TODO: ???
                    $this->sokoban->setDataValue('kod', $stoItem['PartNo']);
                    $this->pricer->unsetDataValue('id');
                    $this->pricer->setDataValue('cenik', \AbraFlexi\RO::code($stoItem['PartNo']));
                    $this->pricer->setDataValue('nakupCena', $stoItem['PriceOrd']);

                    if (array_key_exists('QtyFree', $stoItem)) {
                        $this->pricer->setDataValue('stavMJ', $stoItem['QtyFree']);
                    } else {
                        $this->pricer->setDataValue('stavMJ', 0);
                    }

                    $this->sokoban->setDataValue('nakupCena', ceil($stoItem['PriceDea'] + $stoItem['PriceRef']));
                    $this->sokoban->setDataValue('ean', $stoItem['Code']);
                    $this->sokoban->setDataValue('nazev', $stoItem['Name']);

                    if (array_key_exists('WarDur', $stoItem)) {
                        $this->sokoban->setDataValue('zaruka', $stoItem['WarDur']);
                    }
                    $this->sokoban->setDataValue('mjZarukyK', 'mjZaruky.den');
                    if (array_key_exists('Weight', $stoItem)) {
                        $this->sokoban->setDataValue('hmotMj', $stoItem['Weight']);
                    }
                    if (array_key_exists('NameE', $stoItem)) {
                        $this->sokoban->setDataValue('nazevA', $stoItem['NameE']);
                    }
                    if (array_key_exists('NoteShort', $stoItem)) {
                        $this->sokoban->setDataValue('popis', $stoItem['NoteShort']);
                    }

//    if(array_key_exists('Note', $stoItem)){
//        $sokoban->setDataValue('popis',$stoItem['Note']); //TODO: Source contains HTML markup
//    }
                    $this->sokoban->setDataValue('dodavatel', $this->suplier);

                    if (empty($recordCheck)) {
                        $this->discomper->addStatusMessage($pos . '/' . count($activeItems) . ' ' . $stoItem['Code'] . ': ' . $stoItem['Name'] . ' new item', $this->sokoban->insertToAbraFlexi() ? 'success' : 'error');

                        if (array_key_exists('ImgIs', $stoItem) && $stoItem['ImgIs'] == 1) {
                            $stdImg = \AbraFlexi\Priloha::addAttachment($this->sokoban, $discompItemCode . '.jpg', $this->discomper->getImage($baseImageUrl), $this->discomper->getResponseMime());
                            $this->sokoban->addStatusMessage($this->sokoban . ' ' . $baseImageUrl, $stdImg->lastResponseCode == 201 ? 'success' : 'error');
                        }
                        if (array_key_exists('EnlargementIs', $stoItem) && $stoItem['EnlargementIs'] == 1) {
                            $largeImg = \AbraFlexi\Priloha::addAttachment($this->sokoban, $discompItemCode . '.jpg', $this->discomper->getImage($largeImageUrl), $this->discomper->getResponseMime());
                            $this->sokoban->addStatusMessage($this->sokoban . ' ' . $largeImageUrl, $largeImg->lastResponseCode == 201 ? 'success' : 'error');
                        }

                        if ($this->sokoban->lastResponseCode == 201) {
                            $this->new++;
                        } else {
                            $errors++;
                        }
                    } else {
                        if (array_key_exists('dodavatel', $recordCheck) && ($recordCheck['dodavatel'] == $this->suplier)) {
                            $this->discomper->addStatusMessage($pos . '/' . count($activeItems) . ' ' . $stoItem['Code'] . ': ' . $stoItem['Name'] . ' update', $this->sokoban->insertToAbraFlexi() ? 'success' : 'error');
                        } else {
                            $this->discomper->addStatusMessage($pos . '/' . count($activeItems) . ' ' . $stoItem['Code'] . ': ' . $stoItem['Name'] . ' already iported', 'info');
                        }
                    }

                    $this->updatePrice();
                } else {
                    $this->discomper->addStatusMessage(sprintf(_('Item %s %s does not contain part number. Skipping'), $stoItem['Code'], $stoItem['Name']), 'warning');
                    $this->skipped++;
                }
            } else {
                $this->discomper->addStatusMessage('Unparsable Response: ' . $this->discomper->getLastCurlResponseBody(), 'debug');
            }
        }

        $this->sokoban->addStatusMessage(sprintf(_('New: %d  Updated: %d Skipped: %d Errors: %d'), $this->new, $this->updated, $this->skipped, $this->errors));
    }

    /**
     *
     */
    public function updatePrice($activeItemData)
    {
        $this->pricer->unsetDataValue('id');
        $this->pricer->setDataValue('cenik', $this->sokoban);

        $priceFound = $this->pricer->loadFromAbraFlexi(['cenik' => $this->sokoban, 'firma' => $this->suplier]);
        if (empty($priceFound)) {
            $this->pricer->setDataValue('cenik', $this->sokoban);
            $this->pricer->setDataValue('firma', $this->suplier);
        }
        $this->pricer->setDataValue('nakupCena', $activeItemData['PURCHASE_PRICE']); //TODO: Confirm column
        $this->pricer->setDataValue('mena', \AbraFlexi\RO::code($activeItemData['CURRENCY']));
        $this->pricer->setDataValue('cenik', \AbraFlexi\RO::code($activeItemData['PART_NUMBER']));

        if (array_key_exists('STOCK', $activeItemData) && array_key_exists('AMOUNT', $activeItemData['STOCK'])) {
            $this->pricer->setDataValue('sumDostupMj', $activeItemData['STOCK']['AMOUNT']);
        } else {
            $this->pricer->setDataValue('sumDostupMj', 0);
        }

        try {
            $this->pricer->insertToAbraFlexi();
            $this->pricer->addStatusMessage('supplier price update: ' . $this->sokoban->getDataValue('kod') . ': ' . $this->pricer->getDataValue('nakupCena') . ' ' . \AbraFlexi\RO::uncode($this->pricer->getDataValue('mena')), $this->pricer->lastResponseCode == 201 ? 'success' : 'error');
        } catch (\AbraFlexi\Exception $exc) {
            echo $exc->getTraceAsString();
            $this->errors++;
        }
    }

    /**
     * Prepare processing interval
     *
     * @param string $scope
     * @throws Exception
     */
    public function scopeToInterval($scope)
    {
        switch ($scope) {
            case 'yesterday':
                $this->since = new \DateTime("yesterday");
                $this->until = new \DateTime("today");
                break;
            case 'last_week':
                $this->since = new \DateTime("monday last week");
                $this->until = new \DateTime("sunday last week");
                break;
            case 'current_month':
                $this->since = new \DateTime("first day of this month");
                $this->until = new \DateTime();
                break;
            case 'last_month':
                $this->since = new \DateTime("first day of last month");
                $this->until = new \DateTime("last day of last month");
                break;

            case 'last_two_months':
                $this->since = (new \DateTime("first day of last month"))->modify('-1 month');
                $this->until = (new \DateTime("last day of last month"));
                break;

            case 'previous_month':
                $this->since = new \DateTime("first day of -2 month");
                $this->until = new \DateTime("last day of -2 month");
                break;

            case 'two_months_ago':
                $this->since = new DateTime("first day of -3 month");
                $this->until = new DateTime("last day of -3 month");
                break;

            case 'this_year':
                $this->since = new DateTime('first day of January ' . date('Y'));
                $this->until = new DateTime("last day of December" . date('Y'));
                break;

            case 'January':  //1
            case 'February': //2
            case 'March':    //3
            case 'April':    //4
            case 'May':      //5
            case 'June':     //6
            case 'July':     //7
            case 'August':   //8
            case 'September'://9
            case 'October':  //10
            case 'November': //11
            case 'December': //12
                $this->since = new DateTime('first day of ' . $scope . ' ' . date('Y'));
                $this->until = new DateTime('last day of ' . $scope . ' ' . date('Y'));
                break;

            default:
                throw new \Exception('Unknown scope ' . $scope);
                break;
        }
        $this->since = $this->since->setTime(0, 0);
        $this->until = $this->until->setTime(0, 0);
    }

    /**
     *
     * @param type $categoriesRaw
     */
    public function prepareCategories($categoriesRaw)
    {
        $categories = [];
        foreach ($categoriesRaw as $tree) {
            $categories[] = $this->categoryBranch(explode(' > ', $tree));
        }
        return $categories;
    }

    /**
     * Create ale nodes in category tree
     *
     * @param array $nodes
     * @return type
     */
    public function categoryBranch(array $nodes)
    {
        $parent = '';
        foreach ($nodes as $level => $node) {
            $parent = $this->createBranchNode($node, $level, $parent);
        }
        return $parent;
    }

    /**
     *
     * @param string $node
     * @param int    $level
     * @param string $parent
     *
     * @return string
     */
    public function createBranchNode(string $node, int $level, string $parent)
    {
        $kod = \AbraFlexi\RO::code(substr(\Ease\Functions::rip(substr(\AbraFlexi\RO::uncode($parent), 0, 10) . str_replace(' ', '', $node)), 0, 30));
        if (array_key_exists($level, $this->levels)) {
            $this->levels[$level]++;
        } else {
            $this->levels[$level] = 1;
        }
        $strom = new \AbraFlexi\Strom($kod, ['ignore404' => true]);
        if ($strom->lastResponseCode == 404) {
            $strom->setDataValue('id', $kod);
            $strom->setDataValue('nazev', $node);
            $strom->setDataValue('strom', \AbraFlexi\RO::code(self::$ROOT));
            if ($parent) {
                $strom->setDataValue('otec', $parent);
            }
            $strom->setDataValue('poradi', $this->levels[$level]);
            //$strom->setDataValue('hladina',$level);

            $strom->addStatusMessage('Create Category ' . $node, $strom->sync() ? 'success' : 'error');
        }
        $this->treeCache[$strom->getRecordCode()] = $strom->getMyKey();
        return $strom->getRecordCode();
    }

    /**
     *
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
        $root = new \AbraFlexi\RW(\AbraFlexi\RO::code(self::$ROOT), ['evidence' => 'strom-koren', 'ignore404' => true]);
        return $root->lastResponseCode == 200 ? true : $root->insertToAbraFlexi($discpmpData);
    }

    /**
     *
     * @param string $manufacturerName
     * @return \AbraFlex\Adresar
     */
    public function findManufacturerCode(string $manufacturerName)
    {
        $manufacturerCode = \AbraFlexi\RO::code($manufacturerName);
        $manufacturer = new \AbraFlexi\Adresar($manufacturerCode, ['ignore404' => true]);
        if ($manufacturer->lastResponseCode == 404) {
            $manufacturer->addStatusMessage(sprintf(_('New Manufacturer %s'), $manufacturerName), $manufacturer->sync(['kod' => \AbraFlexi\RO::uncode($manufacturerName), 'nazev' => $manufacturerName]) ? 'success' : 'error');
        }
        return $manufacturer;
    }
}
