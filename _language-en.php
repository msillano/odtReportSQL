<?php
//  ---- odtphp
   $Translation['template not found'] = 'ERROR: the file template %s not found!';
   $Translation['picture not found'] = 'ERROR: not found pictures in Template!';
   $Translation['file picture not found'] = 'ERROR: not found picture file [%s]!';
   $Translation['nested exception'] = 'Parent list for %s cannot be empty';
   $Translation['none result'] = '<BR> ERROR building document !! <br>';
   $Translation['original author'] = 'The autority';  // TODO, update this


//  ----  odtReportSQL	(on long HTML, cut lines at TAG attributes)
  $Translation['missed ID']='ERROR in odtReportSQL.doReport(): required $_POST/$_GET[ID] not defined!'; 
  $Translation['foreach key1 without values'] = '<b>ERROR: can not create any document!<br> Verify key1 values.</b><br><br><hr><center><a href=""
     onclick="history.go(-1); return false;"> &lt;&lt; back </a>&nbsp;</center><hr>';
  $Translation['foreach key2 without values'] = '<b>ERROR: can not create any document!<br> Verify key2 values.</b><br><br><hr><center><a href=""
     onclick="history.go(-1); return false;"> &lt;&lt; back </a>&nbsp;</center><hr>';
  $Translation['saved files warning']='The files (like  <i>%s</i>) are saved on sever<br> in document dir: <b>%s</b><br><br><hr><center><a
     href="" onclick="history.go(-1); return false;"> &lt;&lt; back </a>&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;<a 
     href="./reports/" > to default document dir &gt;&gt;</a></center><hr>';
  $Translation['key definition error']="<BR>ERROR in key (%s = '%s') value!<BR>\n";
  $Translation['click to go']='Click button to do document  &gt;&gt;';
 // ------------ arrays and functions
 //TODO:  set the local timezone (see http://php.net/manual/en/timezones.php)
 function set_local(){
        date_default_timezone_set ('Europe/London');
   }
  // to handle some special chars on XML
   function filter($value){  	 				  
			if ( !((strpos($value,'&amp;') === false)&
			       (strpos($value,'&lt;') === false)&
				     (strpos($value,'&#') === false)) ) return $value;
		  $ret = $value;
			$ret = str_replace("&","&amp;",$ret);
			$ret = str_replace("<","&lt;",$ret);   
 //TODO update this (if required)
 			$ret = str_replace("€","&#8364;",$ret);
			return $ret;  				
		}	  																							

 // ---- date UI
 $month = array('','January','February','Match','April','May','June','July','August','September','October','November','December');
 $year  = array(2010,2011,2012,2013,2014,2015,2016,2017,2018,2019,2020,2021,2022,2023,2024,2025);
// license LGPL    
?>   
   