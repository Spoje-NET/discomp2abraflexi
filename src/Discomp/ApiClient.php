<?php

declare(strict_types=1);

/**
 *
 *
 * @author     Vítězslav Dvořák <info@vitexsoftware.cz>
 * @copyright  2023 Vitex Software
 */

namespace SpojeNet\Discomp;

/**
 * Description of DiscordWs
 *
 * @see https://www.discomp.cz/i6ws/ReadMe.txt
 *
 * @author vitex
 */
class ApiClient extends \Ease\Molecule
{
    use \Ease\Logger\Logging;

    /**
     * Discomp URI
     * @var string
     */
    public $baseEndpoint = 'https://WWW.discomp.CZ/i6ws/default.asmx';

    /**
     * CURL resource handle
     * @var resource|\CurlHandle|null
     */
    private $curl;

    /**
     * CURL response timeout
     * @var int
     */
    private $timeout = 0;

    /**
     * Last CURL response info
     * @var array
     */
    private $curlInfo = [];

    /**
     * Last CURL response error
     * @var string
     */
    private $lastCurlError;

    /**
     * Throw Exception on error ?
     * @var boolean
     */
    public $throwException = true;

    /**
     * Discomp Username
     * @var string
     */
    private $apiUsername;

    /**
     * Discomp User password
     * @var string
     */
    private $apiPassword;

    /**
     * May be huge response
     * @var string|boolean
     */
    private $lastCurlResponse;

    /**
     * HTTP Response code of latst request
     * @var int
     */
    private $lastResponseCode;

    /**
     * Debug mode
     * @var boolean
     */
    public $debug = false;

    /**
     * Discomp Data obtainer
     *
     * @param string $username - leave empty to use Environment or constant DISCOMP_USERNAME
     * @param string $password - leave empty to use Environment or constant DISCOMP_PASSWORD
     */
    public function __construct($username = '', $password = '')
    {
        $this->apiUsername = strlen($username) ? $username : \Ease\Shared::cfg('DISCOMP_USERNAME');
        $this->apiPassword = strlen($password) ? $password : \Ease\Shared::cfg('DISCOMP_PASSWORD');
        $this->debug = \Ease\Shared::cfg('DISCOMP_API_DEBUG', false);
        $this->curlInit();
        $this->setObjectName();
    }

    /**
     * Initialize CURL
     *
     * @return mixed|boolean Online Status
     */
    public function curlInit()
    {
        $this->curl = \curl_init(); // create curl resource
        \curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true); // return content as a string from curl_exec
        \curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true); // follow redirects
        \curl_setopt($this->curl, CURLOPT_HTTPAUTH, true); // HTTP authentication
        \curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, true);
        \curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
        \curl_setopt($this->curl, CURLOPT_VERBOSE, ($this->debug === true)); // For debugging
        if ($this->timeout) {
            \curl_setopt($this->curl, CURLOPT_HTTPHEADER, [
                'Connection: Keep-Alive',
                'Keep-Alive: ' . $this->timeout
            ]);
            \curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->timeout);
        }
        if (empty($this->authSessionId)) {
            \curl_setopt(
                $this->curl,
                CURLOPT_USERPWD,
                $this->apiUsername . ':' . $this->apiPassword
            ); // set username and password
        }
        \curl_setopt(
            $this->curl,
            CURLOPT_USERAGENT,
            'DiscompTakeout v' . \Ease\Shared::appVersion() . ' https://github.com/Spoje-NET/Discomp2AbraFlexi'
        );
        return $this->curl;
    }

    /**
     * Execute HTTP request
     *
     * @param string $url    URL of request
     * @param string $method HTTP Method GET|POST|PUT|OPTIONS|DELETE
     *
     * @return int HTTP Response CODE
     */
    public function doCurlRequest($url, $method = 'GET', $postParams = [])
    {
        curl_setopt($this->curl, CURLOPT_URL, $url);

        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        $this->lastCurlResponse = curl_exec($this->curl);
        $this->curlInfo = curl_getinfo($this->curl);
        $this->curlInfo['when'] = microtime();
        $this->lastResponseCode = $this->curlInfo['http_code'];
        $this->lastCurlError = curl_error($this->curl);
        if (strlen($this->lastCurlError)) {
            $msg = sprintf('Curl Error (HTTP %d): %s', $this->lastResponseCode, $this->lastCurlError);
            $this->addStatusMessage($msg, 'error');
            if ($this->throwException) {
                throw new \Ease\Exception($msg, $this);
            }
        }
        return $this->lastResponseCode;
    }

    /**
     * Mime Type of last server response
     *
     * @return string
     */
    public function getResponseMime()
    {
        return array_key_exists('content_type', $this->curlInfo) ? $this->curlInfo['content_type'] : 'text/plain';
    }

    /**
     * Curl Error getter
     *
     * @return string
     */
    public function getErrors()
    {
        return $this->lastCurlError;
    }

    /**
     * Response code of last HTTP operation
     *
     * @return int
     */
    public function getLastResponseCode()
    {
        return $this->lastResponseCode;
    }

    /**
     * Latest Data obtained
     *
     * @return string
     */
    public function getLastCurlResponseBody()
    {
        return $this->lastCurlResponse;
    }

    /**
     * Convert XML to array
     */
    public static function xml2array($xmlObject, $out = [])
    {
        foreach ((array) $xmlObject as $index => $node) {
            $out[$index] = (is_object($node) || is_array($node)) ? self::xml2array($node) : $node;
        }
        return $out;
    }

    /**
     * Discomp server disconnect.
     */
    public function disconnect()
    {
        if (is_resource($this->curl)) {
            curl_close($this->curl);
        }
        $this->curl = null;
    }

    /**
     * Close Curl Handle before serizaliation
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     *
     * @see https://www.discomp.cz/i6ws/ResultTypeInfo.ashx
     *
     * @param string $resultType
     *
     * @return array
     */
    public function getResult($resultType)
    {
        $this->doCurlRequest($this->baseEndpoint . '/GetResult?resultType=' . $resultType);
        if ($this->lastCurlResponse[0] != '<') {
            throw new \Exception($this->lastCurlResponse);
        }
        return current(self::xml2array(new \SimpleXMLElement(html_entity_decode($this->lastCurlResponse))));
    }

    /**
     *
     * @param string    $resultType
     * @param \DateTime $from
     * @param \DateTime $to
     *
     * @return array
     */
    public function getResultByFromTo(string $resultType, \DateTime $from, \DateTime $to)
    {
        $this->doCurlRequest($this->baseEndpoint .
                '/GetResultByFromTo?resultType=' . $resultType .
                '&from=' . $from->format('Y-m-d\T00:00:00') .
                '&to=' . $to->format('Y-m-d\T00:00:00'));
        if ($this->lastCurlResponse[0] != '<') {
            throw new \Exception($this->curlInfo['url'] . "\n" . html_entity_decode($this->lastCurlResponse));
        }
        return current(self::xml2array(new \SimpleXMLElement($this->lastCurlResponse)));
    }

    /**
     * Everything about product
     *
     * @param string $stoItemBase
     * @param string $code
     *
     * @return array
     */
    public function getResultByCode($stoItemBase, $code)
    {
        $this->doCurlRequest($this->baseEndpoint . '/GetResultByCode?resultType=' . $stoItemBase . '&code=' . $code);
        if ($this->lastCurlResponse[0] != '<') {
            throw new \Exception(html_entity_decode($this->lastCurlResponse));
        }

        /*
          <Result UrlBase="https://www.discomp.cz/default.asp?cls=stoitem&amp;stiid=" UrlBaseThumbnail="https://www.discomp.cz/img.asp?attname=thumbnail&amp;attpedid=52&amp;attsrcid=" UrlBaseImg="https://www.discomp.cz/img.asp?stiid=" UrlBaseEnlargement="https://www.discomp.cz/img.asp?attname=enlargement&amp;attpedid=52&amp;attsrcid=" UrlBaseImgGalery="https://www.discomp.cz/img.asp?attid=" CouCode="CZ" TaxRateLow="15" TaxRateHigh="21">
          <StoItem Id="119012" Code="20235962" Code2="KOBFS3410" PartNo="FS3410" Name="NAS Synology FS3410 All-flash server, 2x10Gb + 4x1Gb LAN, redund.zdroj" ManName="Synology" PriceEU="169540.0000" PriceDea="153330.0000" PriceOrd="153330.0000" PriceRef="0.0000" PriceRef2="0.0000" TaxRate="21.0000" CutCode="?" WarDur="744" WarDurEU="744" SNTrack="1" ScaId="164" Note="&lt;p&gt;&amp;nbsp; &amp;nbsp;&lt;/p&gt;">
          <ImgGal />
          </StoItem>
          </Result>

          <Result>
          <StoItem />
          </Result>

          <Result>
          <StoItem Id="119012" Code="20235962" Code2="KOBFS3410" PartNo="FS3410" PriceEU="169540.0000" />
          </Result>

          <Result>
          <StoItem Id="119012" Code="20235962" Code2="KOBFS3410" PartNo="FS3410" PriceOrd="153330.0000" PriceEU="169540.0000" PriceRef="0.0000" PriceRef2="0.0000" TaxRate="21.0000" />
          </Result>

          <Result CurCode="CZK">
          <StoItem Id="119012" Code="20235962" Code2="KOBFS3410" PartNo="FS3410" PriceOrd="153330.0000" />
          </Result>
         */
        return json_decode(json_encode(is_bool($this->lastCurlResponse) ? [] : new \SimpleXMLElement($this->lastCurlResponse)), true);
    }

    /**
     *
     * @param string $code
     *
     * @return array
     */
    public function getItemByCode($code)
    {
        return [
            'StoItemBase' => $this->getResultByCode('StoItemBase', $code),
//            'StoItemEAN' => $this->getResultByCode('StoItemEAN', $code),
//            'StoItemPriceEU' => $this->getResultByCode('StoItemPriceEU', $code),
//            'StoItemPriceOrd' => $this->getResultByCode('StoItemPriceOrd', $code),
//            'StoItemPriceOrdCur' => $this->getResultByCode('StoItemPriceOrdCur', $code)
        ];
    }

    public function getImage($baseImageUrl)
    {
        $this->doCurlRequest($baseImageUrl);
        return $this->lastCurlResponse;
    }
}
