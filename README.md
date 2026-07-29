
![Packaging: deb](https://img.shields.io/badge/packaging-.deb-red?logo=debian&logoColor=white)
![discomp2abraflexi](social-preview.svg?raw=true)

Konfigurace
-----------

* `ABRAFLEXI_URL` - Adresa na které je API
* `ABRAFLEXI_LOGIN` - - Uživatel API
* `ABRAFLEXI_PASSWORD` - Heslo do AbraFlexi
* `ABRAFLEXI_COMPANY` - API kód firmy do ktera naskladňuje
* `ABRAFLEXI_STORAGE` - Kód výchozího skladu pro import
* `ABRAFLEXI_DISCOMP_CODE` - Kód pod kterým je Discomp s.r.o. v adresáři

* `DISCOMP_SCOPE` - V jakém časovém intervalu importovat aktualizace. "all" pro všechny dostupné. "yesterday" nové produkty přidané včera.

* `DISCOMP_USERNAME`
* `DISCOMP_PASSWORD`

* `DISCOMP_TYP_ZASOBY` - Položky do ceníku importovat jako. Výchozí hodnota: `typZasoby.material`
* `EASE_LOGGER` - Kam logovat. Doporučená hodnota: `console|syslog`

Import Scopes
-------------

* `today`
* `yesterday`
* `last_week`
* `last_month`
* `last_two_months`
* `previous_month`
* `two_months_ago`
* `this_year`
* `January`  
* `February`
* `March`
* `April`
* `May`
* `June`
* `July`
* `August`
* `September`
* `October`
* `November`
* `December`
* `2024-08-05>2024-08-11` - custom scope
* `2024-10-11` - only specific day

Run In Container
----------------

<https://hub.docker.com/repository/docker/vitexsoftware/discomp2abraflexi>

```shell
docker run --env-file .env vitexsoftware/discomp2abraflexi:latest
```

```shell
podman run --env-file .env docker.io/vitexsoftware/discomp2abraflexi:latest
```

Podman first run output:

```shell
$ podman run --env-file .env docker.io/vitexsoftware/discomp2abraflexi:latest
Trying to pull docker.io/vitexsoftware/discomp2abraflexi:latest...
Getting image source signatures
Copying blob 249ff3a7bbe6 done  
Copying blob 48824c101c6a done  
Copying blob 8df282322d1b done  
Copying blob 1f7ce2fa46ab done  
Copying blob ae6ba28dd781 done  
Copying blob aa5d47f22b64 done  
Copying blob c244af8d9658 done  
Copying blob c1286f5f47fc done  
Copying blob 8c9c8132d2d8 done  
Copying blob 5ddaa1b2c3d8 done  
Copying blob b08c405e0c7c done  
Copying blob 262c618b663f done  
Copying blob 55a741b87e7a done  
Copying blob 1d30d31414f7 done  
Copying blob 4f4fb700ef54 done  
Copying blob 7a1bd9b9b4d5 done  
Copying blob cafa0330c197 done  
Copying blob 4f4fb700ef54 skipped: already exists  
Copying config 4525446344 done  
Writing manifest to image destination
Storing signatures
11/28/2023 11:14:22 ⚙ ❲Discomp2AbraFlexi⦒SpojeNet\Discomp\Importer❳ Discomp2AbraFlexi EaseCore dev-main (PHP 8.2.13)
11/28/2023 11:14:22 🌼 ❲Discomp2AbraFlexi⦒SpojeNet\Discomp\Importer❳ Supplier Exists
11/28/2023 11:14:22 🌼 ❲Discomp2AbraFlexi⦒SpojeNet\Discomp\Importer❳ Category Root Exists

Fatal error: Uncaught Exception: https://WWW.discomp.CZ/i6ws/default.asmx/GetResultByFromTo?resultType=StoItemShop_El&from=2023-11-20T00:00:00&to=2023-11-26T00:00:00
System.ApplicationException: Unsupported hour: '12' for method: 'GetResultByFromTo' of resultType: 'StoItemShop_El'.
   v CyberSoft.I6.Web.WebService.HelpLib.GetResult(String method, String resultType, Hashtable runTimeValues)
   v CyberSoft.I6.Web.WebService.HelpLib.GetResultByFromTo(String resultType, DateTime from, DateTime to)
   v CyberSoft.I6.Web.WebService.I6Ws.GetResultByFromTo(String resultType, DateTime from, DateTime to)
 in /usr/src/discomp2abraflexi/src/Discomp/ApiClient.php:273
Stack trace:
#0 /usr/src/discomp2abraflexi/src/Discomp/Importer.php(190): SpojeNet\Discomp\ApiClient->getResultByFromTo('StoItemShop_El', Object(DateTime), Object(DateTime))
#1 /usr/src/discomp2abraflexi/src/Discomp/Importer.php(197): SpojeNet\Discomp\Importer->getFreshItems()
#2 /usr/src/discomp2abraflexi/src/discomp2abraflexi.php(34): SpojeNet\Discomp\Importer->freshItems()
#3 {main}
  thrown in /usr/src/discomp2abraflexi/src/Discomp/ApiClient.php on line 273
```

## Exit Codes

This application uses the following exit codes:

- `0`: Success
