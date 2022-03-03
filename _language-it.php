<?php
//  license LGPL 
//  ---- odtphp
   $Translation['template not found'] = 'ERRORE: il file Template %s not esiste!';
   $Translation['picture not found'] = 'ERRORE: non ci sono immagini nel Template!';
   $Translation['file picture not found'] = 'ERRORE: non trovo il file immagine [%s]!';
   $Translation['nested exception'] = 'Il blocco %s non ha parenti';
   $Translation['none result'] = '<BR> ERRORE  creando il documento !! <br>';
   $Translation['original author'] = 'Consulting SpA';  // TODO, update this

//  ----- odtReportSQL (in caso di lunghe righe HTML, inserire gli 'a capo' tra gli attributi di un TAG)
  $Translation['missed ID']='ERRORE in odtReportSQL.doReport():  $_POST/$_GET[ID] non definito!';
  $Translation['foreach key1 without values'] = '<b>ERRORE: nessun documento da creare !<br> Controllare i dati (key1) e le condizioni.</b><br><br><hr><center><a 
    href="" onclick="history.go(-1); return false;"> &lt;&lt; indietro </a>&nbsp;</center><hr>';
  $Translation['foreach key2 without values'] = '<b>ERRORE: nessun documento da creare !<br> Controllare i dati (key2) e le condizioni.</b><br><br><hr><center><a
    href="" onclick="history.go(-1); return false;"> &lt;&lt; indietro </a>&nbsp;</center><hr>';
  $Translation['saved files warning']='I  Documenti creati files (come  <i>%s</i>) sono salvati sul server<br> nella cartella ad essi riservata: <b>%s</b><br><br><hr><center><a
    href="" onclick="history.go(-1); return false;"> &lt;&lt; indietro </a>&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;<a
    href="./reports/" > alla cartella base dei documenti &gt;&gt;</a></center><hr>';
  $Translation['key definition error']="<BR>ERRORE nei valori di key(%s ='%s')!<BR>\n";
  $Translation['click to go']='Cliccare per creare il documento &gt;&gt;';
 // ------------ arrays and functions
  // set the local timezone (see http://php.net/manual/en/timezones.php)
 function set_local(){
        date_default_timezone_set ('Europe/Rome' );
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
 // caratteri specifici(italiano). 	
			$ret = str_replace("à","&#224;",$ret);
			$ret = str_replace("è","&#232;",$ret);
			$ret = str_replace("é","&#233;",$ret);
			$ret = str_replace("ò","&#242;",$ret);
			$ret = str_replace("ì","&#236;",$ret);
			$ret = str_replace("ù","&#249;",$ret);
			$ret = str_replace("€","&#8364;",$ret);
			return $ret;  				
		}	  																							

 // ---- date UI
 $month = array('','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre');
 $year = array(2010,2011,2012,2013,2014,2015,2016,2017,2018,2019,2020,2021,2022,2023,2024,2025);
   
?>   
   