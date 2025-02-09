<?php

use dokuwiki\Extension\AuthPlugin;
use dokuwiki\HTTP\DokuHTTPClient;
use dokuwiki\Extension\Event;
require_once __DIR__.'/helperfunctions.php';


class helper_plugin_linkcheck extends Dokuwiki_Plugin
{
    private $dbfile=null; #use getdbfile() to access this.
    private $db=null;  #use getdb() to access this.
    private $cacheexpirytime=null; #use getcacheexpirytime() to access this.
    private $cacertfile=null; #use getcacertfile(). a file is only set if the autodownloadcacert is ON.
    private $cacertfileexpirytime=null; #use getcacertfileexpirytime() to access this.
    private $usecache=null; #use usecache() to access this.
    
    public function __construct(){
        $this->usecache = $this->getConf('usecache');
        if($this->usecache && !class_exists('SQLite3',false)){
            msg('linkcheck:usecache option is enabled but SQLite3 is not available. Disabling cache...');
            $this->usecache=false;
        }
    }
    function getusecache(){
        return $this->usecache;
    }
    function getdbfile(){
        if(!isset($this->dbfile)){
            if($this->getusecache())  $this->dbfile = metaFN('linkcheck_cache','.sqlite');
            else $this->dbfile=false;
        }
        return $this->dbfile;
    }
    function getcacertfile(){
        if(!isset($this->cacertfile)){
            if($this->getConf('autodownloadcacert'))  $this->cacertfile = metaFN('linkcheck_cacert','.pem');
            else $this->cacertfile=false;
        }
        return $this->cacertfile;
    }
    function getcacertfileexpirytime(){
        if(!isset($this->cacertfileexpirytime)){
            $this->cacertfileexpirytime=expiry_totime($this->getConf('cacertfileexpiry'));
        }
        return $this->cacertfileexpirytime;
    }
    
    function &getdb(){
        if(!isset($this->db)){
            if($file=$this->getdbfile()) $this->db=linkcheck_db($file);
            else $this->db=false;
        }
        return $this->db;
    }
    function getcacheexpirytime(){
        if(!isset($this->cacheexpirytime)){
            $this->cacheexpirytime=expiry_totime($this->getConf('cacheexpiry'));
        }
        return $this->cacheexpirytime;
    }
    function checkurl($url){
        if($this->getusecache()){
            $o=[
                'dbfile'=>$this->getdbfile(),
                'cacheexpirytime'=>$this->getcacheexpirytime(),
                'requireexists'=>$this->getConf('requireexists'),
                'cacertfile'=>$this->getcacertfile(),
                'cacertfileexpirytime'=>$this->getcacertfileexpirytime(),
            ];
            return linkcheck_checkurl_withcache($url,$o);    
        }
        else{
            $o=[
                'cacertfile'=>$this->getcacertfile(),
                'cacertfileexpirytime'=>$this->getcacertfileexpirytime(),
            ];
            return linkcheck_checkurl($url,$o);    

        }
    }
    function getcachedata($id,$urls){
        $db=&$this->getdb();
        $urlmap=[];
    
        #first query: more efficient: get all urls with pages=$id
        $id_=$db->escapeString($id);
        $query="SELECT url,codegroup,lastcheck FROM linkcheck_cache WHERE pages='$id_' OR pages LIKE '$id_,%' OR pages LIKE '%,$id_' OR pages LIKE '%,$id_,%'";
        foreach(linkcheck_db_query($db,$query) as $row){
            $url=$row['url']; unset($row['url']);
            $urlmap[$url]=$row;
        }

        #delete any pageurls that no longer appear for $id
        if($deleteurls = array_diff(array_keys($urlmap),$urls)){
            #msg("Deleting urls: ".implode(',',$deleteurls));
            $urls_=array_map([$db,'escapeString'],$deleteurls);
            $urls_=array_map(function($url){return "'$url'";},$urls_);
            #delete the urls that are only for this page.
            $query="DELETE FROM linkcheck_cache WHERE pages='$id_' AND url IN (".implode(',',$urls_).")";
            $db->exec($query);

            #for urls that are shared with other pages, just empty the pages value (for efficiency, we won't try to individually remove id from each of these rows).
            $query="UPDATE linkcheck_cache SET pages='' WHERE url IN (".implode(',',$urls_).")";
            $db->exec($query);
        }
    
        #second query: get remaining urls that have previously not been registered for this $id.
        if($newurls=array_diff($urls,array_keys($urlmap))){
            $urls_=array_map([$db,'escapeString'],$newurls);
            $urls_=array_map(function($url){return "'$url'";},$urls_);
            $query="SELECT url,codegroup,lastcheck,pages FROM linkcheck_cache WHERE url IN (".implode(',',$urls_).")";
            foreach(linkcheck_db_query($db,$query) as $row){
                $url=$row['url']; unset($row['url']);
                $urlmap[$url]=$row;
            }
        }
    
        if(!$newurls){
            #msg("No new urls to insert or update.");
            return $urlmap;
        }
        #insert urls that are not in the cache. update pages if the current $id not in pages column.
        #insert should be enough, but to account for simultaneous requests, we keep "ignore into"
        $insertstmt = $db->prepare("INSERT OR IGNORE INTO linkcheck_cache(url,pages) VALUES (:url,:pages)");
        $updatestmt = $db->prepare("UPDATE linkcheck_cache SET pages=:pages WHERE url=:url");
        foreach($newurls as $url){
            if(!isset($urlmap[$url])){
                #msg("Inserting new url [ $url ] for page [ $id ]...");
                $insertstmt->bindValue(':url', $url, SQLITE3_TEXT);
                $insertstmt->bindValue(':pages',$id, SQLITE3_TEXT);
                $insertstmt->execute();
            }
            #elseif($urlmap[$url]['pages']!=$id && !in_array($id,explode(',',$urlmap[$url]['pages']))){
            else{
                #msg("Registering existing url [ $url ] for page [ $id ]...");
                $updatestmt->bindValue(':url', $url, SQLITE3_TEXT);
                $updatestmt->bindValue(':pages', $urlmap[$url]['pages'].','.$id, SQLITE3_TEXT);
                $updatestmt->execute();
            }
        }
        return $urlmap;
    }    
}
