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

use PHPUnit\Framework\TestCase;

class ApiClientTest extends TestCase
{
    private $apiClient;

    protected function setUp(): void
    {
        $this->apiClient = new ApiClient('testuser', 'testpassword');
    }

    public function testCurlInit(): void
    {
        $curl = $this->apiClient->curlInit();
        $this->assertIsResource($curl);
    }

    public function testDoCurlRequest(): void
    {
        $this->apiClient->curlInit();
        $responseCode = $this->apiClient->doCurlRequest('https://httpbin.org/get');
        $this->assertEquals(200, $responseCode);
    }

    public function testGetResponseMime(): void
    {
        $this->apiClient->curlInit();
        $this->apiClient->doCurlRequest('https://httpbin.org/get');
        $mime = $this->apiClient->getResponseMime();
        $this->assertEquals('application/json', $mime);
    }

    public function testGetErrors(): void
    {
        $this->apiClient->curlInit();
        $this->apiClient->doCurlRequest('https://httpbin.org/status/404');
        $error = $this->apiClient->getErrors();
        $this->assertNotEmpty($error);
    }

    public function testGetLastResponseCode(): void
    {
        $this->apiClient->curlInit();
        $this->apiClient->doCurlRequest('https://httpbin.org/get');
        $responseCode = $this->apiClient->getLastResponseCode();
        $this->assertEquals(200, $responseCode);
    }

    public function testGetLastCurlResponseBody(): void
    {
        $this->apiClient->curlInit();
        $this->apiClient->doCurlRequest('https://httpbin.org/get');
        $responseBody = $this->apiClient->getLastCurlResponseBody();
        $this->assertNotEmpty($responseBody);
    }

    public function testXml2array(): void
    {
        $xml = '<root><child>value</child></root>';
        $array = ApiClient::xml2array(new \SimpleXMLElement($xml));
        $this->assertEquals(['child' => 'value'], $array);
    }

    public function testDisconnect(): void
    {
        $this->apiClient->curlInit();
        $this->apiClient->disconnect();
        $this->assertNull($this->apiClient->curl);
    }

    public function testGetResult(): void
    {
        $this->apiClient->curlInit();
        $this->apiClient->doCurlRequest('https://httpbin.org/xml');
        $result = $this->apiClient->getResult('test');
        $this->assertIsArray($result);
    }

    public function testGetResultByFromTo(): void
    {
        $this->apiClient->curlInit();
        $from = new \DateTime('2021-01-01');
        $to = new \DateTime('2021-12-31');
        $result = $this->apiClient->getResultByFromTo('test', $from, $to);
        $this->assertIsArray($result);
    }

    public function testGetResultByCode(): void
    {
        $this->apiClient->curlInit();
        $result = $this->apiClient->getResultByCode('StoItemBase', 'testcode');
        $this->assertIsArray($result);
    }

    public function testGetItemByCode(): void
    {
        $this->apiClient->curlInit();
        $result = $this->apiClient->getItemByCode('testcode');
        $this->assertIsArray($result);
    }

    public function testGetImage(): void
    {
        $this->apiClient->curlInit();
        $image = $this->apiClient->getImage('https://httpbin.org/image/png');
        $this->assertNotEmpty($image);
    }

    public function testGetImageMultipleTimes(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $image = $this->apiClient->getImage('https://httpbin.org/image/png');
            $this->assertNotEmpty($image, "Image download failed on iteration $i");
        }
    }
}
