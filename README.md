# odtReportSQL
This scalable library defines a complete reports/documents system  for php-mySQL applications.

![demo screenshot](./demo/img/2017-04-19.210908.shot.png)

Features:
-  Based on templates created using OpenOffice (.odt files)
-  Templates can be of any size (A4, A3...) and multipage.
-  On templates this system can do:
*       Simple substitution based on couples #field#/value.
*       Blocks and nested blocks duplication (any deep) or deletion.
*       Pictures substitution.
-  The HTML User Inteface is build by System and can be easy added at an existing php application. Add 2 lines ( see odtReportSQL-test.php)
*           <?php  include('odtReportSQL.php'); ?>
*           <?php  echo getReportMenu('this_page'); ?>
-  This system is DB driven, using 2 tables to define all templates substitutions and UI
-  Scalable:
*      odtphp.php defines template substitution engine
*      odtphpsql.php adds substitution queries definitions in DB
*      odtReportSQL.php adds an UI defined in DB
-  Any document as an URL definition.
-  To add a new document is only required to make the new template and to update the DB.
-  The resulting documents can be open using OpenOffice and saved in almost any format.
 
This system was developped to be used with a school examinations management software, with more than 25 different documents (letters, certificates, ufficial records, grade tables, notices...) from 1 to 68 pages.

![demo template](./demo/img/2017-04-20.075902.shot.png)![demo document](./demo/img/2017-04-20.080141.shot.png)

see install.txt.


TODO
- Templates issue

  Description: The new document is build, but you get an error openning it: it is inusable.
  
  Cause: The Template is sensible to the LibreOffice/OpenOffice version used.
   - The "OpenOffice/4.1.8$Win32 OpenOffice.org_project/418m3$Build-9803"  works without probems
   - The "LibreOffice/7.3.0.3$Windows_x86 LibreOffice_project/0f246aa12d0eee4a0f7adcefbf7c878fc2238db3" don't works.
  
  Workaround: This affect only template setup, not the user documents creation. 
  Use allway same (old) version of OpenOffice to build and update your Templates, e.g. [https://downloads.sourceforge.net/project/openofficeorg.mirror/4.1.8/binaries/it/Apache_OpenOffice_4.1.8_Win_x86_install_it.exe](https://downloads.sourceforge.net/project/openofficeorg.mirror/4.1.8/binaries/it/Apache_OpenOffice_4.1.8_Win_x86_install_it.exe)
  
- More translations (files language_xx.php).
- More DB Interfaces (file commonSQL.php). Done: see common_pdo.php.

UPDATE
 - 2020-02-09  the common_pdo.php file replaces the obsolete commonSQL.php  file

 - 2022-02-02 Used this library in the new project: tuyaDaemon.toolokit (https://github.com/msillano/tuyaDAEMON/tree/main/tuyaDAEMON.toolkit): minor bugs correction and update to php 8.
   - Examples of result pages (pdf) are in the ![wiki](https://github.com/msillano/tuyaDAEMON/blob/main/devices/ACmeter/device_ACmeter.pdf)
   - Tested on:
      - Windows 11, php 8.1.2, MariaDB 10.4.22
      - Android 11, php 4.1,  MySql 5.1
      
 The php 8 use requires also an update of pclzip.lib (here as  "lib\pclzip.2.8.4.lib.php").
 
