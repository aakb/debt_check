debt_check
==========

Checking debts paid in DIBS with the library system

###################################################

Check af regninger
Når en regning betales i DIBS-vinduet trækkes pengene og der foretages et callback fra dibs til ding-serveren
som derefter foretager et kald via alma til biblioteksserveren.
Erfaringen viser at der kan gå ting galt i denne proces således at der er trukket et beløb, 
men regningen er ikke registreret i bibliotekssystemet. 

Fejlen er beskrevet i lighthouse, https://libraryding.lighthouseapp.com/projects/27862/tickets/1742

Regningen er dog registreret på ding-serveren, så ved at sammenligne tallene dér med tal fra biblioteksserveren
kan disse fejl findes og det er hvad dette script gør.

Kaldes via drush direkte på serveren:

drush php-script check_payments.php {parametre}

parametre er følgende:
--email={liste af emails afskilt af komma}
Optional, sender resultatet til disse emails

--always
Optional, hvis sat betyder det at der altid sendes en mail uanset om der er fejl eller ej. Emnefelt/indhold vil afhænge af om der er fejl eller ej.

--days={dage}
Default=2, checker regninger dette antal dage

--print
Optional, udskriver resultatet til consol - kan bruges når script køres manuelt

Eksempler:
Manuelt fra console:
{sti-til-drush}/drush -r {sti-til-produktionsite} php-script {sti-til-script}/check_payments.php --days=2 --print

Som et cronjob
0 7 * * * {sti-til-drush}/drush -r {sti-til-produktionsite} php-script {sti-til-script}/check_payments.php --email=myemail@ting.dk --days=2 --always > /dev/null 2>&1

Mailen der sendes vil indeholde de fundne fejl på denne form:

{dato}
orderid: {orderid}
ddelibra: {regningsnumre}
dibs: {beløb trukket / registreret i dibs}
alma: {beløb registreret i bibliotekssystemet}
parturl: {del af url som kan bruges når regningen skal godkendes}

Orderid kan benyttes til at søge regningen frem i DIBS-admin systemet og 
regningsnumre kan benyttes til at finde regningen i regningsmodulet i ddelibraGUI / Bibliotekssystemet.


Følgende typer af fejl kan forekomme:

Alma er 0 (nul) og betalingen er ikke foretaget i bibliotekssystemet. 
Den normale situation der opstår når regningerne er fejlet. 
Løsning er at den gennemføres manuelt i ddelibragui eller vha parturl.

Alma er ikke nul men forskellig fra værdien af dibs. 
Regningen er gennemført i biblioteksssystemet. 
Ser ud til at være en fejl i alma når regningen (noget af den) tidligere er betalt kontant. 
Løsning: intet skal gøres.

Alma er 0 (nul) men regningen er betalt i bibliotekssystemet. 
Låneren har altså efterfølgende betalt regningen igen. Det kan enten være på samme website eller 
(hvis biblioteket tilbyder det) på ddelibraWeb. 
I ddelibraGui vil orderid (af den korrekt gennemførte regning) fremgå. 
Løsning her er at tilbageføre beløbet for regningen med fejl.

Køres scriptet efter regningen er håndteret vil regninger af type 1) forsvinde, 
hvorimod 2 og 3 stadig kan findes frem.

Prefixets parturl med Alma baseurl kan regningen gennemføres i bibliotekssystemet, 
men om det er muligt i praksis vil afhænge af netværk/firewalls. 
Det ville dog kunne gøres fra dingserveren da der herfra netop er hul igennem til alma.