<?php
# handle {{linkcheck>on|off}}  to enable/disable link-checking within the page.
if(!defined('DOKU_INC')) die();
require_once(DOKU_PLUGIN.'syntax.php');
require_once __DIR__.'/helperfunctions.php';

class syntax_plugin_linkcheck extends DokuWiki_Syntax_Plugin {
	protected $helper;
	public function __construct()
	{
			$this->helper = $this->loadHelper('linkcheck', false);
	}
	
	function getType(){ return 'substition'; }
  function getPType(){ return 'normal'; }
	function getSort(){ return 196; }
	function connectTo($mode) {
		$this->Lexer->addSpecialPattern('{{linkcheck>[^}]+}}',$mode,'plugin_linkcheck');
	}

	#$s is $R->doc that we are modifying.
function injectclassforlinks(&$doc,$urlmap,$otherurls){
	#We ignore the unlikely possibility that a url may appear in different linkcheck:on/off blocks. If a url appears in a linkcheck:on block, the same url will also be registered for linkcheck in other blocks.
	if(strpos($doc,'urlextern')===false) return;

	preg_match_all('#<a [^>]*href="([^"]+)" [^>]*class="[^"]*\b(urlextern)\b[^"]*"#',$doc,$ums,PREG_SET_ORDER|PREG_OFFSET_CAPTURE);
	if(!$ums) return;

	if($this->helper->getusecache()){
			$expirytime = $this->helper->getcacheexpirytime();
			$otherurlmap=array_flip($otherurls);

			for($j=sizeof($ums)-1; $j>=0; $j--){
					$m=$ums[$j];
					$url=$m[1][0];
					$r=&$urlmap[$url];

					#up-to-date urls are written javascript:LINKCHECKDATA variable. in action.php: ontplmetaheaderoutput(). 
					#We handle them in javascript and update their class accordingly. no need to inject a class here.
					#CAVEAT: We get lastcheck/codegroup information during parse time, so we may not have the latest information here. For such cases, the link will be ajax-check in javascript. No big deal. If the page is edited/rendered again, the latest information will be available.
					if(isset($r)&&$r['lastcheck']>=$expirytime){
							#msg("Skipping [ $url ], b/c it will be included in javascript variable LINKCHECKDATA ...");
					}
					#for others, inject the linkcheck class to trigger an ajax call in javascript.
					elseif(isset($otherurlmap[$url])){
							$doc=substr($doc,0,$m[2][1])."linkcheck ".substr($doc,$m[2][1]);
					}
			}
	}
	#when cache is not being used, we inject the linkcheck class to trigger an ajax call in javascript for all links.
	else{
		#$doc=preg_replace('#(<a [^>]*href="[^"]+" [^>]*class="[^"]*)(\burlextern\b[^"]*")#','$1linkcheck $2',$doc);
		#only inject for the urls that are in lincheck:on blocks.
		for($j=sizeof($ums)-1; $j>=0; $j--){
			$m=$ums[$j];
			$url=$m[1][0];
			if(isset($otherurlmap[$url])) $doc=substr($doc,0,$m[2][1])."linkcheck ".substr($doc,$m[2][1]);
		}		
	}
}

	#--------------------------------------------------------------
	function render($mode, Doku_Renderer $R, $data) {
		static $currentstate;
		if(!isset($currentstate)) $currentstate=$this->getConf('enabledbydefault');
		list($state,$data)=$data;
		
		#these is the $urlmap injected by the action.php: onparserhandlerdone() function.
		#this also marks the end of a page, so we go back and modify the externurl links in R->doc.
		if(is_array($data)){
			$id=$data[0]; #this would not be the same as the global $ID when this is a rendering of an include'd page.
			$urlmap=$data[1];
			$otherurls=$data[2];
			if($mode=='metadata'){
				#we may have multiple wiki pages being processed (sidebar, mainpage, etc.)
				if(!isset($R->meta['linkcheck_urlmap'])) $R->meta['linkcheck_urlmap']=$urlmap;
				else $R->meta['linkcheck_urlmap']=array_merge($R->meta['linkcheck_urlmap'],$urlmap);
			}
			elseif($mode=='xhtml'){
				$this->injectclassforlinks($R->doc,$urlmap,$otherurls);
				#restore the default state
				$currentstate=$this->getConf('enabledbydefault');
			}
		}
		elseif($mode=='xhtml'){
			if($data=='on'||$data=='off'){
				$currentstate=$data=='on';
			}
			else{
				$R->info['cache'] = FALSE; #otherwise msg() will not work after the first call.
				msg("Invalid linkcheck state: [".htmlspecialchars($data)."]. Must be 'on' or 'off'.");
				$currentstate=$this->getConf('enabledbydefault');
			}
		}
		return false;
	}

	#parse task, args, and options.
	function handle($match, $state, $pos, Doku_Handler $handler)
	{
		preg_match('#{{linkcheck>([^}]+)}}#',$match,$m);
		$data=trim($m[1]);
		return [$state,$data];
	}
}
