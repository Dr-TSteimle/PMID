<?php
/**
 * PMID
 * 
 * This is a PHP extension for mediawiki. It displays article metadata from parsing a PMID
 * number. It uses PubMedAPI from Asif Rahman https://github.com/asifr for parsing NCBI server.
 * Stores results inside a MySQL table.
 * 
 * Copyright (c) 2021 Thomas Steimlé
 * https://github.com/Dr-TSteimle/PMID
 */

class pmid {
	static function onParserInit( Parser $parser ) {
		$parser->setHook( 'pmid', array( __CLASS__, 'pmidRender' ) ); 
		return true;
	}

	static function pmidRender( $input, array $args, Parser $parser, PPFrame $frame ) {
		$PMID = intval($input);
        //Check pmid
        //init DB
        $db = wfGetDB(DB_MASTER);
        $db->query("create table if not exists cachepmid ( id INTEGER PRIMARY KEY AUTO_INCREMENT, q TEXT NOT NULL INDEX UNIQUE, r TEXT NOT NULL, f TEXT, p TEXT);");

        //$rdb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        //$db = $rdb->getConnectionRef( DB_REPLICA );

        //init PubMedAPI
        $PubMedAPI = new PubMedAPI();
        
        if(! $args) {
            if($PMID==null) return "Invalid PMID";
            if(!$db){
                $output = $db->lastErrorMsg();
            } else {
//                $result = $db->newSelectQueryBuilder()
//	           	->table( 'cachepmid')
//                ->fields( array('q','r','f','p') )
//                ->where( 'q = "'.$PMID.'"' )
//                ->fetchResultSet();
                $result = $db->select('cachepmid',array('q','r','f','p'), 'q = "'.$PMID.'"', 'LIMIT 1');
                
                if ($result) {
                    if($result->numRows() > 0) {
                        $r = $result->fetchRow();
                        $XML = simplexml_load_string(stripslashes($r['r']), 'SimpleXMLElement');
                        if (strpos($r['f'], strval($parser->getTitle())) === false) { //cité sur une nouvelle page
                        $up = $r['f']."#".strval($parser->getTitle());
                        $db->query('UPDATE cachepmid SET f = "'.$up.'" WHERE q = "'.$PMID.'";');
                        }
                        $results = $PubMedAPI->parse($XML, false);
			if (empty($results[0])) {
				$error_msg = "Error with the PMID ".$PMID;
				trigger_error($error_msg, E_USER_WARNING);
				$output = $error_msg;
			} else {
	                        $output = renderPMID($results[0],$r['p']);
			}
                    } else {
                        $XML = $PubMedAPI->pubmed_efetch($PMID);
                        $results = $PubMedAPI->parse($XML, false);
                        
                        //Create Titre for the new entry
                        $pageT = "Article_".$PMID;
                        $titre = Title::newFromText($pageT);
                        while ($titre->exists()) {
                            $pageT = "Article_".$PMID."_".substr(md5(microtime()),rand(0,26),5);
                            $titre = Title::newFromText($pageT);
                        }
                        
                        //Insert INTO cachepmid
                        $ins = array();
                        $ins["q"] = $PMID; //query
                        $ins["r"] = $XML->asXML(); //response
                        $ins["f"] = $parser->getTitle(); //from
                        $ins["p"] = $pageT; //page title
                        $op = array();
                        $op["IGNORE"] = true;
                        $db->insert('cachepmid', $ins, '__METHOD__', $op);
                        
                        //generate content form new article
                        $text = renderPMIDtable($results[0],$ins["f"]);
                        
                        //Create article:
                        $jobParams = array ( 'titre' => $titre, 'txt' => $text );
                        $title = Title::newFromText('PMIDAdd Job '.$pageT);
                        
                        //sleep(1);
                        $title = Title::newFromText((string)$titre);
                        $page = new WikiPage($title);
                        $content = ContentHandler::makeContent((string)$text,$title);
                        $user = User::newFromSession();
                        
                        $page->doUserEditContent($content, $user, 'Page auto PMID');
                        
                        $output = renderPMID($results[0],$ins['p']);
                    }
                }
            }
        $output = $parser->recursiveTagParse($output, $frame);   
        } else { //Si args
            if(isset($args["query"])) {
                if($args["query"] == "f") {
                $output = ListPageCitePMID($db,$PMID);
                $output = $parser->recursiveTagParse($output,$frame);
                }
                if($args["query"] == "ALL") {
                    $result = $db->query('SELECT rowid,* from cachepmid');
                    
                    $output = "{| class='wikitable sortable'\r\n";
                    $output .= "|-\r\n";
                    $output .= "!Titre\r\n";
                    $output .= "!Année\r\n";
                    $output .= "!Auteurs\r\n";
                    $output .= "!Journal\r\n";
                    $output .= "!Page\r\n";
                    $output .= "!Liens\r\n";
                    if (isset($args['option'])) $output .= "!Options\r\n";
                    
                    while ($r=$result->fetchRow())  {
                        $liens = ListPageCitePMID($db,$r["q"]);
                        
                        if ( isset($args["from"]) && strpos($r['f'], $args["from"]) === false) { continue; }
                        
                        $XML = simplexml_load_string($r['r']);
                        $results = $PubMedAPI->parse($XML, false);
                        $rt = $results[0];
                        
                        $output .= "|-\r\n";
                        
                        $title = isset($rt["title"]) ? $rt["title"] : "";
                        $output .= "|".$title."\r\n";
                        
                        $year = isset($rt["year"]) ? $rt["year"] : "";
                        $output .= "|".$year."\r\n";
                        
                        $rt['authors'] = array_slice($rt['authors'],0,3);
                        $authors = implode(", ",$rt['authors']).", et al.";
                        $output .= "|".$authors."\r\n";
                        
                
                        $jtitle = isset($rt["journal"]) ? $rt["journal"] : "";
                        $output .= "|".$jtitle."\r\n";
                        
                        $url = "[http://www.ncbi.nlm.nih.gov/pubmed/".$r["q"]." Pubmed]";
                        $output .= "|".$url."<br>[[".$r['p']."|Wiki]]\r\n";
                        
                        $output .= "|".$liens."\r\n";
                        
                        if (isset($args['option'])) $output .= "|[{{fullurl:{{FULLPAGENAME}}|param=erase".$r['id']."}} Erase] \r\n";
                        
                        
                    }
                    $output .= "|}\r\n";
                    
                    $output = $parser->recursiveTagParse($output, $frame );
                }
            }
        }
        return $output;
        // return "==ds==";
	}
}

function renderPMID($result,$pageT) { //ajouter wiki
    $result['authors'] = array_slice($result['authors'],0,3);
    $authors = implode(", ",$result['authors']).", et al.";
    $txt = "'''".$authors."''' ";
    $txt .= $result['title']." ";
    $txt .= "''".$result['journal']."'' ";
    $txt .= $result['year'].";";
    $txt .= $result['volume'];
    if (isset($result['issue']) && $result['issue'] != "") $txt .= "(".$result['issue'].")";
    if (isset($result['pages']) && $result['pages'] != "") $txt .= ":".$result['pages']." ";
    $txt .= "[http://www.ncbi.nlm.nih.gov/pubmed/".$result['pmid']." Pubmed] [[".$pageT."|Wiki]]";
    return $txt;
}

function renderPMIDtable($result,$from) {
    $authors = implode(", ",$result['authors']).", et al.";
    
    //Tableau
    $text = "\r\n";
    $text .= "{| class='wikitable' \r\n";
    $text .= "|-\r\n";
    $text .= "! scope='row'| Titre\r\n";
    $text .= "| ".$result['title']."\r\n";
    $text .= "|-\r\n";
    $text .= "! scope='row'| Année\r\n";
    $text .= "| ".$result['year']."\r\n";
    $text .= "|-\r\n";
    $text .= "! scope='row'| Revue\r\n";
    $text .= "| ".$result['journal']."\r\n";
    $text .= "|-\r\n";
    $text .= "! scope='row'| Auteurs\r\n";
    $text .= "|".$authors."\r\n";
    $text .= "|-\r\n";
    $text .= "! scope='row'| Lien\r\n";
    $text .= "| [http://www.ncbi.nlm.nih.gov/pubmed/".$result['pmid']." Pubmed]\r\n";
    $text .= "|-\r\n";
    $text .= "! scope='row'| Pages citant cet article\r\n";
    $text .= "| <pmid query='f'>".$result['pmid']."</pmid>\r\n";
    $text .= "|-\r\n";
    $text .= "! scope='row'| Abstract\r\n";
    $text .= "|".$result['abstract']."\r\n";
    $text .= "|}\r\n";
    $text .= "\r\n";

    $text .= "[[category:".$from."]]\r\n";
    
    return $text;
}

function ListPageCitePMID($db,$PMID) {
    $result = $db->select('cachepmid',array('q','r','f','p'), 'q = "'.$PMID.'"');
    if ($result) {
        if($result->numRows() > 0) {
            $r = $result->fetchRow();
            if (strpos($r['f'], "#")) {
                $arr = explode("#",$r['f']);
                $output = "";
                foreach ($arr as $from) {
                PageCiteUpdaterPMID($from,$PMID,$db);
                $output .= "[[".$from."]] | ";
                }
                $output = rtrim($output,"| ");
            } else {
            PageCiteUpdaterPMID($r['f'],$PMID,$db);
            $output = "[[".$r['f']."]]";
            }
        } else {
            $output = "";
        }
    } else {
        $output = "";
    }
    return $output;
}

function PageCiteUpdaterPMID($title,$PMID,$db) {
  $titleObject = Title::newFromText($title);
  if ( !$titleObject->exists() ) {
    $result = $db->select('cachepmid',array('q','r','f','p'), 'q = "'.$PMID.'"');
    if ($result) {
      if($result->numRows() > 0) {
        $r = $result->fetchRow();
        if (strpos($r['f'], "#")) {
          $arr = explode("#",$r['f']);
          $up = array();
          foreach($arr as $from) {
            if($from !== $title) $up[] = $from;
          }
          $up = implode("#",$up);
          $db->query("UPDATE cachepmid SET f = '".$up."' WHERE q = '".$PMID."';");
        } else {
          if ($r['f'] == $title) $db->query("UPDATE cachepmid SET f = NULL WHERE q = '".$PMID."';");
        }
      }
    }
  }
  return true;
}
