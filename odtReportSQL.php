<?php

include(dirname(__FILE__) . "/odtphpsql.php");
require_once dirname(__FILE__) . "/language.php";

/**
 * This scalable system defines a complete reports/documents system
 * for Wamp applications.
 * Features:
 *    Based on templates created using OpenOffice (.odt files)
 *    Templates can be of any size (A4, A3...) and multipage.
 *    On templates this system can do:
 *      Simple substitution based on couples #field#/value
 *      Blocks and nested blocks duplication (any deep)
 *      Pictures substitution
 *    The HTML User Inteface is build by System and can be easy added at 
 *      existing php applications. Add 2 lines ( see odtReportSQL-test.php)
 *            <?php  include('odtReportSQL.php'); ?>
 *            <?php  echo getReportMenu('this_page'); ?>
 *    This system is DB driven, using 2 tables to define all templates 
 *       substitutions and UI
 *    Scalable:
 *        odtphp.php defines template substitution engine
 *        odtphpsql.php adds substitution queries definitions in DB
 *        odtReportSQL.pho adds an UI defined in DB
 *    Any document as an URL definition.
 *    To add a new document is only required to update DB.
 *    The resulting documents can be open using OpenOffice and saved in almost
 *        any format.
 *
 * This system was developped to be used with a school examinations management 
 * software, with more than 25 different documentes (letters, certificates, ufficial records,
 *  tables of the scores, notices...) 
 * =============================================================================
 * odtReportSQL:
 *
 * This lib adds an UI database driven to complete the DB Report System.
 * It uses a new DB table: odt_reports to define where and when a document
 * is available and all details of the user Interface.
 * The communication is based on 5 parameters (key1..key5) defined programmatically 
 * or via UI, passed to doReport(), and here used as-is for Field substitutions
 * in template or for macro-sobstitutions in queries (queries defined in odt_queries 
 * table, see odtphpsql docs).
 *
 * This library defines only 3 functions:
 *
 * doTestTemplate()  uses $_POST['ID']
 *   Returns a HTML fragment ready for page include, showing the fields and Blocks names of the template
 *   Usually not called directly, but using doReport() with action = debug 
 *
 * doReport()
 *   builds a report. Usually not called directly, but using the [EXECUTE] button in UI.
 *   Also an URL calling this file and using following GET/POST params can be used to do a Report: 
 *   (that is done by button [EXECUTE] in UI builded by getReportMenu()):	   
 *                       action = build|debug
 *                       ID   = report ID ( = primary key in odt_reports)  
 *                       key1 = none/value/(none + key1format; key1Day; key1Month; key1Year) for date/((v1,v2,v3...) + key1type=foreach) for foreach
 *                       key2 = none/value/(none + key2format; key2Day; key2Month; key2Year) for date/((v1,v2,v3...) + key2type=foreach) for foreach
 *                       key3 = none/value/(none + key3format; key3Day; key3Month; key3Year) for date
 *                       key4 = none/value/(none + key4format; key4Day; key4Month; key4Year) for date
 *                       key5 = none/value/(none + key5format; key5Day; key5Month; key5Year) for date
 *
 * getReportMenu(page, $key1, $key2, $key3, $key4, $key5)
 *   Returns a HTML fragment ready for include, showing a UI for all reports having $show = true, like this:
 *
 *		|----------------------------------------|  This uses a HTML template (\templates\reportSQL.html) and 
 *    | SHORT NAME							               |	macro sostitutions 
 *		|  description, description, description,|
 *		|  description, description...           |
 *    |----------------------------------------|
 *    | name    o radio   o radio              |  << max 5 of drop/down list or radio or...
 *    |	name   [dropdown list][V]   [EXECUTE]  |     for key1...key5
 *    |										                     |
 *    |----------------------------------------|
 * The optional $key1..$key5 parameters allows the direct assigment to keyX, without UI.
 * 
 * Tested using OpenOffice 4.1.3 and LibreOffice 3.3.2
 * -----------------------------		  
 *  dependecies:
 *        odtphp.php
 *        odtphpsql.php
 *        commonSQL.php
 *        config.php
 *        language.php
 *        \lib\pclzip.lib.php 	
 *        \templates\reportSQL.html
 *        update.gif
 *
 *  license LGPL 
 *  ver 1-04 10/10/2016 added language internalization
 *  author Marco Sillano  (marco.sillano@gmail.com)
 */
/**																									 
 *
 * DATABASE TABLE:
 * odt_reports:   (one record for document)
 *
 *  note: Many fields requires/allows php fragment: so we can have values updated at runtime.
 *        Use some like this: return  php_expression; ( don't forget final ';' )
 *        In the php fragment can be used some useful variables: 
 *        $reportName, $thisReport[], $baseDir and $key1,$key2,... (if caller defined)
 *        or php functions, like:  return 'My report ('.date().')';
 *  
 *  note: Many fields requires/allows SQL queries, starting by 'SELECT' (no case sensitive), to make lists or lookup tables.
 *        In SQL queries are done some macro-sobstitutions: #key1# is replaced by $key1 value, and so on.
 *  
 * ======  odt_reports FIELDS:
 *    ID         INT      : primary key of table
 *    page       TEXT   60: destination page name,  to group Reports that will be show in a page (note: this page name is used 
 *                             only on calling getReportMenu(). If required it is possible to maps it to URL, see docs_list-test.php) 
 *    position   INT      : the position (inside a page and global). Use 110, 120 on first page (allows new insertions later) and 
 *                             values growings for next pages (310..., 510...). This numbers are used
 *                             to sort UI inside a page and to sort documents on global index page (see docs_list-test.php)) 
 *    templateID TEXT   60: the template file name to be used. Templates in: /templates/[templateID].odt
 *    show       TEXT  500: a php fragment (eval) returning TRUE|false to conditional include the report. 
 *                           example: "return true;" (default true). 
 *    outmode:   send : download
 *               save : save in remote 
 *               send_save : both   
 *   outfilepath: a php fragment (eval) for the report file path and name. 	(e.g. return $baseDir.'/reports/test01_done.odt';)
 *                           default: <templateName>-processed.odt
 *   shortName  TEXT  250: The report short name, as php fragment (eval) to get the the rigth value for $shortName var.
 *							             example: return 'My report ('+date()+')'; (can include HTML tags)
 *   description TEXT 250: User instructions,  as php fragment (eval) for $description var. (can include HTML tags)
 * -----------------------------
 *  key1, key2, key3, key4, key5 are parameters (fixed names) coming from application to doReport via  getReportMenu(),
 *  or defined in odt_report table for user choice (list/radio etc.) in the document UI.   
 *
 *	 key1type    TEXT  10: one of:
 *                             hidden : not in UI (defined programmatically calling getReportMenu() or in ke1value)
 *                             date   : in UI a form to insert a date, default today
 *                             list   : in UI a dropdown list, single choice
 *                             radio  : in UI radio buttons 
 *                             HTML   : in UI an input TAG as defined from HTML code in key1value, name='key1'
 *                             foreach: not in UI, to genarate many copies of same document, one for any value of key1 (or key2)
 *
 *  key1name    TEXT  60: the parameter description used in UI  (it can contain HTML tags)
 * 
 *  key1value   TEXT 500: if key1 == hidden -> needs a value:
 *                                  a SQL query like "SELECT name FROM users WHERE id = 3056";
 *                                    or, if it not starts by SELECT, case insensitive:
 *                                  a php fragment (eval) returning a value
 *                                  or ""/NULL (as placeholder for auto: must exists a caller value)
 *                                  assigns to key1 the key1value/caller value
 *                        if key1 == date   -> a formatting string (default dd/mm/YYYY) like: %d %B %Y
 *                                 note: format is done using strftime(), see later and http://php.net/manual/en/function.strftime.php.
 *                                 note: this don't assigns key1, but sends separated year, month, day and format: doReport() will format it. 
 *                        if key1 == HTML   -> a php fragment (eval) returning the required HTML code to have some
 *                                 correct value in  keyX (e.g. for a text field:  return "<input type='text' name='key1'>";  )
 *                                 assigns to key1 the user inserted value                                       
 *                        if key1 == list|radio -> needs a lookup array value/message:
 *                                  a SQL query like "SELECT * FROM sexlookup", to make the lookup array using [0] and [1]	fields;
 *                                  or, @<nomefile.csv> a file containing a message list (.csv, using ';;' as separator)
 *                                     in this case values = 0,1,...n;  
 *                    	            or, if it not starts by @|SELECT, case insensitive:
 *                                     a php fragment (eval) returning an lookup array like:
 *                                                  "return array('1' => 'one', '2' => 'two');" 	   
 *                                               or "return array('primo', 'secondo', terzo');" :in this case values = 0,1,2;   
 *                                  assigns to key1 the value chosed by user
 *                        if key1 == foreach (only key1 and key2) -> needs an array of values:
 *                                  a SQL query like "SELECT id FROM users", to make an array using [0] field;
 *                                  or, @<nomefile.csv> a file containing a value list (csv, using ';;' as separator)
 *                                  assigns to key1 a list of values: any value produces a new document.
 *                                  note: outfilepath must be parametrized on key1-foreach, or output documents will overwrite.
 *                                        All output documents  are saved (never sended).
 *                                  note: hidden in UI
 *                               
 *	 key2type    TEXT  10: see key1
 *	 key2name    TEXT  60: see key1
 *   key2value   TEXT 500: see key1
 *	 key3type    TEXT  10: see key1 (not foreach)
 *	 key3name    TEXT  60: see key1
 *   key3value   TEXT 500: see key1
 *	 key4type    TEXT  10: see key1 (not foreach)
 *	 key4name    TEXT  60: see key1
 *   key4value   TEXT 500: see key1
 *	 key5type    TEXT  10: see key1 (not foreach)
 *	 key5name    TEXT  60: see key1
 *   key5value   TEXT 500: see key1		 
 *   
 *    note: key1...key5 can also be set on calling getReportMenu():	this will generate
 *          an hidden field, and the definition in odt_reports table is discarted.
 *          As placeholder, use 'hidden' as type and a name + '(auto)' and NULL as value on odt_reports table.
 *    note: after building HTML, this data are not usefull: can be discarded
 *    note: use '/' in path also in win	 														 
 *
 */

/*
=========== strftime format date codes
Day --- ---
%a	An abbreviated textual representation of the day	Sun through Sat
%A	A full textual representation of the day	Sunday through Saturday
%d	Two-digit day of the month (with leading zeros)	01 to 31
%e	Day of the month, with a space preceding single digits. Not implemented as described on Windows. See below for more information.	1 to 31
%j	Day of the year, 3 digits with leading zeros	001 to 366
%u	ISO-8601 numeric representation of the day of the week	1 (for Monday) through 7 (for Sunday)
%w	Numeric representation of the day of the week	0 (for Sunday) through 6 (for Saturday)
Week	---	---
%U	Week number of the given year, starting with the first Sunday as the first week	13 (for the 13th full week of the year)
%V	ISO-8601:1988 week number of the given year, starting with the first week of the year with at least 4 weekdays, with Monday being the start of the week	01 through 53 (where 53 accounts for an overlapping week)
%W	A numeric representation of the week of the year, starting with the first Monday as the first week	46 (for the 46th week of the year beginning with a Monday)
Month	---	---
%b	Abbreviated month name, based on the locale	Jan through Dec
%B	Full month name, based on the locale	January through December
%h	Abbreviated month name, based on the locale (an alias of %b)	Jan through Dec
%m	Two digit representation of the month	01 (for January) through 12 (for December)
Year	---	---
%C	Two digit representation of the century (year divided by 100, truncated to an integer)	19 for the 20th Century
%g	Two digit representation of the year going by ISO-8601:1988 standards (see %V)	Example: 09 for the week of January 6, 2009
%G	The full four-digit version of %g	Example: 2008 for the week of January 3, 2009
%y	Two digit representation of the year	Example: 09 for 2009, 79 for 1979
%Y	Four digit representation for the year	Example: 2038
Time	---	---
%H	Two digit representation of the hour in 24-hour format	00 through 23
%k	Hour in 24-hour format, with a space preceding single digits	0 through 23
%I	Two digit representation of the hour in 12-hour format	01 through 12
%l (lower-case 'L')	Hour in 12-hour format, with a space preceding single digits	1 through 12
%M	Two digit representation of the minute	00 through 59
%p	UPPER-CASE 'AM' or 'PM' based on the given time	Example: AM for 00:31, PM for 22:23
%P	lower-case 'am' or 'pm' based on the given time	Example: am for 00:31, pm for 22:23
%r	Same as "%I:%M:%S %p"	Example: 09:34:17 PM for 21:34:17
%R	Same as "%H:%M"	Example: 00:35 for 12:35 AM, 16:44 for 4:44 PM
%S	Two digit representation of the second	00 through 59
%T	Same as "%H:%M:%S"	Example: 21:34:17 for 09:34:17 PM
%X	Preferred time representation based on locale, without the date	Example: 03:59:16 or 15:59:16
%z	The time zone offset. Not implemented as described on Windows. See below for more information.	Example: -0500 for US Eastern Time
%Z	The time zone abbreviation. Not implemented as described on Windows. See below for more information.	Example: EST for Eastern Time
Time and Date Stamps	---	---
%c	Preferred date and time stamp based on locale	Example: Tue Feb 5 00:45:10 2009 for February 5, 2009 at 12:45:10 AM
%D	Same as "%m/%d/%y"	Example: 02/05/09 for February 5, 2009
%F	Same as "%Y-%m-%d" (commonly used in database datestamps)	Example: 2009-02-05 for February 5, 2009
%s	Unix Epoch Time timestamp (same as the time() function)	Example: 305815200 for September 10, 1979 08:40:00 AM
%x	Preferred date representation based on locale, without the time	Example: 02/05/09 for February 5, 2009
Miscellaneous	---	---
%n	A newline character ("\n")	---
%t	A Tab character ("\t")	---
%%	A literal percentage character ("%")	---
*/

$debug = false; // if true getReportMenu() uses action=debug else action=build
// 
// better to use POST
if (array_key_exists('ID', $_GET)) {
    $_POST = $_GET;
}


// ===============================  debug report
if (array_key_exists('action', $_POST) && ($_POST['action'] == 'debug')) {
    print('<HTML> <HEAD></HEAD><BODY>');
    print('<h3>debug mode </h3>');
    print('<pre>');
    print_r($GLOBALS);
    print_r(array_keys(get_defined_vars()));
    print('</pre><hr>');
    print(doTestTemplate());
    print('<hr><br></BODY></HTML>');
    exit;
}
// ===============================  build report  
 if (array_key_exists('action', $_POST) && ($_POST['action'] == 'build')) {
    doReport();
 }

/**
 * Returns names of fields and blocks
 * The report name must be difined as parameter in call or document ID in $_POST/$_GET[ID]
 */

function doTestTemplate($reportName = NULL)
{
    if (($reportName == NULL) && array_key_exists('ID', $_POST)) {
        $reportName = sqlValue('SELECT templateID FROM odt_reports WHERE ID =' . $_POST['ID'] . ';');
    }
    if ($reportName == NULL)
        die('ERROR in odtReportSQL.doReport():  $reportName or $_POST/$_GET[ID] not defined!');
    $baseDir  = dirname(__FILE__);
    $template = dirname(__FILE__) . "/templates/$reportName.odt";
    ob_start();
    $odtsql = new Odtphpsql($template);
    print('<br><b>Template file: <i>' . $template . '</i>:</b><br>');
    print('<div> FIELDS: </div><pre>');
    print_r($odtsql->getFieldNames());
    print('</pre><div> BLOCKS: </div><pre>');
    print_r($odtsql->getBlockNames());
    print('</pre><div> NESTED BLOCKS: </div><pre>');
    print_r($odtsql->getNestedBlockNames());
    print('</pre>');
    $result = ob_get_contents();
    ob_end_clean();
    return $result;
}

/*
 * Generates HTML UI for options (text, list, radio or hidden) and buttons.
 *  Templates are select using 'page' value and the 'show' condition.
 *  Templates are ordered using 
 *  key1...key5 if not NULL are values defined progrmmatically, not by user.
 *  These values must corrispond to an "hidden" type in odt_reports definitions.
 */

function getReportMenu($page, $key1 = NULL, $key2 = NULL, $key3 = NULL, $key4 = NULL, $key5 = NULL)
{ 
    $HTMLtemplate   = './templates/reportSQL.html';
    $query0         = "SELECT * FROM odt_reports WHERE page = '$page' ORDER BY position";
    $HTMLfragment   = '';
    $allPageReports = sqlArrayTot($query0);
    $html           = file_get_contents(dirname(__FILE__) . $HTMLtemplate);
    foreach ($allPageReports as $aReport) {
        if (($aReport['show'] == '') || (eval($aReport['show']))) {
            $HTMLfragment .= generateHTML($html, $aReport, $key1, $key2, $key3, $key4, $key5);
        }
    }
    return $HTMLfragment;
}

/**
 *  this function produces documents using:
 *    1) $_POST/$_GET fields like action, ID, key1, key1.format, key1.Month, key1.Day, key1.Year, etc...
 *    2) data from DB  odt_reports
 *    uses odtphpsql.
 */
function doReport()
{
    global $Translation;
    /*	    
    print ('<pre>');
    print_r ($_POST);	
    print ('</pre>');
    */
    if (!$_POST['ID']) {
        die( $Translation['missed ID'] );
    }
    // get row from odt_reports    
    $query0     = 'SELECT * FROM odt_reports WHERE ID=' . $_POST['ID'] . ';';
    $thisReport = sqlRecord($query0);
    $reportName = $thisReport['templateID']; 
    // =================== some useful var to be used in keyXvalue with 'eval'
    //  $reportName:  template file name
    //  $thisReport[]]:  array wit report data (= record in odt_report)        
    //  $baseDir   :  remote application base dir     
    $baseDir = dirname(__FILE__);
    //      
    // ===========   sets key1...kek5
    // format date			
    set_local();
    for ($i = 1; $i < 6; $i++) {
        // any date type keyX? if yes builds keyX value using keyX.format 
        if (array_key_exists('key' . $i . 'format', $_POST)) {
            $xdate             = mktime(0, 0, 0, $_POST['key' . $i . 'Month'], $_POST['key' . $i . 'Day'], $_POST['key' . $i . 'Year']);
            $_POST['key' . $i] = strftime($_POST['key' . $i . 'format'], $xdate);
        }
    }
    
    $template = $baseDir . "/templates/" . $reportName . ".odt";
    
    $key1Values = array();
    $key2Values = array();
    //	
    if (array_key_exists('key1', $_POST)) {
        if ((array_key_exists('key1type', $_POST)) && ($_POST['key1type'] == 'foreach')) {
            if ($_POST['key1'] == '') {
                echo StyleSheet();
                echo "\n\n<div >" . $Translation['foreach key1 without values'] . '</div>';
                exit;
            }
            $key1Values = explode(',', $_POST['key1']);
        } else
            $key1Values[] = ($_POST['key1']);
    } // key1
    // else  $key1Values[] =  0;
    // 		 
    if (array_key_exists('key2', $_POST)) {
        if ((array_key_exists('key2type', $_POST)) && ($_POST['key2type'] == 'foreach')) {
            if ($_POST['key2'] == '') {
                echo StyleSheet();
                echo "\n\n<div >" . $Translation['foreach key2 without values'] . '</div>';
                exit;
            }
            $key2Values = explode(',', $_POST['key2']);
        } else
            $key2Values[] = ($_POST['key2']);
        
    } // key2   
    // else  $key2Values[] =  0; 
    // multiples files: only save
    if ((count($key1Values) > 1) || (count($key2Values) > 1))
        $thisReport['outmode'] = 'save';
    // double execution  loop
    $loop1 = true;
    for ($i = 0; ($i < count($key1Values)) || $loop1; $i++) {
        $loop1 = false;
        if (count($key1Values) > 0)
            $key1 = $key1Values[$i];
        $loop2 = true;
        for ($k = 0; ($k < count($key2Values)) || $loop2; $k++) {
            $loop2 = false;
            if (count($key2Values) > 0)
                $key2 = $key2Values[$k];
            
            if (array_key_exists('key3', $_POST))
                $key3 = ($_POST['key3']);
            if (array_key_exists('key4', $_POST))
                $key4 = ($_POST['key4']);
            if (array_key_exists('key5', $_POST))
                $key5 = ($_POST['key5']);
            
            // $thisReport['outfilepath'] can contains  $baseDir... but also $key1..$key5     
            if ($thisReport['outfilepath'] == '') {
                $outputFile = $baseDir . "/reports/" . $reportName . "-processed.odt";
            } else
                $outputFile = eval($thisReport['outfilepath']);
            $outputName = basename($outputFile, ".odt");
            // builds document       
            $odtsql     = new Odtphpsql($template);
            // sets keyX values	 
            if (isset($key1))
                $odtsql->assign("key1", $key1); // basic field mapping	   
            if (isset($key2))
                $odtsql->assign("key2", $key2); // basic field mapping	   
            if (isset($key3))
                $odtsql->assign("key3", $key3); // basic field mapping	   
            if (isset($key4))
                $odtsql->assign("key4", $key4); // basic field mapping	   
            if (isset($key5))
                $odtsql->assign("key5", $key5); // basic field mapping	 
            // so can be replaced in queries:		  
            $odt_queriesArray = $odtsql->getArrayQueries($reportName); //  gets the descriptors for this template
            $odtsql->assignAllSQL($odt_queriesArray); //  sql fields and blocks definitions via $odt_queriesArray	 
            // output
            if ($thisReport['outmode'] != 'send')
                $odtsql->saveODT($outputFile); //  optional save 
            if ($thisReport['outmode'] != 'save')
                $odtsql->downloadODT($outputName); //  send to client  
        } // for key2
    } // for key 1
    
    if ($thisReport['outmode'] != 'send') {
        echo "\n\n<div >" . sprintf($Translation['saved files warning'], basename($outputFile), dirname($outputFile)) . '</div>';
        exit;
    }
}

function generateHTML($model, $rep, $key1, $key2, $key3, $key4, $key5)
{
    global $Translation;
    global $odtdata;
    global $debug;
    if ($shortName = eval($rep['shortName'])) {
        $model = str_replace('<!-- nome -->', $shortName, $model);
    }
    
    if ($description = eval($rep['description'])) {
        $model = str_replace('<!-- description -->', $description, $model);
    }
    
    $model = str_replace('<!-- target -->', 'odtReportSQL.php', $model);
    if ($debug == true)
        $model = str_replace('<!-- fields -->', '<input type="hidden" name="action" value="debug"><!-- fields -->', $model);
    else
        $model = str_replace('<!-- fields -->', '<input type="hidden" name="action" value="build"><!-- fields -->', $model);
    $model = str_replace('<!-- fields -->', '<input type="hidden" name="ID" value="' . $rep['ID'] . '"><!-- fields -->', $model);
    preg_match('/--fieldBlock(.*)fieldBlock--/s', $model, $matches);
    if (count($matches) > 1) {
        //	so modelFields() replaces <!-- fields --> in $model or in $fieldBlock		
        //  $fieldBlock mandatory for Key NOT hidden
        $fieldBlock = $matches[1];
        $fieldBlock = str_replace('#fieldInput#', '<!-- fields -->', $fieldBlock);
    }
    // building
    $fragm1 = "";
    $fragm2 = "";
    $fragm3 = "";
    $fragm4 = "";
    $fragm5 = "";
//    
    $rows = 1;
// macro substutions and  pre-processing    
    $query = $rep['key1value'];
    // hidden cases: 
    if ($rep['key1type'] == 'foreach') {       
        $model  = modelFields($model, 'key1', $rep['key1type'], $query);
    } else if ($key1) {
        // forces hidden 
        $model = modelFields($model, 'key1', 'hidden', "return '$key1';");
    } else if (($rep['key1type'] == 'hidden') || (count($matches) < 1)) {
        $model = modelFields($model, 'key1', 'hidden', $query);
        // not hidden        
    } else if ($rep['key1type'] != '') {
        $fragm1 = modelFields($fieldBlock, 'key1', $rep['key1type'], $query);
        // uses also field-name if not hidden: 
        $fragm1 = str_replace('#fieldName#', $rep['key1name'], $fragm1);
        $rows++;
    }
// macro subst    
    $query = str_replace('#key1#', $key1, $rep['key2value']);
//
    if ($rep['key2type'] == 'foreach') {
        $model  = modelFields($model, 'key2', $rep['key2type'], $query);
    } else if ($key2) {
        $model = modelFields($model, 'key2', 'hidden', "return '$key2';");
    } else if (($rep['key2type'] == 'hidden') || (count($matches) < 1)) {
        $model = modelFields($model, 'key2', 'hidden', $query);
    } else if ($rep['key2type'] != '') {
        $fragm2 = modelFields($fieldBlock, 'key2', $rep['key2type'], $query);
        $fragm2 = str_replace('#fieldName#', $rep['key2name'], $fragm2);
        $rows++;
    }
 //   
    $query = str_replace('#key1#', $key1, $rep['key3value']);
    if ($key2)
        $query = str_replace('#key2#', $key2, $query);
 //  not foreach
    if ($key3) {
        $model = modelFields($model, 'key3', 'hidden', "return '$key3';");
    } else if (($rep['key3type'] == 'hidden') || (count($matches) < 1)) {
        $model = modelFields($model, 'key3', 'hidden', $query);
    } else if ($rep['key3type'] != '') {
        $fragm3 = modelFields($fieldBlock, 'key3', $rep['key3type'], $query);
        $fragm3 = str_replace('#fieldName#', $rep['key3name'], $fragm3);
        $rows++;
    }
    
    $query = str_replace('#key1#', $key1, $rep['key4value']);
    if ($key2)
        $query = str_replace('#key2#', $key2, $query);
    if ($key3)
        $query = str_replace('#key3#', $key3, $query);
//        
    if ($key4) {
        $model = modelFields($model, 'key4', 'hidden', "return '$key4';");
    } else
    //	if ($rep['key4type'])   
        if (($rep['key4type'] == 'hidden') || (count($matches) < 1)) {
        $model = modelFields($model, 'key4', 'hidden', $query);
    } else if ($rep['key4type'] != '') {
        $fragm4 = modelFields($fieldBlock, 'key4', $rep['key4type'], $query);
        $fragm4 = str_replace('#fieldName#', $rep['key4name'], $fragm4);
        $rows++;
    }
    
    $query = str_replace('#key1#', $key1, $rep['key5value']);
    if ($key2)
        $query = str_replace('#key2#', $key2, $query);
    if ($key3)
        $query = str_replace('#key3#', $key3, $query);
    if ($key4)
        $query = str_replace('#key4#', $key4, $query);
    if ($key5) {
        $model = modelFields($model, 'key5', 'hidden', "return '$key5';");
    } else
    //	     if ($rep['key5type'])   
        if (($rep['key5type'] == 'hidden') || (count($matches) < 1)) {
        $model = modelFields($model, 'key5', 'hidden', $query);
    } else if ($rep['key5type'] != '') {
        $fragm5 = modelFields($fieldBlock, 'key5', $rep['key5type'], $query);
        $fragm5 = str_replace('#fieldName#', $rep['key5name'], $fragm5);
        $rows++;
    }
    // dummy row 				   
    if ($rows == 1) {
        $fieldBlock = str_replace('<!-- fields -->', $Translation['click to go'], $fieldBlock);
        $fragm1     = str_replace('#fieldName#', '&nbsp;', $fieldBlock);
        $rows       = 2;
    }
 //  finel make   
    $model = str_replace('<!-- fields -->', $fragm1 . $fragm2 . $fragm3 . $fragm4 . $fragm5 . '<!-- fields -->', $model);
    $model = str_replace('<!-- rows -->', $rows, $model);
    return $model;
}


function putError($model, $value, $badKey)
{
    return str_replace('<!-- fields -->', sprintf($Translation['key definition error'], $badKey, $value) . '<!-- fields -->', $model);
}


function modelFields($model, $key, $type, $value)
{
    global $month;
    global $year;
    
    if (!strcasecmp($type, 'hidden')) {
        //  hidden.sql
        if (substr_compare(trim($value), 'select', 0, 6, true) === 0) {
            $value = sqlValue($value);
        } else
        //   hidden.eval
            $value = eval($value);
        //           
        if ($value) {
            $model = str_replace('<!-- fields -->', '<input type="hidden" name="' . $key . '" value="' . $value . "\">\n<!-- fields -->", $model);
        } else {
            return putError($model, $value, $key);
        }
    } // end hidden	
    
    if (!strcasecmp($type, 'foreach')) {
         $values = array();
        // foreach.query
         if (substr_compare(trim($value), 'select', 0, 6, true) === 0) {
            $values =  sqlArray($value);
        } else
        // foreach.@<path to file>	  	
            if (file_exists(dirname(__FILE__) . '\\' . substr($value, 1))) {
            $opzioni_data = addslashes(implode('', @file(dirname(__FILE__) . '/' . substr($value, 1))));
            $values        = explode(';;', $opzioni_data);
        // foreach.eval
        } else if (!($values = eval($value))) {
            return putError($model, $value, $key);
        }
        //  foreach.update      
        $model = str_replace('<!-- fields -->', '<input type="hidden" name="' . $key . '" value="' . implode(",", $values) . "\">\n<!-- fields -->", $model);
        $model = str_replace('<!-- fields -->', '<input type="hidden" name="' . $key . 'type" value="foreach">' . "\n<!-- fields -->", $model);
    }
    
    if (!strcasecmp($type, 'HTML')) {
        //	HTML.eval
        if ($value = eval($value)) {
            $model = str_replace('<!-- fields -->', $value . "\n<!-- fields -->", $model);
        } else {
            return putError($model, $value, $key);
        }
    }
    
    if (!strcasecmp($type, 'date')) {
        // 	date		
        $date = '<input type="hidden" name="' . $key . 'format" value="' . $value . '">';
        $date .= '<select name="' . $key . 'Day" id="' . $key . '-dd" class="Option" style="">
				<option value="">&nbsp;</option>';
        for ($i = 1; $i < 32; $i++) {
            $date .= '<option value="' . $i . '" class="Option" ' . ($i == date('j') ? 'selected' : '') . '>' . $i . "</option>\n";
        }
        $date .= '</select> / <select name="' . $key . 'Month" id="' . $key . '-mm" class="Option" style="">
				<option value="">&nbsp;</option>';
        for ($i = 1; $i < 13; $i++) {
            $date .= '<option value="' . $i . '" class="Option" ' . ($i == date('n') ? 'selected' : '') . '>' . $month[$i] . "</option>\n";
        }
        
        $date .= '</select> / <select name="' . $key . 'Year" id="' . $key . '-yy" class="Option" style="">
				<option value="">&nbsp;</option>';
        
        foreach ($year as $op) {
            $date .= '<option value="' . $op . '" class="Option"' . ($op == date('Y') ? 'selected' : '') . '>' . $op . "</option>\n";
        }
        $date .= '</select>';
        $model = str_replace('<!-- fields -->', $date . "\n<!-- fields -->", $model);
    }
    
    if (!strcasecmp($type, 'list')) {
        
        // <select name="key3">
        //  	<option value="primo"> primo</option>
        //   	<option value="secondo"> secondo</option>
        //  </select>
        // updates $value array	 
        //	    print ('<br> list is: '.filter($value).'<br>');
        
        if (substr_compare(trim($value), 'select', 0, 6, true) === 0) {
        // list.query
            $value = sqlLookup($value);
        } else
        //	list.@<path to file>	  	
            if (file_exists(dirname(__FILE__) . '\\' . substr($value, 1))) {
            $opzioni_data = addslashes(implode('', @file(dirname(__FILE__) . '/' . substr($value, 1))));
            $value        = explode(';;', $opzioni_data);
         // list.eval
        } else if (!($value = eval($value))) {
            return putError($model, $value, $key);
        }
        // $value now is a lookup array		
        $new = '<select name="' . $key . "\">\n";
        foreach ($value as $op => $val) {
            $new .= '<option value="' . $op . '">' . $val . "</option>\n";
        }
        $new .= "</select><BR>\n<!-- fields -->";
        $model = str_replace('<!-- fields -->', $new, $model);
        
    } // end if list
    
    // radio
    if (!strcasecmp($type, 'radio')) {
         // radio.query
         if (substr_compare(trim($value), 'select', 0, 6, true) === 0) {
            $value = getLookupSQL($value);
        } else
        //	radio.@<path to file>	  	
            if (file_exists(dirname(__FILE__) . '\\' . substr($value, 1))) {
            $opzioni_data = addslashes(implode('', @file(dirname(__FILE__) . '/' . substr($value, 1))));
            $value        = explode(';;', $opzioni_data);
        // radio.eval
        } else if (!($value = eval($value))) {
            return putError($model, $value, $key);
        }
        
        // $value is a lookup array	
        $ceckLine = 4;
        $new      = '';
        $i        = 0;
        foreach ($value as $op => $val) {
            if ($i++ == 0) {
                $new .= '<input type="radio" name="' . $key . '" value="' . $op . '"  checked>&nbsp;' . $val . ($i % $ceckLine == 0 ? "<br>\n" : "&nbsp;&nbsp;&nbsp;\n");
            } else {
                $new .= '<input type="radio" name="' . $key . '" value="' . $op . '">&nbsp;' . $val . ($i % $ceckLine == 0 ? "<br>\n" : "&nbsp;&nbsp;&nbsp;\n");
            }
        }
        $model = str_replace('<!-- fields -->', $new . '<!-- fields -->', $model);
    } // end if radio	
    
    return $model;
}

// ========================================================================================================
// low level functions		   

function repaceTxtMacros($text, $values)
{
    foreach ($values as $id => $val) {
        $text = str_ireplace('#' . $id . '#', $val, $text);
    }
    return $text;
}

