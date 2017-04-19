<?php

include(dirname(__FILE__)."/odtphp.php");	
include(dirname(__FILE__)."/commonSQL.php");	
require_once dirname(__FILE__)."/language.php";
/**
* This class defines a simple "ODT document system" for mySQL, using '.odt' templates and
* producing documents and reports including text, tables, images, editables and exportables
* in almost any format (odt, doc, pdf, txt etc..).
* 
* The template file (build using OpenOffice, '.odt') contains Field, Block, Image tags
* that are repaced from the results of SQL queries to a mySQL database, or defined
* programmatically.
* note: replacement interests only the textual content, not format infos.
*
* Templates can be updated at run-time, using only a structure $templateArray
* (usually stored in a database table) as descriptor of all Fields, Blocks, Images and queries:	  
*
* $templateArray = 
*     array(array("reportId"=>'template4', "block"=>'', "query"=>'query0', "parent"=>''),		       // block==NULL: for fields in template (one or more))
*           array("reportId"=>'template4', "block"=>'dir', "query"=>'query1', "parent"=>''),	     // parent== NULL: for root blocks (one per block)
*			      array("reportId"=>'template4', "block"=>'file', "query"=>'query2', "parent"=>'dir'));  // for nested blocks (any deep, one per block)
*	
* note: all field-names must be unique and are not case sensitive. 
* note: block-names must match '\w+' regex and are case sensitive.
* note: reportId is the template file name 
* note: any query must return a table having column names equals to field-names in template or block (#field-name#),
*      the number of found records cantrols the Blocks duplication.
*  
* Replacement in Template (header, body, footer) using '#field#' :
*      field/values can be pre-defined (like today, user, etc..) see constructor Odtphpsql().
*      field/values can be assigned as Strings: see assign($field,$value).
*      field/values can be assigned as Array: see assignArray($fields)
*      field/values can be read at run-time from a DB, using a Query: see assingFieldsSQL($query)
*      field/values	can be defined using queries in one or more records in $templateArray.	
*
* note: if a query for fields (block == NULL)  returns more than one record, the fields are indexed like so:
*       row[0] :   fielda, fieldb...    (original names in query result)
*       row[1] :   fielda1, fieldb1...  (indexed names)
*       row[2] :   fielda2, fieldb2...  (indexed names)
*  and all can be used in replacements (#fielda#, #fieldb1#...)
*
* 	
* Recursive block replacement in Template (only body) using '[start blockname]'...'[end blockname]' alone in a template text row:
*	     Both root Blocks and nested Blocks can be defined in code: see assignBlock() and assignNestedBlock()
*      All Blocks can be defined using one records in $templateArray or in a DB.
* note: when a query for blocks (block != NULL)  returns more than one record, every row produces a new block.
*
* note: all queries can be updated at runtime:
*      Any query can have #field# macros, replaced by current values (in $Odtphp->assigned_field).  This is valid also for queries used with
*      getArraySQL().
*      In nested Blocks queries we can put fields getting value from parent result, like #2# (2 = field position in row[] array resulting from 
*      the query of parent Block, starting from 0). This take precendence over a field having same name (#2#) in $Odtphp->assigned_field.
* 
* Image replacement:
*      Put one or more dummy images in template and near the image, in same block, place a field having a name starting
*      by "img_" (e.g. #img_user# or #img_001"). The value must be the path (relative or absolute) to image file to be used. 
*      The images will be replaced in the report.
*      limits: only one image per template, per block or per nested block;
*              the image size is fixed in template;
*              It allows some file type change: tested '.jpg' in template and '.png' as replacement.
*  note: use only '/' as path separator also in win. 
*  note: the field names like #img_xxxx# are reserved for images.
* 
* Document: 
*  The resulting Document (.odt) can be saved as file in server and/or sended to client.
*  note: the Document is a standard ODT file, it can be open in client OpenOffice, and modified or saved
*      in any supported format ("Save as") or exported as PDF.
*
* Extras:
*     Anonymizer: The author and data are raised to "odtphp" and the actual data. (Take care to delete all old versions from
*         the template file.) An 'original Author' can be defined in language file.
*     Template analyse functions: see  getFieldNames(),  getBlockNames(), getNestedBlockNames().
*     utility: string macro-replacement using stored field/values: see replaceMacros($string).					 
*
* Use:
*   $templateID = 'test01';                                               // templateID == file name
*   $template = dirname(__FILE__)."/templates/".$templateID.".odt";       // the template file   (note: use / also in WIN)
*   $outputFile = dirname(__FILE__)."/reports/".$templateID."_final.odt"; // the saved output file (remote)
* 
*	$odtsql = new Odtphpsql($template);							         //  constructor
*	$odtsql->assign("signature","Marco Sillano");            //  basic field mapping	 (if required)  
* $templateArray = $odtsql->getArrayQueries($templateID);	 //  gets the descriptors for this template as Array of Array
*	$odtsql->assignAllSQL($templateArray );					         //  executes sql, assings fields and blocks	 
* $odtsql->saveODT($outputFile);							             //  optional save
*	$odtsql->downloadODT($templateID);							         //  and/or send to client  
*
* $reportDescription = $odtsql->replaceMacros('My SQL Report for #target# (#today# #now#)');  //  replaceMacros example
* 
* -----------------------------		  
*  dependecies:
*        odtphp.php
*        commonSQL.php
*        config.php
*        language.php
*        lib/pclzip.lib.php 				   
*
*  ver 1-01 16/11/2011 original write (m.s.) 
*  ver 1-02 30/05/2012 debug (m.s.)
*  ver 1-03 02/06/2014 updated regex substitutions (m.s.)
*  ver 1-04 10/10/2016 added language internalization (m.s.)
*  
*  license LGPL 
*  author Marco Sillano  (marco.sillano@gmail.com)
*/		


class Odtphpsql extends Odtphp{
/**
* constructor
* $template: the template full path
*/

public function Odtphpsql($template){	
 global $month;
		    $this->Odtphp($template);        		
// TODO update local in language.php        
        set_local();
// pre-defined fields, application dependent: date, version, user etc...	
// TODO update this (optional) to get required date/time 
        $this->assign("today", strftime ("%d")." ".$month[intval(strftime('%m'))]." ".strftime ("%Y")); // basic field mapping	 '12 April 2017'  
        $this->assign("date",  strftime ("%x"));   // basic field mapping	  '04/12/17' (see odtReportSQL start comment for strftime codes)
        $this->assign("now",   strftime ("%X"));   // basic field mapping	  '14:03:47'
// TODO update this (optional) to get more pre-defined fields:
//      $this->assign("user", 'Marco Sillano');   // basic field mapping	          
        
}	
 
  // templateID (in DB)  MUST be the template file name
public function getArrayQueries($templateName) {
  $query = "SELECT * FROM odt_queries WHERE templateID = '$templateName' ORDER BY ID";
  return  $this->getArraySQL($query);	 //  gets the descriptors for this template
 
}
/*
 *  note: in queries are allowed fields (#field#) replaced by actual values in $this->assigned_field
 */	 
public function getArraySQL($query){	   
	   $q = $this->replaceMacros($query);			
	   return sqlArrayTot($q);	   
 }

 
	/**
	*	Simple assign Fields using records from a query.	
	*   if the rows in result are only 1 (row[0]]), uses the couples field/value
	*	  if the rows are more than one, uses field+index/value  starting from 1 for rows[1]]
	*/		
public function assingFieldsSQL($query){	
	   $blockData= $this->getArraySQL($query);	  
	   $rows = count($blockData);		 
 	   $this->assignArray($blockData[0]);  
	   for ($index = 1; $index < $rows; $index++){
		      foreach($blockData[$index] as $id => $val) {	  
			        $this->assign($id.$index,$val);
			  }	   
	  }
 }	

	/**
	*	Simple assign Block	(not nested) using all records get by the query. 
	*   returns a data array, as required by assingBlockSQLrecursive.
	*/		
			
public function assingBlockSQL($blockName, $query){	
// print("Assigns assingBlockSQL $blockName <br>");						
	   $blockData= $this->getArraySQL($query);		   
 	   $this->assignBlock($blockName,$blockData);
// print("exit assingBlockSQL $blockName <br>");						
	   return  $blockData;   		
 }	
	 	 
 
/**
* Using $templateArray as descriptor for fields, Blocks and queries:
*/ 					 
  
public function assignAllSQL($templateArray ){   
 // print("in Assign All <br>");						
    foreach($templateArray as $block){	
	      if ($block['block'] == '') {	 				  // fields
//print("call assingFieldsSQL <br>");						
 	         $this->assingFieldsSQL($block['query']);
		  }
		  else
	      if ($block['parent'] == '') {					  // top blocks
          	$this->assingBlockSQLrecursive( $block['block'], $block['query'], $templateArray );	
		  }
	  }	        
 }		
 	   
// ================== privates	
//	 used by assignAllSQL
//	 for blocks top level
  
private function assingBlockSQLrecursive( $blockName, $query, $templateArray){	

//print("Assigns BlockRecursive $blockName <br>");						
	   $blockData= $this->assingBlockSQL( $blockName, $query);
	   foreach($templateArray as $block){	
//print("loop for  assignNestedBlockSQLrecursive {$block['block']} <br>");						
	      if ($block['parent'] == $blockName) {			  
		      	 $this->assignNestedBlockSQLrecursive( $block['block'], $block['query'], $templateArray, $blockData, $blockName, array() );
	     }
	  }   
 }	  
	
//	 used by assignAllSQL
//	 any deep
  
private function assignNestedBlockSQLrecursive($nestedBlockName, $query, $templateArray, $parentData, $parentName, $tree){	

	   $i = 1;	// parent index	
 // print("Assigns nested BlockRecursive $nestedBlockName <br>");						
	   foreach($parentData as $row){	
     	  $q = $query; 
	      while ( preg_match( '/#(\d+)#/', $q, $found))
		     {	
			     $pos = $found[1];	
	         $q = str_replace("#$pos#", $row[$pos], $q);  
			 } 
 // print $q.'<br>';
		  $rTree = $tree;
		  $rTree[$parentName]=$i; 	 // parent and 	index appended to $tree
		  $subData= $this->getArraySQL($q); 
		  $this->assignNestedBlock($nestedBlockName,$subData,$rTree);	  
    	foreach($templateArray as $block){	
	         if ($block['parent'] == $nestedBlockName) {  
	              $this->assignNestedBlockSQLrecursive( $block['block'], $block['query'], $templateArray, $subData, $nestedBlockName, $rTree);	   		    
			     }
	      }	// end foreach	 
		  $i++;
	   } // end foreach		 	 	
   }			  
}
 
?>
  