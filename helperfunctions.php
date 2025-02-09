<?php
#ensure numeric timeout. convert e.g., '1 week' to #seconds.
function expiry_ensurenum($expiry){
    if(!$expiry) return 0;
    if(!is_numeric($expiry)) {
        $expiry_=$expiry; #backup for error message.
        #strtotime requires a space betwen the number and the unit.
        $expiry=preg_replace("/^([0-9]+)([a-zA-Z])/",'$1 $2',$expiry);
        if(($expiry = strtotime("+$expiry",0))===false){
            throw new Exception("Invalid timeout: [ $expiry_ ]");
        }
    }
    return $expiry;
}
function expiry_totime($expiry,$expirytime=null){
    if(isset($expirytime)) return $expirytime;
    $expiry=expiry_ensurenum($expiry);
    if(!$expiry) return 0;
    return time()-$expiry;
}
#return status or [status,errormsg]
function linkcheck_checkurl_curl($url,$o=[]) {
    $o=array_merge([
        'verifypeer'=>true,
        'verifypeername'=>NULL, #defaults to same as verifypeer.
        'cacertfile'=>NULL,
		'dbg'=>false,
        'nobody'=>true,
    ],$o);

    $ch = curl_init($url);
    if($o['cacertfile']) curl_setopt($ch, CURLOPT_CAINFO, $o['cacertfile']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $o['verifypeer']?1:0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, ($o['verifypeername']??$o['verifypeer'])?2:0);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
    curl_setopt($ch,CURLOPT_MAXREDIRS, 5); 
    curl_setopt($ch,CURLOPT_TIMEOUT,10);
    curl_setopt($ch,CURLOPT_NOBODY, $o['nobody']?1:0);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET'); #without this kaggle and amazon return 404 (maybe they don't like HEAD method that curl might be setting with NOBODY option.
    #curl_setopt($ch, CURLOPT_HTTPGET, 1);  #this turns off NOBODY option (and curl ends up getting the body. CUSTOMREQUEST is better; it doesn't turn off NOBODY.)
    $output = curl_exec($ch);
    if($o['dbg']){
        echo "Got output:\n".(php_sapi_name() === 'cli'?$output:htmlspecialchars($output))."\n";
    }
    $curl_errno = curl_errno($ch);
    if($curl_errno) {
        if($curl_errno == 28) {
            $code = 408;
        }
        else if($curl_errno == 60) {
            $code = 495;
        }
        else if($curl_errno<100) $code=$curl_errno;
        else $code=500;
        $msg=curl_error($ch);
        $ret=[$code,$msg];
    }
    else{
    $ret = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    } 
    curl_close($ch);
    return $ret;
}
function linkcheck_checkurl_stream($url,$o=[]) {
    $o=array_merge([
        'verifypeer'=>true,
        'verifypeername'=>null, #defaults to same as verifypeer.
        'cacertfile'=>NULL,
    ],$o);

    $ossl=['verify_peer'=>$o['verifypeer'],'verify_peer_name'=> $o['verifypeername']??$o['verifypeer']];
    if($o['cacertfile']) $ossl['cafile']=$o['cacertfile'];
    $context=['ssl'=>$ossl,'http'=>['method'=>'HEAD']];
    $s=@file_get_contents($url, false, stream_context_create($context),0,0); #getting a length of zero for efficiency; it results in empty string on successful request.
    if($s!==false){ return 200; }
    else return 404;
}


#returns the http code for a url.
function linkcheck_checkurl($url,$o=[]) {
    $o=array_merge([
        'verifypeer'=>true,
        'cacertfile'=>NULL,
        'autodownloadcacertfile'=>true, #if cacertfile is specified but doesn't exist (or is out of date), download it.
        'cacertfileexpiry'=>'1 month', #The revisions on https://curl.se/docs/caextract.html are 1-2months apart.
        'cacertfileexpirytime'=>null, #alternative to cacertfileexpiry time length, provide a timepoint.
        'dbg'=>false,
    ],$o);
    if(!$o['verifypeer']) $o['cacertfile']=NULL; #if verifypeer is off, we don't need a cacertfile.
    #autodownload cacertfile.
    if($o['cacertfile'] && $o['autodownloadcacertfile']&&(!is_file($o['cacertfile'])||filemtime($o['cacertfile'])<expiry_totime($o['cacertfileexpiry'],$o['cacertfileexpirytime']))){
        if($o['dbg']) echo "<li> Downloading cacert.pem ...\n";
        if(!($s=file_get_contents("https://curl.haxx.se/ca/cacert.pem"))){
            throw new Exception("Failed to download cacert.pem.");
        }
        if(!is_dir(dirname($o['cacertfile']))) mkdir(dirname($o['cacertfile']),0755);
        file_put_contents($o['cacertfile'],$s);
    }

    if(function_exists('curl_init')) return linkcheck_checkurl_curl($url,$o);
    else return linkcheck_checkurl_stream($url,$o);
}

#get db connection. create cache table if it doesn't exist.
#codegroup and lastcheckdate are redundant but make it easier to look at the db contents.
function linkcheck_db($dbfile){
    if(!is_string($dbfile)) return $dbfile;
    if(!file_exists($dbfile)) {
        if(!is_dir(dirname($dbfile))) mkdir(dirname($dbfile),0755);
        $db = new SQLite3($dbfile);
        $db->exec("CREATE TABLE IF NOT EXISTS linkcheck_cache (url TEXT PRIMARY KEY, codegroup TEXT, code INTEGER, msg TEXT, lastcheck INTEGER, lastcheckdate DATETIME, pages TEXT)");
    }
    else $db = new SQLite3($dbfile);
    return $db;
}
function linkcheck_db_query($db,$query){
    $rows=[];
    $res = $db->query($query);
    while($row=$res->fetchArray(SQLITE3_ASSOC)){
        $rows[]=$row;
    }
    return $rows;
}

#maps to one of valid, invalid, error.
function linkcheck_code2group($code){
    if($code>=200 && $code<=399) return 'valid';
    elseif($code>=400&&$code<=499) return 'invalid';
    else return 'error';
}
function linkcheck_code2msg($code){
    $msgs=[400=>'Bad Request',401=>'Unauthorized',402=>'Payment Required',403=>'Forbidden',404=>'Not Found',405=>'Method Not Allowed',406=>'Not Acceptable',407=>'Proxy Authentication Required (RFC 7235)',408=>'Request Timeout',495=>'SSL Certificate Error'];
    return $msgs[$code]??'';
}

function linkcheck_checkurl_withcache($url,$o=[]) {
    $o=array_merge([
        'dbfile'=>NULL,
        'cacheexpiry'=>604800, #when not given, defaults to 604800, #'1 week',
        'cacheexpirytime'=>null, #alternative to cacheexpiry time length, provide a timepoint.
        'requireexists'=>true, #whether to restrict checks to the urls that have previously been inserted into the database.
    ],$o);
    if(!$o['dbfile']) return linkcheck_checkurl($url,$o);

    $db=linkcheck_db($o['dbfile']);
    $url_=$db->escapeString($url);
    $row=$db->querySingle("SELECT code,msg,lastcheck FROM linkcheck_cache WHERE url='$url_'", true);

    if($o['requireexists']&&!$row) return [500,"url [ $url ] not in database"];
    if(!isset($o['cacheexpirytime'])) $o['cacheexpirytime']=expiry_totime($o['cacheexpiry']);

    if(!$row || $row['lastcheck']<$o['cacheexpirytime']){
        if(!$row) $row=['url'=>$url_];
        $r=linkcheck_checkurl($url,$o);
        $row['code']=(is_array($r)?$r[0]:$r)+0; #ensures integer
        $row['codegroup']=linkcheck_code2group($row['code']);
        
        if(is_array($r)){ $row['msg']=$r[1]; $msg_=$db->escapeString($row['msg']); }
        else{ $msg_=$row['msg']=''; }
        
        $row['lastcheck']=time();
        $row['lastcheckdate']=date('Y-m-d H:i:s',$row['lastcheck']);
        if(!$o['requireexists'])
            $db->exec("INSERT OR IGNORE INTO linkcheck_cache(url) VALUES ('$url_')");
        $db->exec("UPDATE linkcheck_cache SET codegroup='$row[codegroup]', code=$row[code],msg='$msg_',lastcheck=$row[lastcheck],lastcheckdate='$row[lastcheckdate]' WHERE url='$url_'");
    }
    return $row['msg'] ? [$row['code'],$row['msg']] : $row['code'];
}



#just some testing in the command line...
/*
if(php_sapi_name() === 'cli' && getenv('AHMETLIBPHP')){
    require_once getenv('AHMETLIBPHP').'/ahmet.php';
    #ve(linkcheck_checkurl_withcache('https://localhost/',['cacertfile'=>'C:/downloads/cacert.pem','verifypeer'=>false,'dbg'=>true,'dbfile'=>'C:/downloads/linkcheck_cache.sqlite','requireexists'=>false]));
    #oxford doesn't like programmatic access. it returns 403. nothing we can do.
    #ve(linkcheck_checkurl('https://academic.oup.com/bioinformatics/article/24/24/2872/196843',['dbg'=>1,'nobody'=>0]));
}
*/
