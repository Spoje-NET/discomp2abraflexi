ZAKLADNI POPIS
---------------------------------------------------------------------

Webova sluzba je zprovoznena na eShopu distrubutora jako aplikacni adresar /i6ws/
http://WWW.CYBERSOFT.CZ/i6ws/
(WWW.CYBERSOFT.CZ nahradte domenou distributora)

Pristup na webovou sluzbu nemusi byt stejny jako eShop.
Casto pro webovou sluzby byva vydavan separatni pristup,
aby pripadne zmeny hesla na eShopu a pod.
nenarusily chod automatizovaneho zpracovani dat ze sluzby a pod.

Se sluzbou lze komunikovat pomoci protokolu SOAP.
U metod s jednoduchymi parametry (exporty) take pomoci GET/POST,
takze XML exporty lze stahovat/ziskat obycejnym odkazem.

Na sluzbu se lze napojit nejen programove - poskytuje popis sluzby pomoci WSDL,
ale lze ji take pouzivat/zkouset browserem.
Sluzba ma jednoduche rozhrani, kde zobrazuje vypis metod, strucny popis,
u nekterych take zkusebni formulare, kterymi lze metody rovnou z browseru vyvolat.

Sluzba umi vracene XML data komprimovat (gzip, deflate),
pokud se pozadavek vysle s hlavickou:
Accept-Encoding: gzip, deflate


EXPORTY DAT
---------------------------------------------------------------------
Poskytuje sluzba/stranka:
Default.asmx
http://WWW.CYBERSOFT.CZ/i6ws/Default.asmx

Exporty dat lze ziskat pomoci obecnych metod:
  GetResult - vraci vsechna data (resp. presneji zakladni mnozinu bez blizsi specifikace - u produktu - vse, u stavu skladu - vse skladem, u objednavek - jen otevrene a pod.)
  GetResultByCode - vraci data filtrovana dle jednoznacneho kodu - typicky jen jeden radek (u produktovych exportu se lze dotazovat i pres PartNo, kdyz vyhledavaci retezec zacina na {PartNo})
  GetResultByFromTo - vraci data filtrovana dle datumu od/do - typicky na zaklade evidovane zmeny

Seznam exportu, ktere lze ziskat, poskytne stranka s prehledem exportu:
http://WWW.CYBERSOFT.CZ/i6ws/ResultTypeInfo.ashx
Vyznam sloupcu tabulky:
  ResultType - Nazev exportu (predava se jako argument metod resultType)
  Schema - Po kliknuti se zobrazi jednoduche schema => Lze vycist vracene attributy(sloupce)
  GetResult - Informace o moznostech / casech pouziti metody GetResult
  ByCode - Informace o moznostech / casech pouziti metody GetResultByCode
  ByFromTo - Informace o moznostech / casech pouziti metody GetResultByFromTo
    => pokud je vse vysedle, tak metoda neni pro dany resultType implementovana (nelze pouzit)
    => pokud jsou vysedle nejake dny v |Allowed days of week| nejde metoda dany den zavolat (napr. pro pouziti jen o vikendu)
    => pokud jsou vysedle nejake hodiny v |Allowed hours| nejde metoda danou hodinu zavolat (napr. pro pouziti jen mimo hlavni prac. dobu)
  Description - Popis exportu
  
Jenoduche stazeni pomoci metody GET
-----------------------------
Zakladni syntax URL:
http://JMENO:HESLO@WWW.CYBERSOFT.CZ/i6ws/Default.asmx/NAZEV_METODY?resultType=NAZEV_RESULTU&PARAMETRY
UCASE hodnoty v URL se nahrazuji:
  JMENO - Prihlasovani jmeno do webove sluzby
  HESLO - Prihlasovani heslo do webove sluzby
  NAZEV_METODY - GetResult | GetResultByCode | GetResultByFromTo
  PARAMETRY
    GetResult - resultType=
    GetResultByCode - resultType= &code=
    GetResultByFromTo - resultType= &from= &to= 
Pozn.:
Aby fungovala syntax: JMENO:HESLO@ s logovacimi udaji primo v linku,
je treba pri rucnim zkouseni za pouziti Internet Exploreru to explicitne povolit:
http://support.microsoft.com/kb/834489
Jinak pouzivat bez uvedeni JMENO:HESLO@ v URL,
prohlizec pri prvnim pozadavku zobrazi prihlasovaci dialog.
    
Priklady URL:
  zakladni vlastnosti vsech produktu:
    => Default.asmx/GetResult?resultType=StoItemBase
  zakladni vlastnosti jednoho produktu 600623:
    => Default.asmx/GetResultByCode?resultType=StoItemBase&code=600623
  zakladni vlastnosti jednoho produktu 600623:
    => Default.asmx/GetResultByCode?resultType=StoItemBase&code=600623
  info o stavu skladu jednoho produktu 600623 dle PartNo:
    => Default.asmx/GetResultByCode?resultType=StoItemQtyFree&code={PartNo}600623
  info o cene jednoho produktu 600623:   
    => Default.asmx/GetResultByCode?resultType=StoItemPriceOrd&code=600623
  vsechny produkty skladem:
    => Default.asmx/GetResult?resultType=StoItemQtyFree
  zakladni vlastnosti vice produktu, u kterych doslo ke zmene mezi 13.06.2005-06.06.2079:
    => Default.asmx/GetResultByFromTo?resultType=StoItemBase&from=2005-06-13&to=2079-06-06

Ukazkove stazeni s vyslednym ulozenim XML souboru pomoci VBScriptu:
'--------------------------------------------------------------------
Option Explicit
Main
Private Sub Main
  Dim strUrl, strFile
  strUrl = "http://JMENO:HESLO@WWW.CYBERSOFT.CZ/i6ws/Default.asmx/GetResultByCode?resultType=StoItemBase&code=600623"
  strFile = "C:\StoItemBase.xml"
  With CreateObject("MSXML2.XMLHTTP")
    WScript.Echo "Opening url: " & strUrl
    .Open "GET", strUrl, False
    .Send
    If .Status <> 200 Then Err.Raise vbObjectError + 1, "GetResponseXml", "Bad response status: [" & .Status & "] " & .StatusText & vbCrLf & .ResponseText
    WScript.Echo "Saving file: " & strFile
    .ResponseXml.Save strFile
    WScript.Echo "Done."
  End With
End Sub
'--------------------------------------------------------------------
Pokud se obsah bloku ulozi do textoveho souboru s nazvem: i6ws_client.vbs
Lze jej pak spustit v prikazovem radku CMD.EXE jako:
cscript.exe i6ws_client.vbs
Script provede stazeni zakladnich informaci o produktu 600623
a jeho ulozeni do souboru: C:\StoItemBase.xml


Popis struktury exportu StoItemBase
-----------------------------
StoItemBase je zakladni export poskytujici informace produktech.
Dalsi produktove exporty jsou casto jeho podmnozinou (jen ceny, info o skladech a pod.)
Za pouziti URL z ukazky vyse:
http://JMENO:HESLO@WWW.CYBERSOFT.CZ/i6ws/Default.asmx/GetResultByCode?resultType=StoItemBase&code=600623
by byla stazena nasledujici XML struktura:

<Result
  UrlBase="string | zakladni URL pro tvorbu odkazu na produkt"
  UrlBaseThumbnail="string | zakladni URL pro tvorbu odkazu na obrazek - maly"
  UrlBaseImg="string | zakladni URL pro tvorbu odkazu na obrazek - bezny"
  UrlBaseEnlargement="string | zakladni URL pro tvorbu odkazu na obrazek - velky">
    <StoItem
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
       />
</Result>

Pro usporu velikosti XML nejsou nektere attributy obsahujici
  prazdne texty - napr.: Code2, PartNo, PartNo2, NameAdd, NameE, ManName, SisName, NoteShort, Note
  NULLove/neprirazene hodnoty - napr.: PriceRef, RefProName, RefCode, PriceRef2, RefProName2, RefCode2, WeightRef, MeasureRef2
  nulove =0 hodnoty - napr: QtyFreeIs, QtyFree, SNTrack, ThumbnailIs, ThumbnailSize, ImgIs, ImgSize, EnlargementIs, EnlargementSize
  vychozi hodnoty - napr.: QtyPack=1
vubec vraceny.

Blizsi komentar k nekterym atributum:
  QtyFreeIs | QtyFree
    => exportuje se jen jeden z udaju (na zaklade konfigurace webove sluzby u distributora)
  ThumbnailIs | ThumbnailSize + ImgIs | EnlargementSize + EnlargementIs | EnlargementSize
    => Drive exportovane info jen ve smyslu ANO/NE *Is lze nahradit nove pridanymi *Size (*Is = 1 je to stejne jako *Size > 0)
    *Is sloupce zustaly jen pro zpetnou kompatibilitu.
    Ze *Size lze detekovat zmenu obrazku (da se predpokladat, ze se zmenou obrazku se zmeni take jeho velikost)
  WarDur + WarDurEU
    => Zaruka je v systemu vedena jako Doba(2,24,...)+Jednoka(Rok,Mesic,...).
    Ve sluzbe je pro univerzalnost prepoctena na dny.
    Z takto exportovane hodnoty lze udelat zpetny prepocet:
      MAXINT=2147483647 - jedna se o dozivotni zaruku
      Je-li cislo beze zbytku delitelne 365 - jde udelat prepocet na roky
      Je-li cislo beze zbytku delitelne 31  - jde udelat prepocet na mesice.
   

Tvorba odkazu z exportovanych udaju:
Pro usporu mista jsou konstantni casti odkazu uvedeny v atributech jen jednou v korenovem elementu Result.
Vysledny odkaz se vytvori zretezenim atributu UrlBase* z korenoveho elementu Result
a atributem Id z radkoveho elementu StoItem, takze:
  UrlBase + Id = odkaz na stranku s detailem produktu na eShopu distributora
  UrlBaseThumbnail + Id = odkaz na obrazek (maly), ma smysl jen pokud ThumbnailSize > 0
  UrlBaseImg + Id = odkaz na obrazek (bezny), ma smysl jen pokud ImgSize > 0
  UrlBaseEnlargement + Id = odkaz na obrazek (velky), ma smysl jen pokud EnlargementSize > 0
  UrlBaseImgGalery + Id ze subelementu ImgGal pro obrazky z galerie



OBJEDNAVANI
---------------------------------------------------------------------
Poskytuje sluzba/stranka:
Order.asmx
http://WWW.CYBERSOFT.CZ/i6ws/Order.asmx

Sluzba nemusi byt u vsech distributoru nakonfigurovana / povolena.
Pokud sluzba neni funkcni konci vsechny volani metod sluzby stavovym kodem:
501 Not Implemented
Lze jednoduse vyzkouset GET pozadavkem:
http://JMENO:HESLO@WWW.CYBERSOFT.CZ/i6ws/Order.asmx/GetStatus?Id=0
(dotaz na neexistujici objednavku - vrati bud chybu 500 nebo 501 - vubec nezna metodu)

Vlastni popis objednavani pres webovou sluzbu je v samostatnem dokumentu:
OrderReadMe.txt
http://JMENO:HESLO@WWW.CYBERSOFT.CZ/i6ws/OrderReadMe.txt