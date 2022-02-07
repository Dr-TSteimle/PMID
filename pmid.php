<?php
/*
Author: Thomas Steimlé

Installation:
        install this file in :
	    ${MWROOT}/extensions/PMID/pmid.php
	
	
	
	and add the following line at the end of ${MWROOT}/LocalSettings.php :
	require_once("$IP/extensions/PMID/pmid.php");
*/

/**
 * Protect against register_globals vulnerabilities.
**/

require_once("$IP/extensions/PMID/PubMedAPI.php");

if(!defined('MEDIAWIKI')){
        echo("This is an extension to the MediaWiki package and cannot be run standalone.\n" );
        die(-1);
}

/* Avoid unstubbing $wgParser on setHook() too early on modern (1.12+) MW versions */
if ( defined( 'MW_SUPPORTS_PARSERFIRSTCALLINIT' ) ) {
        $wgHooks['ParserFirstCallInit'][] = 'myPMID';
} else {
        $wgExtensionFunctions[] = 'myPMID';
}


$wgJobClasses['PMIDAddJob'] = 'PMIDAddJob'; //Add class for job

/**
 * An array of extension types and inside that their names, versions, authors and urls. This credit information gets added to the wiki's Special:Version page, allowing users to see which extensions are installed, and to find more information about them.
**/

$wgExtensionCredits['parserhook'][] = array(
        'name'          =>      'PMID',
        'version'       =>      '0.1',
        'author'        =>      'Thomas St',
        'url'           =>      '',
        'description'   =>      'Resolve a PMID [[Special:PMID]]'
);

function myPMID() {
  global $wgParser;
  $wgParser->setHook('pmid', 'myRenderPMID');
  return true;
}


function myRenderPMID($input, $args, $parser, $frame) {
    
    $PMID = intval($input);
    
    
    //Check pmid
    
    //init DB
    $db = wfGetDB(DB_MASTER);
    $db->query("create table if not exists cachepmid ( id INTEGER PRIMARY KEY AUTOINCREMENT, q TEXT NOT NULL, r TEXT NOT NULL, f TEXT, p TEXT);");
    
    //init PubMedAPI
    $PubMedAPI = new PubMedAPI();
    
    
    if(! $args) {
        if($PMID==null) return "Invalid PMID";
        if(!$db){
  		    $output = $db->lastErrorMsg();
  	    } else {
  	        $result = $db->select('cachepmid',array('q','r','f','p'), 'q = \''.$PMID.'\'');
  	        
  	        if ($result) {
  			    if($result->numRows() > 0) {
  			        $r = $result->fetchRow();
      				$XML = simplexml_load_string($r['r']);
      				if (strpos($r['f'], strval($parser->getTitle())) === false) { //cité sur une nouvelle page
      				  $up = $r['f']."#".strval($parser->getTitle());
      				  $db->query("UPDATE cachepmid SET f = '".$up."' WHERE q = '".$PMID."';");
      				}
      				$results = $PubMedAPI->parse($XML, false);
  			        $output = renderPMID($results[0],$r['p']);
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
      				$ins["r"] = $XML->asXML();; //response
      				$ins["f"] = $parser->getTitle(); //from
      				$ins["p"] = $pageT; //page title
      				$db->insert('cachepmid',$ins);
      				
      				//generate content form new article
      				$text = renderPMIDtable($results[0],$ins["f"]);
      				
      				//Create article:
      				$params = array ( 'titre' => $titre, 'txt' => $text );
                    $title = Title::newFromText('PMIDAdd Job '.$pageT);
                    $job = new PMIDAddJob($title,$params);
                    JobQueueGroup::singleton()->push($job);
                    
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
  $result = $db->select('cachepmid',array('q','r','f','p'), 'q = \''.$PMID.'\'');
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
    $result = $db->select('cachepmid',array('q','r','f','p'), 'q = \''.$PMID.'\'');
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

/**
 * Add Job Create Page of article $params array ("titre" => $titre, "txt" => $txt) titre de l'article et txt du contenu.
**/

class PMIDAddJob extends Job {
	public function __construct( Title $title, array $params ) {
		parent::__construct( 'PMIDAddJob', $title, $params );
	}

	/**
	 * Queue Some more jobs
	 * @return bool
	 */
	public function run() {
		$titre = $this->params[ 'titre' ]; //Title::newFromText('hey');
        	$text = $this->params[ 'txt' ];
        	$article = new Article($titre);
        	$article->doEdit( $text, 'Page Auto PMID', EDIT_NEW | EDIT_FORCE_BOT );
		return true;
	}
}

/**
 * SPECIAL PAGE: Special:PMID
**/
$wgSpecialPages['Pmid'] = 'Pmid';

class Pmid extends SpecialPage {
	function __construct() {
		parent::__construct('Pmid');
	}
 
	function execute( $par ) {
		$request = $this->getRequest();
		$output = $this->getOutput();
		$this->setHeaders();
 
		# Get request data from, e.g.
		$param = $request->getText('param');

    $wikitext = "==List of all articles in the database==\r\n";
    $wikitext .= "<pmid query='ALL' option='true'/>\r\n";
    $wikitext .= "==Options==\r\n";
		$wikitext .= "[{{fullurl:{{FULLPAGENAME}}|param=sure}} Erase the database]\r\n";
		
		if($param == "sure") {
      $wikitext = "<b>WARNING this action is irreversible.</b><br>Are you sure ?<br>[{{fullurl:{{FULLPAGENAME}}|param=drop}} YES!] [{{fullurl:{{FULLPAGENAME}}}} NO]";
		}
		
		if($param == "drop") {
		  $db = wfGetDB( DB_MASTER );
      $result = $db->query('SELECT * from cachepmid');
      while ($r = $result->fetchRow()) {
        if (isset($r['p']) && $r['p'] !== "") {
          $titre = Title::newFromText($r['p']);
          $article = new Article($titre);
          $article->doDelete("Request by user on Special:PMID");
        }
      }
      $db->query("DROP TABLE cachepmid;");
		}
		
		if(substr($param,0,5)=="erase") {
		  $db = wfGetDB( DB_MASTER );
      $id = substr($param,5);
      $result = $db->select('cachepmid',array('q','r','f','p'), 'rowid = \''.$id.'\'');
      $r = $result->fetchRow();
      if (isset($r['p']) && $r['p'] !== "") {
          $titre = Title::newFromText($r['p']);
          $article = new Article($titre);
          $article->doDelete("Request by user on Special:PMID");
        }
      $db->query("DELETE FROM cachepmid WHERE rowid ='".$id."';");
		}
		$output->addWikiText($wikitext);
	}
}
?>