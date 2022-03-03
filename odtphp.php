
 <?php
/**
 *  low level primitives (read, replace, write templates)
 *  for odtReportSQL
 * -----------------------------
 *  dependecies:
 *        language.php
 *        lib/pclzip.lib.php
 *
 *  ver 1-01 16/11/2011 original write (m.s.)
 *  ver 1-02 30/05/2012 debug
 *  ver 1-03 02/06/2014 updated regex substitutions (m.s.)
 *  ver 1-04 10/10/2016 added language internalization
 *  ver 1-05 24/01/2021 minor bugs
 *  ver 1-06 24/02/2022 updating to PHP 8
 *  author Marco Sillano  (marco.sillano@gmail.com)
 *
 *  license GPL
 */
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(-1);
require_once dirname(__FILE__)."/lib/pclzip.2.8.4.lib.php";
require_once dirname(__FILE__)."/lib/language.php";
define("__thisversion__", "1-05");
class Odtphp {
    private $template;
    private $content;
    private $style;
    private $manifest;
    private $tmpDir = "D:/tmp/a/b/c"; //  must be writable
    //  private $tmpDir = "/storage/emulated/0/tmp/odtphp/parts";   // for linux
    private $assigned_field = array();
    private $assigned_block = array();
    private $assigned_nested_block = array();
    private $block_content = array();
    private $block_count = array();
    private $images = array();
    private $nested_block_count = array();
    private $fieldNames = array();
    private $blockNames = array();
    private $nestedBlockNames = array();
    private $processed = FALSE;
    private $hasimages = FALSE;
    /**
     *  Reads the template and does the initial stuff
     */
    public function Odtphp($template) {
        global $Translation;
        // ERRXX
        error_reporting(E_ALL);
		
		if (substr(php_uname(), 0, 7) == "Windows") {
             $this->tmpDir = "D:/tmp/odtphp/parts";
        } else {
             $this->tmpDir = "/storage/emulated/0/tmp/odtphp/parts";
        }
        if (file_exists($template)) {
            $this->template = $template;
        } else {
            die(sprintf($Translation['template not found'], $template));
        }
        $this->extract();
    }
    /**
     * Access to fields names present in template
     */
    public function getFieldNames() {
        return $this->fieldNames;
    }
    /**
     * Access to Block names present in template
     */
    public function getBlockNames() {
        return $this->blockNames;
    }
    /**
     * Access to nested Block names present in template
     */
    public function getNestedBlockNames() {
        return $this->nestedBlockNames;
    }
    /**
     * basic field assign using strings
     */
    public function assign($field, $value) {
        $this->assigned_field[$field] = $value;
    }
    /**
     * basic field assign using array
     *   $fields as from mySQL:
     *      $fields = mysql_fetch_assoc($res)
     */
    public function assignArray($fields) {
        foreach($fields as $field => $value) {
            $this->assigned_field[$field] = $value;
        }
    }
    /**
     * basic Block assign: one Block per data row
     *   $values as from mySQL:
     *       while ( $row = mysql_fetch_assoc($res)) {
     *           array_push($values, $row);  }
     *
     * see Odtphpsql->getArraySQL($query)
     */
    public function assignBlock($blockname, $values) {
        $this->assigned_block[$blockname] = $values;
    }
    /**
     * basic nested Block assign:
     * position on the tree done using a parent index array
     *   $values: see  assignBlock()
     *   $parent like: array("members"=>1,"pets"=>1))  (starting from 1)
     */
    public function assignNestedBlock($blockname, $values, $parent) {
        array_push($this->assigned_nested_block, array("block" => $blockname, "values" => $values, "parent" => $parent));
    }
    /**
     *  Replaces '#field#' using field/value in $assigned_field.
     *  No image prossing: image fields (#img_xx#) are stripped out
     *  see: replaceFields(), filter() in langoage.php (language dependent).
     */
    public function replaceMacros($string) {
        $tmp = $string;
        foreach($this->assigned_field as $field => $value) {
            if (stripos($field, 'img_') === 0) {
                $tmp = str_ireplace('#'.$field.'#', '', $tmp);
            } else {
                $tmp = str_ireplace('#'.$field.'#', filter($value), $tmp);
            }
        }
        return $tmp;
    }
    /**
     * Does all replacements ad saves resulting  file in $outputFile
     * $outputFile: complete path, '.odt' extension
     * Re-callable many times
     */
    public function saveODT($outputFile) {
        if (!$this->processed) { //  only first time
            // cuts blocks in content
            $this->content = $this->parseBlocks($this->content);
            // for images: puts a place marker in manifest
            if ($this->hasimages) {
                // print("images? yes");
                $pos = strpos($this->manifest, '<manifest:file-entry manifest:media-type="image/');
                $this->manifest = substr($this->manifest, 0, $pos - 1)."<!-- images -->\n".substr($this->manifest, $pos);
            }
            // builds root blocks
            if (count($this->assigned_block) > 0) {
                //ERRXX
                //    print("<br>Loop blocks? ".count($this->assigned_block) );
                foreach($this->assigned_block as $block => $values) {
                    $n = 1;
                    foreach($values as $value) {
                        $value['n'] = $n++;
                        $this->addBlock($block, $value);
                    }
                }
            }
            // builds all nested  blocks
            if (count($this->assigned_nested_block) > 0) {
                //ERRXX
                //   print("<BR>Loop Nested blocks? ".count($this->assigned_nested_block) );
                foreach($this->assigned_nested_block as $array) {
                    $this->addNestedBlock($array['block'], $array['values'], $array['parent']);
                }
            }
            // replace fields in all content and does template (not in block) image processing
            $this->content = $this->repaceFields($this->content, $this->assigned_field); // processes images
            // replace fields for headers and footers
            $this->style = $this->replaceMacros($this->style); // dont processes images
        } // end if not processed
        //  zips the outputFile
        $this->compact($outputFile);
        $this->processed = TRUE;
    }
    /**
     * sends resulting  file to client, as response page
     * $name = file name, sended to client (no final '.odt')
     *  If $name is null, it uses a random name.
     */
    public function downloadODT($name = null) {
        // NOTE: tmpDir must be writable
        $tmp_filename = $this->tmpDir."/../".uniqid('', true).".odt";
        if (is_null($name)) {
            $name = basename($tmp_filename, ".odt");
        }
        $this->saveODT($tmp_filename);
        $this->downloadFile($tmp_filename, $name.".odt");
    }
    function execInBackground($cmd) {
        if (substr(php_uname(), 0, 7) == "Windows") {
            pclose(popen("start /B ".$cmd, "r"));
        } else {
            exec($cmd." > /dev/null &");
        }
    }
    // ==============================   private functions
    /*
     * template processing start step:
     * Reads and unzip the template file.
     * Gets fields, blocks, nestedBlocks names.
     */
    private function extract() {
        // NOTE: tmpDir must be writable
        if (file_exists($this->tmpDir.'/META-INF') && is_dir($this->tmpDir.'/META-INF')) {
            //  cleanup of the tmp dir
           $this->rrmdir(realpath($this->tmpDir.'/../'));
        }
        @mkdir($this->tmpDir, 0777, true);
        $err = 100;
        try {
            $archive = new PclZip(realpath($this->template));
            $err = $archive->extract(PCLZIP_OPT_PATH, $this->tmpDir);
        } catch (Exception $e) {
            echo 'Caught exception decoding: '.$e->getMessage()."\n";
        }
        if ($err < 0)
            die($archive->errorInfo(true)); // extra test
        // get the contents
        $this->content   = file_get_contents($this->tmpDir."/content.xml");
        $this->style     = file_get_contents($this->tmpDir."/styles.xml");
        $this->manifest  = file_get_contents($this->tmpDir."/META-INF/manifest.xml");
        $this->hasimages = !(strpos($this->manifest, 'media-type="image') === false);
        // cleanup before processing:
        $this->pre_clean();
        // extra template analysis
        // get Fields names
        preg_match_all('/#(\w+)#/', $this->content, $fieldsC);
        preg_match_all('/#(\w+)#/', $this->style, $fieldsS);
        $this->fieldNames = array_values(array_unique(array_merge($fieldsC[1], $fieldsS[1])));
        // get Blocks names
        preg_match_all('/\[start (\w+)\].*?\[end \1\]/', $this->content, $fields);
        $this->blockNames = array_values(array_unique($fields[1]));
        // get NestedBlocks names
        preg_match_all('/\[start (\w+)\]/', $this->content, $fields);
        $this->nestedBlockNames = array_values(array_unique(array_diff($fields[1], $this->blockNames)));
        $this->processed = FALSE;
    }
    /*
     * template processing final step:
     * zips new file in  $output.
     */
    private function compact($output) {
        if (!$this->processed) {
            // cleanup after processing:
            $this->post_clean();
            // copy data
            file_put_contents($this->tmpDir."/content.xml", $this->content);
            file_put_contents($this->tmpDir."/styles.xml", $this->style);
            if ($this->hasimages) {
                file_put_contents($this->tmpDir."/META-INF/manifest.xml", $this->manifest);
            }
        } // end if not processed
        // zips
        unlink($output);
        try {
            $archive = new PclZip($output);
            $archive->create($this->tmpDir, PCLZIP_OPT_REMOVE_PATH, $this->tmpDir);
            //          sleep(1);
        } catch (Exception $e) {
            echo 'Caught exception compressing: '.$e->getMessage()."\n";
        }
    }
    /*
     *  Replaces '#field#' using $array (couples field/value)
     *  This does image prossing: image fields (#img_xx#) are stripped out but a new image replaces the dummy image in template.
     *  see: replaceMacro()
     */
    private function repaceFields($template, $array) {
        global $Translation;
        foreach($array as $id => $val) {
            if (stripos($id, 'img_') === 0) {
                if ($this->hasimages) {
                    $template = $this->pictureProcessing($id, $val, $template);
                } else {
                    $template = str_ireplace('#'.$id.'#', $Translation['picture not found'], $template);
                }
            } else {
                $template = str_ireplace('#'.$id.'#', filter($val), $template);
            }
        }
        return $template;
    }
    /*
     *    All picture stuff. Not recursive, only one Image per block
     */
    private function pictureProcessing($label, $image, $template) {
        global $Translation;
        if ($frompath = realpath($image)) {
            // copy file -> update manifest
            $fullpath = "Pictures/".uniqid(true).strrchr($image, '.');
            $topath = $this->tmpDir."/".$fullpath;
            copy($frompath, $topath);
            // update manifest
            $this->manifest = str_replace('<!-- images -->', '<manifest:file-entry manifest:media-type="image/jpeg" manifest:full-path="'.$fullpath.'"/>'."\n<!-- images -->", $this->manifest);
            // update file
            $template = preg_replace('/draw:image xlink:href="\S*"/', 'draw:image xlink:href="'.$fullpath.'"', $template);
            // destroy field
            $template = str_ireplace('#'.$label.'#', '', $template);
            return $template;
        } else {
            $template = str_ireplace('#'.$label.'#', sprintf($Translation['file picture not found'], $image), $template);
            return $template;
        }
    }
    /*
     * Function download to client
     */
    private function downloadFile($filepath, $name) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename='.$name);
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: '.filesize($filepath));
        ob_clean();
        flush();
        readfile($filepath);
    }
    /*
     * Strips and stores all Blocks from template content.
     */
    private function parseBlocks($txt) {
        $matches = array();
        $ret = $txt;
        if (!$txt)
            return;
        preg_match_all('/\[start (\w+)\].*?\[end \1\]/s', $txt, $matches);
        if (count($matches[1]) > 0) {
            foreach($matches[1]as $block) {
                $ret = $this->parseBlock($block, $ret);
            }
        }
        return $ret;
    }
    /*
     * recursive step for  $parseBlocks
     */
    private function parseBlock($name, $txt) {
        // print ('in parse Block '.$name);
        // we strip the block markup
        $previous_pos = $this->getPreviousPosOf("start ".$name, "<text:p ", $txt);
        $end_pos = $this->getNextPosOf("start ".$name, ":p>", $txt) + 3;
        //
        // print (' start:'.$previous_pos. ' '.$end_pos);
        $txt = str_replace(substr($txt, $previous_pos, $end_pos - $previous_pos), "<!-- start ".$name." -->", $txt);
        $previous_pos = $this->getPreviousPosOf("end ".$name, "<text:p ", $txt);
        $end_pos = $this->getNextPosOf("end ".$name, ":p>", $txt) + 3;
        $txt = str_replace(substr($txt, $previous_pos, $end_pos - $previous_pos), "<!-- end ".$name." -->", $txt);
        // we save the template content for the block
        $block = preg_match("`<!-- start ".$name." -->(.*)<!-- end ".$name." -->`", $txt, $matches);
        // echo "<pre>";
        // echo  print_r($matches)  ;
        // echo "</pre>";
        if (array_key_exists(1, $matches) > 0) {
            $this->block_content[$name] = $this->parseBlocks($matches[1]);
        }
        // we remove the template content from the doc
        $txt = preg_replace('`<!-- start '.$name.' -->(.*)<!-- end '.$name.' -->`', '<!-- start '.$name.' --><!-- end '.$name.' -->', $txt);
        // print ('out parse Block '.$txt);
        return $txt;
    }
    /*
     *replaces top level Blocks from assigned_block
     */
    private function addBlock($blockname, $values) {
        $block = $this->block_content[$blockname];
        // print ('in addblock '.$this->filter($block).'<br>');
        if (array_key_exists($blockname, $this->block_count)) {
            $this->block_count[$blockname] = $this->block_count[$blockname] + 1;
        } else {
            $this->block_count[$blockname] = 1;
        }
        $block = $this->repaceFields($block, $values);
        // print ('out addblock '.$this->filter($block).'<br>');
        $this->content = str_replace("<!-- end ".$blockname." -->", "<!-- block_".$blockname."_".$this->block_count[$blockname]." -->".$block."<!-- end_block_".$blockname."_".$this->block_count[$blockname]." --><!-- end ".$blockname." -->", $this->content);
    }
    /*
     * low-level: replaces one nested Block from $assigned_nested_block .
     * $values is an array of arrays fields/values: one array for block
     *  $parent like: array("members"=>1,"pets"=>1))  (starting from 1)
     * ver.02 : riscritta eliminando regex. Maggiore velocità
     */
    private function addNestedBlock($blockname, $values, $parent) {
        global $Translation;
        $start = 0;
        $end = 0;
        $matches = array();
        if (is_array($parent) && count($parent) > 0) {
            $block = "";
            $regex = '`(?U)(.*)`';
            $link_nested_count = array();
            foreach($parent as $id => $node) {
                // uso di reg
                //                  if($regex == "`(?U)(.*)`"){
                //      $regex = str_replace("(.*)","<!-- block_".$id."_".$node." -->(.*)<!-- end_block_".$id."_".$node." -->",$regex);
                //                  } else {
                //      $regex = str_replace("(.*)",".*<!-- block_".$id."_".$node." -->(.*)<!-- end_block_".$id."_".$node." -->.*",$regex);
                //                  }
                array_push($link_nested_count, $id.$node);
            }
            $idnested = implode("_", $link_nested_count)."_".$blockname;
            if (array_key_exists($idnested, $this->nested_block_count)) {
                //                  $current_index = $this->nested_block_count[$idnested] + 1;
                $this->nested_block_count[$idnested]++;
            } else {
                $this->nested_block_count[$idnested] = 1;
                //                  $current_index = 1;
            }
            $block_content = $this->block_content[$blockname];
            $blockIndex = 1;
            foreach($values as $row) {
                $current_block = $block_content;
                $row['n'] = $blockIndex;
                $current_block = $this->repaceFields($current_block, $row);
                $block .= "<!-- block_".$blockname."_".$blockIndex." -->".$current_block."<!-- end_block_".$blockname."_".$blockIndex." -->";
                $blockIndex++;
            }
            // uso di reg       . having problems with big files
            //      $founds =    preg_match($regex,$this->content,$matches);
            //    $part = $matches[1];
            // non uso di regex
            $start = 0;
            $lastid = 0;
            $lastnode = '';
            foreach($parent as $id => $node) {
                $start = strpos($this->content, "<!-- block_".$id."_".$node." -->", $start);
                $start += strlen("<!-- block_".$id."_".$node." -->");
                $lastid = $id;
                $lastnode = $node;
            }
            $end = strpos($this->content, "<!-- end_block_".$lastid."_".$lastnode." -->", $start);
            $part = substr($this->content, $start, $end - $start);
            $new = str_replace("<!-- end ".$blockname." -->", $block."<!-- end ".$blockname." -->", $part);
            // non uso di regex
            $left = substr($this->content, 0, $start);
            $rigth = substr($this->content, $end);
            $this->content = $left.$new.$rigth;
            /*
            // uso di regex
            $regex2 = str_replace('(.*)',').*(',$regex);
            //ERRXX modificata riga, aggiunto (?U)
            $regex2 = str_replace('`(?U)<','`(?U)(<',$regex2);
            $regex2 = str_replace('>`','>)`',$regex2);
            // special chars: provvisorio
            $new = str_replace("\\",'&xxy;',  $new);
            $new = str_replace('$','&xxx;',  $new);
            $new = preg_replace($regex2,'${1}'.$new.'${2}',$this->content,1);
            // restore provvisorio
            $new = str_replace('&xxx;','$', $new);
            $this->content = str_replace('&xxy;',"\\", $new);
             */
        } else {
            throw new Exception(sprintf($Translation['nested exception'], $blockname));
        }
    }
    /*
     * cleanup before template processing
     */
    private function pre_clean() {
        global $Translation;
        // Anonymizer
        $meta = file_get_contents($this->tmpDir."/meta.xml");
        //  <dc:title>Idoneità - Allegato A</dc:title>
        //  <meta:initial-creator>Marco Sillano</meta:initial-creator>
        //  <meta:creation-date>2011-11-05T14:05:00</meta:creation-date>
        //  <dc:date>2011-11-14T19:30:07.18</dc:date>
        //  <dc:creator>Marco Sillano</dc:creator>
        $meta = preg_replace('`<meta:initial-creator>.*</meta:initial-creator>`', '<meta:initial-creator>'.$Translation['original author'].'</meta:initial-creator>', $meta);
        $meta = preg_replace('`<meta:creation-date>.*</meta:creation-date>`', '<meta:creation-date>'.date('Y-m-d\TH:i:s.00').'</meta:creation-date>', $meta);
        $meta = preg_replace('`<dc:creator>.*</dc:creator>`', "<meta:creator>Odtphp ver.".__thisversion__."</meta:creator>", $meta);
        $meta = preg_replace('`<dc:date>.*</dc:date>`', '<dc:date>'.date('Y-m-d\TH:i:s.00').'</dc:date>', $meta);
        file_put_contents($this->tmpDir."/meta.xml", $meta);
        //
    }
    /*
     * cleanup after template processing
     */
    private function post_clean() {
        global $Translation;
        // cleanup after processing: eliminates empty blocks
        //ERRXX trace in error
        if ($this->content == "") {
            print($Translation['none result']);
        } else
            $this->content = preg_replace('`<!-- start (\w+) -->\s*<!-- end \1 -->`', '', $this->content);
    }
    /*
     *  util: recursive delete dir
     */
    private function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir."/".$object) == "dir")
                        $this->rrmdir($dir."/".$object);
                    else
                        unlink($dir."/".$object);
                }
            }
             @ rmdir($dir);
        }
    }
    /*
     * util: used by  parseBlock
     */
    private function getNextPosOf($start_string, $needle, $txt) {
        $current_pos = strpos($txt, $start_string);
        $len = strlen($needle);
        $not_found = true;
        while ($not_found && $current_pos <= strlen($this->content)) {
            if (substr($txt, $current_pos, $len) == $needle) {
                return $current_pos;
            } else {
                $current_pos = $current_pos + 1;
            }
        }
        return 0;
    }
    /*
     * util: used by  parseBlock
     */
    private function getPreviousPosOf($start_string, $needle, $txt) {
        $current_pos = strpos($txt, $start_string);
        $len = strlen($needle);
        $not_found = true;
        while ($not_found && $current_pos >= 0) {
            if (substr($txt, $current_pos, $len) == $needle) {
                return $current_pos;
            } else {
                $current_pos = $current_pos - 1;
            }
        }
        return 0;
    }
}
?>