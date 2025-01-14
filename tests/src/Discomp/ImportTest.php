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

use AbraFlexi\RW;
use PHPUnit\Framework\TestCase;

class ImportTest extends TestCase
{
    private $importer;

    protected function setUp(): void
    {
        $this->importer = new Importer();
    }

    public function testConstructor(): void
    {
        $this->assertInstanceOf(Importer::class, $this->importer);
        $this->assertInstanceOf(ApiClient::class, $this->importer->discomper);
    }

    public function testAbraFlexiInit(): void
    {
        $this->importer->abraFlexiInit();
        $this->assertInstanceOf(\AbraFlexi\Cenik::class, $this->importer->sokoban);
        $this->assertInstanceOf(\AbraFlexi\Dodavatel::class, $this->importer->pricer);
        $this->assertInstanceOf(\AbraFlexi\StromCenik::class, $this->importer->category);
        $this->assertInstanceOf(RW::class, $this->importer->atribut);
        $this->assertInstanceOf(RW::class, $this->importer->atributType);
    }

    public function testAbraFlexiDisconnect(): void
    {
        $this->importer->abraFlexiDisconnect();
        $this->assertNull($this->importer->sokoban);
        $this->assertNull($this->importer->suplier);
        $this->assertNull($this->importer->pricer);
        $this->assertNull($this->importer->category);
        $this->assertNull($this->importer->atribut);
        $this->assertNull($this->importer->atributType);
    }

    public function testEnsureSupplierExists(): void
    {
        $result = $this->importer->ensureSupplierExists();
        $this->assertIsBool($result);
    }

    public function testGetFreshItems(): void
    {
        $items = $this->importer->getFreshItems();
        $this->assertIsArray($items);
    }

    public function testFreshItems(): void
    {
        $this->importer->freshItems();
        $this->assertTrue(true); // Assuming no exceptions are thrown
    }

    public function testSyncProperty(): void
    {
        $this->importer->syncProperty('TestName', 'TestValue');
        $this->assertTrue(true); // Assuming no exceptions are thrown
    }

    public function testAllTimeItems(): void
    {
        $this->importer->allTimeItems();
        $this->assertTrue(true); // Assuming no exceptions are thrown
    }

    public function testUpdatePrice(): void
    {
        $activeItemData = [
            'CODE' => 'testcode',
            'PURCHASE_PRICE' => 100,
            'CURRENCY' => 'USD',
            'PART_NUMBER' => 'testpartnumber',
            'STOCK' => ['AMOUNT' => 10],
        ];
        $this->importer->updatePrice($activeItemData);
        $this->assertTrue(true); // Assuming no exceptions are thrown
    }

    public function testScopeToInterval(): void
    {
        $this->importer->scopeToInterval('yesterday');
        $this->assertInstanceOf(\DateTime::class, $this->importer->since);
        $this->assertInstanceOf(\DateTime::class, $this->importer->until);
    }

    public function testPrepareCategories(): void
    {
        $categories = $this->importer->prepareCategories(['Category1', 'Category2']);
        $this->assertIsArray($categories);
    }

    public function testCategoryBranch(): void
    {
        $branch = $this->importer->categoryBranch(['Node1', 'Node2']);
        $this->assertIsString($branch);
    }

    public function testCreateBranchNode(): void
    {
        $node = $this->importer->createBranchNode('Node', 1, 'Parent', 'Code');
        $this->assertIsString($node);
    }

    public function testEnsureCategoryRootExists(): void
    {
        $result = $this->importer->ensureCategoryRootExists();
        $this->assertIsInt($result);
    }

    public function testFindManufacturerCode(): void
    {
        $manufacturer = $this->importer->findManufacturerCode('TestManufacturer');
        $this->assertInstanceOf(\AbraFlexi\Adresar::class, $manufacturer);
    }

    public function testRemoveItemFromTree(): void
    {
        $item = $this->createMock(\AbraFlexi\Cenik::class);
        $result = $this->importer->removeItemFromTree($item);
        $this->assertIsInt($result);
    }
}
