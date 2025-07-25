<?php

/*
 +-----------------------------------------------------------------------+
 | Localization file of the Roundcube Webmail client                     |
 |                                                                       |
 | Copyright (C) The Roundcube Dev Team                                  |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 +-----------------------------------------------------------------------+

 For translation see https://www.transifex.com/projects/p/roundcube-webmail/resource/messages/
*/

$messages['errortitle']  = 'Došlo k chybě!';
$messages['loginfailed']  = 'Přihlášení selhalo.';
$messages['cookiesdisabled'] = 'Váš prohlížeč nepodporuje cookies, které jsou pro přihlášení nezbytné.';
$messages['sessionerror'] = 'Vaše přihlášení je neplatné nebo vypršelo.';
$messages['cookiesmismatch'] = 'Nalezena nekonzistentnost v cookies. Vymažte, prosím, cookies ve vašem prohlížeči.';
$messages['storageerror'] = 'Připojení na IMAP server selhalo.';
$messages['servererror'] = 'Chyba serveru!';
$messages['servererrormsg'] = 'Chyba serveru: $msg';
$messages['accountlocked'] = 'Příliš mnoho chybných pokusů o přihlášení. Zkuste to později.';
$messages['connerror'] = 'Chyba připojení (Selhalo připojeni k serveru)!';
$messages['dberror'] = 'Chyba databáze!';
$messages['dbconnerror'] = 'Nelze se připojit k databázi!';
$messages['windowopenerror'] = 'Vyskakovací okno bylo zablokováno!';
$messages['requesttimedout'] = 'Došlo k vypršení požadavku';
$messages['errorreadonly'] = 'Příkaz nelze provést, složka je určena jen ke čtení.';
$messages['errornoperm'] = 'Příkaz nelze provést, nemáte oprávnění.';
$messages['erroroverquota'] = 'Příkaz nelze provést, není volné místo na disku.';
$messages['erroroverquotadelete'] = 'Není volné místo na disku. Použijte SHIFT+DEL k odstranění zprávy.';
$messages['invalidrequest'] = 'Nesprávný požadavek. Data nebyla uložena.';
$messages['invalidhost'] = 'Špatné jméno serveru.';
$messages['nomessagesfound'] = 'Ve schránce nebyla nalezena žádná zpráva.';
$messages['loggedout'] = 'Byli jste odhlášeni. Na shledanou!';
$messages['mailboxempty'] = 'Schránka je prázdná';
$messages['nomessages'] = 'Žádné zprávy';
$messages['refreshing'] = 'Obnovuji...';
$messages['loading'] = 'Načítám...';
$messages['uploading'] = 'Nahrávám soubor...';
$messages['attaching'] = 'Přikládám soubor...';
$messages['uploadingmany'] = 'Nahrávám soubory...';
$messages['loadingdata'] = 'Načítám data...';
$messages['checkingmail'] = 'Kontroluji nové zprávy...';
$messages['sendingmessage'] = 'Odesílám zprávu...';
$messages['messagesent'] = 'Zpráva byla odeslána.';
$messages['savingmessage'] = 'Ukládám zprávu...';
$messages['messagesaved'] = 'Zpráva uložena do Rozepsané.';
$messages['successfullysaved'] = 'Uloženo.';
$messages['savingresponse'] = 'Ukládám text odpovědi...';
$messages['deleteresponseconfirm'] = 'Opravdu chcete odstranit tento text odpovědi?';
$messages['nocontactselected'] = 'Dosud jste nevybrali kontakt.';
$messages['addedsuccessfully'] = 'Kontakt byl přidán do adresáře.';
$messages['contactexists'] = 'Kontakt se zadanou e-mailovou adresou již existuje.';
$messages['contactnameexists'] = 'Kontakt se stejným jménem již existuje.';
$messages['blockedimages'] = 'Z bezpečnostních důvodů byly zablokovány obrázky ve zprávě.';
$messages['blockedresources'] = 'Z důvodu ochrany soukromí bylo zablokováno načtení vzdálených zdrojů.';
$messages['suspiciousemail'] = 'Zpráva obsahuje podezřelé adresáty, kteří mohou být podvodní.';
$messages['bccemail'] = 'Přeposílaná zpráva obsahuje důvěrné příjemce (Bcc).';
$messages['bouncehint'] = 'Zpráva bude odeslána bez dalších změn, t.j. bude použito původní tělo zprávy, hlavičky i příjemci.';
$messages['encryptedmessage'] = 'Tato zpráva je zašifrovaná a nelze ji zobrazit.';
$messages['externalmessagedecryption'] = 'Toto je šifrovaná zpráva a může být dešifrována pomocí rozšíření ve vašem prohlížeči.';
$messages['nopubkeyfor'] = 'Nenalezen validní veřejný klíč pro $email';
$messages['nopubkeyforsender'] = 'Pro Vaši identitu odesílatele se nenašel žádný platný veřejný klíč. Chcete správu zašifrovat pro příjemce?';
$messages['encryptnoattachments'] = 'Přílohy, které jste už uložili na server, není možné zašifrovat. Prosím, přidejte je znovu v editoru pro šifrování.';
$messages['searchpubkeyservers'] = 'Přejete si prohledat servery s klíči k nalezení chybějících klíčů?';
$messages['encryptpubkeysfound'] = 'Byly nalezeny následující veřejné klíče:';
$messages['keyservererror'] = 'Nebylo možné získat klíč ze serveru s klíči.';
$messages['keyimportsuccess'] = 'Veřejný klíč $key byl vložen do vaší klíčenky.';
$messages['nocontactsfound'] = 'Kontakty nebyly nalezeny.';
$messages['contactnotfound'] = 'Požadovaný kontakt nebyl nalezen.';
$messages['contactsearchonly'] = 'Pro zobrazení kontaktů ze zvoleného adresáře použijte vyhledávací formulář.';
$messages['sendingfailed'] = 'Odesílání zprávy selhalo.';
$messages['senttooquickly'] = 'Prosím počkejte $sec sekund před odesláním zprávy.';
$messages['errorsavingsent'] = 'Nastala chyba při ukládání odeslané zprávy.';
$messages['errorsaving'] = 'Vyskytla se chyba při ukládání.';
$messages['errormoving'] = 'Nemohu přesunout zprávu.';
$messages['errorcopying'] = 'Nemohu zkopírovat zprávu.';
$messages['errordeleting'] = 'Nemohu smazat zprávu.';
$messages['errormarking'] = 'Zprávu nelze označit.';
$messages['alerttitle'] = 'Pozor';
$messages['confirmationtitle'] = 'Jste si jistý...';
$messages['deletecontactconfirm']  = 'Opravdu chcete smazat označené kontakty?';
$messages['deletegroupconfirm']  = 'Opravdu chcete smazat skupinu?';
$messages['deletemessagesconfirm'] = 'Opravdu chcete smazat označené zprávy?';
$messages['deletefolderconfirm']  = 'Opravdu chcete smazat tento adresář?';
$messages['movefolderconfirm']  = 'Opravdu chcete přesunout tento adresář?';
$messages['purgefolderconfirm']  = 'Opravdu chcete smazat všechny zprávy v této složce?';
$messages['contactdeleting'] = 'Mažu kontakty...';
$messages['groupdeleting'] = 'Mažu skupinu...';
$messages['folderdeleting'] = 'Odstraňuji složku...';
$messages['foldermoving'] = 'Přesouvám složku...';
$messages['foldersubscribing'] = 'Přihlašuji složku k odběru...';
$messages['folderunsubscribing'] = 'Odhlašuji odběr složky...';
$messages['formincomplete'] = 'Formulář nebyl korektně vyplněn.';
$messages['noemailwarning'] = 'Zadejte, prosím, platnou e-mailovou adresu.';
$messages['nonamewarning']  = 'Zadejte, prosím, jméno.';
$messages['nopagesizewarning'] = 'Zadejte, prosím, velikost stránky.';
$messages['nosenderwarning'] = 'Zadejte e-mailovou adresu odesílatele.';
$messages['recipientsadded'] = 'Příjemci byli přidáni.';
$messages['norecipientwarning'] = 'Zadejte, prosím, alespoň jednoho příjemce.';
$messages['disclosedrecipwarning'] = 'Všichni příjemci uvidí adresy ostatních příjemců. Pro ochranu soukromí příjemců můžete použít pole Skrytá kopie (Bcc).';
$messages['disclosedreciptitle'] = 'Příliš mnoho veřejných příjemců';
$messages['nosubjectwarning']  = 'Předmět nebyl vyplněn. Přejete si jej zadat nyní?';
$messages['nosubjecttitle']  = 'Žádný předmět';
$messages['nobodywarning'] = 'Opravdu chtete odeslat prázdnou zprávu?';
$messages['notsentwarning'] = 'Zpráva nebyla odeslána a obsahuje neuložené změny. Přejete si změny zahodit?';
$messages['restoresavedcomposedata'] = 'Byla nalezena dříve vytvořená ale neodeslaná zpráva.\n\nPředmět: $subject\nUloženo: $date\n\nPřejete si obnovit tuto zprávu?';
$messages['nosearchname'] = 'Zadejte, prosím, jméno nebo e-mail kontaktu.';
$messages['notuploadedwarning'] = 'Ještě nebyly nahrány všechny přílohy. Počkejte prosím nebo nahrávání zrušte.';
$messages['searchsuccessful'] = 'Nalezeno $nr zpráv.';
$messages['contactsearchsuccessful'] = 'Nalezeno $nr kontaktů.';
$messages['searchnomatch'] = 'Nenalezena žádná zpráva';
$messages['searching'] = 'Vyhledávám...';
$messages['checking'] = 'Kontroluji...';
$messages['stillsearching'] = 'Stále vyhledávám...';
$messages['nospellerrors'] = 'Nebyly nalezeny žádné chyby.';
$messages['folderdeleted'] = 'Složka byla vymazána.';
$messages['foldersubscribed'] = 'Složka byla přihlášena k odběru.';
$messages['folderunsubscribed'] = 'Odběr složky byl odhlášen.';
$messages['folderpurged'] = 'Složka byla vyprázdněna.';
$messages['folderexpunged'] = 'Složka byla zkomprimována.';
$messages['deletedsuccessfully'] = 'Smazáno.';
$messages['converting'] = 'Odstraňuji formátování ze zprávy...';
$messages['messageopenerror'] = 'Nemohu načíst zprávu ze serveru.';
$messages['filelinkerror'] = 'Přikládání souboru selhalo.';
$messages['fileuploaderror'] = 'Nahrávání souboru selhalo.';
$messages['csvfilemismatch'] = 'Iimport více CSV souborů s různými poli není podporován.';
$messages['filesizeerror'] = 'Soubor přesáhl maximální velikost $size.';
$messages['filecounterror'] = 'Můžete nahrát maximálně $count souborů najednou.';
$messages['msgsizeerror'] = 'Selhalo připojení souboru. Byla překročena maximální velikost zprávy ($size).';
$messages['msgsizeerrorfwd'] = 'Překročena maximální délka zprávy ($size). Počet položek, které nebyly připojeny je $num.';
$messages['copysuccess'] = 'Zkopírováno $nr kontaktů.';
$messages['movesuccess'] = 'Přesunuto $nr kontaktů.';
$messages['copyerror'] = 'Kontakty nelze kopírovat.';
$messages['moveerror'] = 'Kontakty nelze přesunout.';
$messages['sourceisreadonly'] = 'Tento zdroj adres je pouze pro čtení.';
$messages['errorsavingcontact'] = 'Nemohu uložit adresu kontaktu.';
$messages['movingmessage'] = 'Přesouvám zprávu...';
$messages['copyingmessage'] = 'Kopíruji zprávu...';
$messages['copyingcontact'] = 'Kopíruji kontakty...';
$messages['movingcontact'] = 'Přesouvám kontakty...';
$messages['deletingmessage'] = 'Odstraňuji zprávu...';
$messages['markingmessage'] = 'Označuji zprávu...';
$messages['addingmember'] = 'Přidávám kontakty do skupiny...';
$messages['removingmember'] = 'Odstraňuji kontakty ze skupiny...';
$messages['receiptsent'] = 'Potvrzení o přřijetí zprávy odesláno.';
$messages['errorsendingreceipt'] = 'Potvrzení o přijetí zprávy nebylo možné odeslat.';
$messages['deleteidentityconfirm'] = 'Opravdu chcete odstranit tuto identitu?';
$messages['nodeletelastidentity'] = 'Alespoň jedna identita musí být ponechána. Identitu nelze odstranit.';
$messages['forbiddencharacter'] = 'Název složky obsahuje zakázaný znak.';
$messages['selectimportfile'] = 'Prosím vyberte soubor k nahrání.';
$messages['addresswriterror'] = 'Vybraný seznam kontaktů není zapisovatelný.';
$messages['contactaddedtogroup'] = 'Kontakty byly přidány do této skupiny.';
$messages['contactremovedfromgroup'] = 'Kontakty byly odstraněny z této skupiny.';
$messages['nogroupassignmentschanged'] = 'Nebylo změněno přiřazení do skupin.';
$messages['importwait'] = 'Importuji, prosím čekejte...';
$messages['importformaterror'] = 'Import se nezdařil! Typ souboru není podporován.';
$messages['importconfirm'] = 'Úspěšně naimportováno $inserted kontaktů, $skipped existujících záznamů přeskočeno: $names';
$messages['importmessagesuccess'] = 'Naimportováno $nr zpráv';
$messages['importmessageerror'] = 'Import se nezdařil! Nahraný soubor není platnou zprávou nebo souborem mailboxu';
$messages['opnotpermitted'] = 'Operace není povolena!';
$messages['nofromaddress'] = 'Chybějící e-mailová adresa v označeném profilu.';
$messages['editorwarning'] = 'Přepnutím režimu editoru ztratíte veškeré formátování. Chcete pokračovat?';
$messages['httpreceivedencrypterror'] = 'Vyskytla se vážná chyba v konfiguraci. Kontaktujte neprodleně administátora. Vaše zpráva nemohla být odeslána.';
$messages['smtpconnerror'] = 'Chyba SMTP ($code): Připojení k serveru selhalo.';
$messages['smtpautherror'] = 'Chyba SMTP ($code): Chyba přihlášení.';
$messages['smtpfromerror'] = 'Chyba SMTP ($code): Nelze nastavit odesílatele "$from" ($msg).';
$messages['smtptoerror'] = 'Chyba SMTP ($code): Nelze přidat příjemce "$to" ($msg).';
$messages['smtprecipientserror'] = 'Chyba SMTP: Nelze zpracovat seznam příjemců.';
$messages['smtputf8error'] = 'Chyba SMTP: Unicode v e-mailové adrese není podporován na serveru.';
$messages['smtpsizeerror'] = 'Chyba SMTP: Zpráva překročila limit serveru ($limit)';
$messages['smtperror'] = 'Chyba SMTP: $msg';
$messages['emailformaterror'] = 'Neplatná e-mailová adresa: $email';
$messages['toomanyrecipients'] = 'Příliš mnoho příjemců. Změňte počet příjemců na $max.';
$messages['maxgroupmembersreached'] = 'Počet členských skupin dosáhl maximum z $max';
$messages['internalerror'] = 'Došlo k interní chybě. Zkuste to prosím znovu.';
$messages['contactdelerror'] = 'Kontakty nelze odstranit.';
$messages['contactdeleted'] = 'Kontakty byly odstraněny.';
$messages['contactrestoreerror'] = 'Nelze obnovit smazané kontakty.';
$messages['contactrestored'] = 'Kontakty byly obnoveny.';
$messages['groupdeleted'] = 'Skupina byla odstraněna.';
$messages['grouprenamed'] = 'Skupina byla přejmenována.';
$messages['groupcreated'] = 'Skupina byla vytvořena.';
$messages['savedsearchdeleted'] = 'Uložené hledání bylo ostraněno.';
$messages['savedsearchdeleteerror'] = 'Nelze odstranit uložené hledání.';
$messages['savedsearchcreated'] = 'Nové hledání bylo vytvořeno.';
$messages['savedsearchcreateerror'] = 'Nelze vytvořit uložené hledání.';
$messages['messagedeleted'] = 'Zpráva byla odstraněna.';
$messages['messagemoved'] = 'Zpráva byla přesunuta.';
$messages['messagemovedtotrash'] = 'Zpráva byla přesunuta do koše.';
$messages['messagecopied'] = 'Zpráva byla zkopirována.';
$messages['messagemarked'] = 'Zpráva označena.';
$messages['autocompletechars'] = 'Napište alespoň $min znaků pro automatické doplnění.';
$messages['autocompletemore'] = 'Napište více znaků. Nalezeno příliš mnoho položek.';
$messages['namecannotbeempty'] = 'Jméno musí být vyplněno.';
$messages['nametoolong'] = 'Jméno je příliš dlouhé.';
$messages['namedotforbidden'] = 'Jméno složky nemůže začínat tečkou.';
$messages['folderupdated'] = 'Složka byla aktualizována.';
$messages['foldercreated'] = 'Složka byla vytvořena.';
$messages['invalidimageformat'] = 'Formát obrázku není podporován.';
$messages['mispellingsfound'] = 'Ve zprávě byly zjištěny pravopisné chyby.';
$messages['parentnotwritable'] = 'Nelze vytvořit/přesunout složku do vybrané rodičovské složky. Nemáte práva.';
$messages['messagetoobig'] = 'Část zprávy je příliš velká pro zpracování.';
$messages['attachmentvalidationerror'] = 'Tato příloha je podezřelá, protože její typ se neshoduje s typem uvedeným ve zprávě. Pokud nedůvěřujete odesílateli, neměli byste přílohu otevírat v prohlížeči, protože může obsahovat škodlivý obsah.<br/><br/><em>Očekáváno: $expected; nalezeno: $detected</em>';
$messages['noscriptwarning'] = 'Upozornění: Tato emailová aplikace vyžaduje Javascript! Prosím, zapněte Javascript v nastavení prohlížeče.';
$messages['messageissent'] = 'Zpráva již byla odeslána, ovšem ještě nebyla uložena. Přejete si ji uložit nyní?';
$messages['errnotfound'] = 'Soubor nebyl nalezen';
$messages['errnotfoundexplain'] = 'Požadovaný objekt nebyl nalezen!';
$messages['errfailedrequest'] = 'Požadavek zamítnut';
$messages['errauthorizationfailed'] = 'Autorizace selhala';
$messages['errunauthorizedexplain'] = 'Nemohu ověřit, zda máte právo přistupovat k této službě!';
$messages['errrequestcheckfailed'] = 'Stav Vašeho požadavku není možné ověřit.';
$messages['errcsrfprotectionexplain'] = "Pro ochranu Vašeho účtu je přístup do této sekce dočasně zablokován.  Existuje důvodné podezření, že Váš účet byl napaden.\nPokud vidíte toto upozornění, nejspíše jste se neodhlásili před opuštěním této stránky.\n\nAbyste mohli pokračovat, je nutné kontaktovat pověřenou osobu.";
$messages['errcontactserveradmin'] = 'Prosím, kontaktujte správce serveru.';
$messages['clicktoresumesession'] = 'Klikněte sem pro obnovu Vašeho předchozího sezení.';
$messages['errcomposesession'] = 'Chybná relace pro vytvoření zprávy';
$messages['errcomposesessionexplain'] = 'Požadovaná relace pro vytvoření zprávy nebyla nalezena.';
$messages['clicktocompose'] = 'Klikněte sem pro vytvoření nové zprávy';
$messages['nosupporterror'] = 'Tato funkcionalita není vaším prohlížečem podporována.';
$messages['siginserted'] = 'Podpis byl vložen.';
$messages['responseinserted'] = 'Odpověď byla vložena.';
$messages['noresponsesavailable'] = 'Žádné odpovědi nejsou k dispozici';
$messages['listempty'] = 'Seznam je prázdný.';
$messages['listusebutton'] = 'Použijte tlačítko "Vytvořit" pro přidání nového záznamu.';
$messages['keypaircreatesuccess'] = 'Pro $identity byl vytvořen nový pár klíčů.';
$messages['emptyattachment'] = 'Příloha je prázdná.<br>Zkontrolujte její stav s odesílatelem.';
$messages['oauthloginfailed'] = 'Přihlášení přes OAuth selhalo. Prosím, zkuste to znovu';
