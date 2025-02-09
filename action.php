<?php
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once DOKU_PLUGIN.'action.php';
require_once __DIR__.'/helperfunctions.php';

class action_plugin_linkcheck extends DokuWiki_Action_Plugin {
protected $helper;
public function __construct()
{
    $this->helper = $this->loadHelper('linkcheck', false);
}


  function getInfo(){ return conf_loadfile(dirname(__FILE__).'/plugin.info.txt'); }
  function register($contr){
    $contr->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'onajaxcallunknown');
    $contr->register_hook('PARSER_HANDLER_DONE', 'BEFORE', $this, 'onparserhandlerdone');
    $contr->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'ontplmetaheaderoutput');

    global $JSINFO;
    if($this->getConf('jqueryselector')){
        $JSINFO['linkcheck_selector']=$this->getConf('jqueryselector');
    }
}


function onajaxcallunknown(Doku_Event $ev, $param) {
    if($ev->data != 'linkcheck') return;
    $url=$_REQUEST['url'];
    if(!$url){
        $r=['code'=>500,'codegroup'=>'error','msg'=>'url parameter missing'];
         echo json_encode($r);
         $ev->preventDefault();
         return;
    }
    $code=$this->helper->checkurl($url,$o);

    $r=['code'=>is_array($code)?$code[0]:$code];
    if(is_array($code)) $r['msg']=$code[1];
    else $r['msg']=linkcheck_code2msg($r['code']);
    $r['codegroup']=linkcheck_code2group($r['code']);
    echo json_encode($r);
    $ev->preventDefault();
}
#collect links and store them in db. Inject a plugin linkcheck entry to the parser calls to trigger it to use the syntax.php handler, which will update the page meta data.
function onparserhandlerdone(Doku_Event $ev, $param) {
    $currentstate=$this->getConf('enabledbydefault');
    $urls=[];
    foreach($ev->data->calls as $i=>$e){
        #ve($e);
        if($e[0]=='plugin' && $e[1][0]=='linkcheck'){
            $newstate=$e[1][1][1];
            if($newstate=='on' || $newstate=='off'){
                $currentstate=$newstate=='on';
            }
            #else ignore the new state.
        }
        elseif($e[0]=='externallink' && $currentstate){
            $urls[]=$e[1][0];
        }
    }
    global $ID;
    $urlmap=$this->helper->getusecache()?$this->helper->getcachedata($ID,$urls):[];
    if($urls){
        #in the syntax handler, global $ID is no longer true for included pages. so, we need to save the ID within the parser entries.
        $e=['plugin',['linkcheck',[5,[$ID,$urlmap,array_diff($urls,array_keys($urlmap))]],5,''],3]; #copied the structure by examining the $e variable for plugin linkcheck above.
        #ve($e);
        $ev->data->calls[]=$e;
    }
}
function ontplmetaheaderoutput(Doku_Event $ev, $param) {
    global $INFO;
    $urlmap=$INFO['meta']['linkcheck_urlmap']??[];
    if(!$urlmap) return;
    #in javascript, we'll only need the url=>codegroup mapping for up to date entries.
    $expirytime = $this->helper->getcacheexpirytime();
    $urlmap=array_filter($urlmap,function($r) use($expirytime){return $r['lastcheck']>=$expirytime;});    
    foreach($urlmap as $url=>&$r){
       unset($r['lastcheck']);
       unset($r['pages']);
       $r=$r['codegroup'];
    }unset($r);

    if(!$urlmap) return;
    $s="var LINKCHECKDATA=".json_encode($urlmap).";\n";
    #$s.="console.log(LINKCHECKDATA);";
    
    $ev->data['script'][] = array(
        'type'    => 'text/javascript',
     // 'charset' => 'utf-8',
        '_data'   => $s,
    );
}


}
