<?php
/* released under the WTFPL ( http://www.wtfpl.net/about/ )  */
error_reporting(E_ALL);
$scriptName='MyAnimeList PHP CSS generator.. version: 0.1-dev mtime(1360692382770)';
set_time_limit(120);
ini_set('display_errors','On');
ini_set('memory_limit','200M');//TODO: debug memory issues
//in an animelist with 352 entries, peak memory usage was 32821776 bytes (~31 megabytes)
$max_curl_multi_connections=20;//TODO: hardcoded max connections?
/*this could be done a lot nicer, more robust, and faster,
if MyAnimeList.net could provide list, image, name,  about-page url, etc, via an API... 
*hint to admins* :p 
if they did, i wouldn't even need PHP, just plain old HTML+Javascript+Ajax /XMLHttpRequest would suffice
*/
validate_env();
function hhb_exception_handler($ex) {
$outputAsHTML=true;
$esc=function($input) use ($outputAsHTML)
{
return ($outputAsHTML? ('<div style="background-color: #'.substr(str_repeat(dechex(rand(1337,0xFFFFFE)),10),0,6).';" >'.htmlentities($input).'</div>') : $input);
};

$str='Uncaught exception: ';
$str.=$esc($ex->getMessage());
$str.="\n".'in file "'.$esc($ex->getFile()).'"';
$str.=' on line '.$esc($ex->getLine());
$str.="\n".'errorCode: '.$esc(return_var_dump($ex->getCode()));//
$str.="\n".'stack-trace: '."\n".$esc($ex->getTraceAsString());
$str.="\n\n\n".'$GLOBALS : '.$esc(return_var_dump($GLOBALS));
if($outputAsHTML){$str=nl2br($str);};
echo $str;
die();
}
set_exception_handler('hhb_exception_handler');


if(isset($_POST['AnimeOrManga'])){
generate_css();
die();
}
?>
<!DOCTYPE HTML>
<html><head><title><?php echo htmlentities($scriptName);?></title></head>
<body>
<form action="<?php echo htmlentities($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="Javascript:onsubmit_f(this);">
<input type="text" name="MyAnimeListAccount"> MyAnimeList account<br>
<select name="AnimeOrManga">
<option selected="selected" value="anime">Anime List</option>
<option value="manga">Manga List</option>
</select>
<input id="submitButton" type="submit" value="submit"><br/>
<input type="checkbox" name="use_curl_multi" checked="checked"> use curl_multi speed optimizations.. (try turning this off if you get errors...) </input>
<br/>Format (variables: "%title%" "%id%" "%url%" "%img_url%" ) <br/>
<textarea id="format" name="format" rows="4" style="width:100%;height:100%;">
/* %title% ( %url% ) */
#more%id% {background-image: url('%img_url%');}

</textarea>
</form>
<ul id="oldNamesList">
<!-- this will be filled with javascript-->
</ul>
<br/>

<script type="text/javascript">
function onsubmit_f(e)
{
var button=document.getElementById("submitButton");
//button.setAttribute("disabled","disabled");
button.value="loading... please wait, this can take a while...";
//button.disabled="disabled"
if(typeof(localStorage)==='undefined'){return;};
var list=JSON.parse(localStorage.getItem('MyAnimeListCSSoldNamesArray') || '[]');
var name=document.getElementsByName("MyAnimeListAccount")[0].value;
//alert(name);
if(list.indexOf(name)===-1)
{
list.push(name);
localStorage.setItem('MyAnimeListCSSoldNamesArray',JSON.stringify(list));
}
}
(function f(){if(document.readyState!=='complete'){setTimeout(f,100); return;};
if(typeof(localStorage)==='undefined'){return;}


var list=document.getElementById("oldNamesList");
var oldNames=JSON.parse(localStorage.getItem('MyAnimeListCSSoldNamesArray') || '[]');
var addDefaults=function(oldNames,input)
{
var i=0;
for(i=0;i<input.length;i++)
{
if(oldNames.indexOf(input[i])===-1){
oldNames.push(input[i]);
}
}
};
addDefaults(oldNames,["hanshenrik","NagisaDango","forks"]);
var i=0;
var oldNameClicked=function(ev){
//alert("oldNameClicked!");
document.getElementsByName('MyAnimeListAccount')[0].value=ev.target.textContent;
document.getElementsByName('MyAnimeListAccount')[0].setAttribute("value",ev.target.textContent);

};
for(i=0;i<oldNames.length;i++)
{
try{
var li=document.createElement('li');
li.appendChild(document.createElement("a"));
li.firstChild.textContent=oldNames[i];
li.firstChild.href="Javascript:void(0);";
//li.firstChild.setAttribute("style","border-width: 10px; border-color: blue;");
li.addEventListener("click",oldNameClicked);
//alert(li.click);
list.appendChild(li);
}catch(e){console.log(e);};
};
})();
</script>
<br/>
source code of this PHP script:<br/>
<div id="sourcecode" style="border-color: black; border: outset; background-color: #D0D0D0;">
<?php
highlight_file($_SERVER["SCRIPT_FILENAME"],false);
?>
</div>
</body>
</html>
<?php
function return_var_dump(){//because var_export($var,true) is bugged.
$args=func_get_args();
ob_start();
call_user_func_array('var_dump',$args);
return ob_get_clean();
}

function generate_css()
{
if(empty($_POST['MyAnimeListAccount'])){throw new Exception('$_POST["MyAnimeListAccount"] is empty!');};
if(empty($_POST['format'])){throw new Exception('$_POST["MyAnimeListAccount"] is empty!');};
if(empty($_POST['AnimeOrManga']) || ($_POST['AnimeOrManga']!=="anime" && $_POST['AnimeOrManga']!=="manga") ){throw new Exception('$_POST["AnimeOrManga"] has an unknown value! ... its '.return_var_dump($_POST['AnimeOrManga']));};
$account=$_POST['MyAnimeListAccount'];
$list=($_POST['AnimeOrManga'] =="anime"? "animelist":"mangalist");
$curl=hhb_curl_init();
$listUrl='http://myanimelist.net/'.$list.'/'.$account;
curl_setopt($curl,CURLOPT_URL,$listUrl);
$html=curl_exec($curl);
if(curl_errno($curl))
{
throw new Exception('got following error when trying to load the '.$list.'!!: '.curl_errno($curl).': '.curl_error($curl). ' (wrong username perhaps?)');
}
$document=new DOMDocument();
if(!@$document->loadHTML($html))
{
throw new UnexpectedValueException('i could not load the HTML to a PHP DOMDocument! :(  ... $html: '.return_var_dump($html));
}
$elements=$document->getElementsByTagName("div");
$element_matches=array();
$id_matches=array();
$id_matches_numbers=array();
$tmp='';
$resultList=array();
$id=0;
foreach($elements as $ele)
{
// preg_match('/^more(\d+)$/i',$tmp=$ele->getAttribute("id"),$num); $num=$num[1];
if(preg_match('/^more(\d+)$/i',$tmp=$ele->getAttribute("id"),$id)){
if(true/*TODO: duplicate check*/){
$id=$id[1];
$name="unknown/error getting name";//defaults to error.
try{
$name=$ele->previousSibling->previousSibling->getElementsByTagName("span")->item(0)->textContent;
}catch(Exception $ex){
$name="error getting name! (maybe myanimelist changed layout?): ".$ex->getMessage();
}
$url="unknown/error getting url";//defaults to error.
try{
$url=$ele->previousSibling->previousSibling->getElementsByTagName("span")->item(0)->parentNode->getAttribute('href');
}catch(Exception $ex){
$url="error getting url! (maybe myanimelist changed layout?): ".$ex->getMessage();
}
/*quote: Format (variables: "%title%" "%id%" "%url%" "%img_url%" ) <br/>*/
$resultList[$id]=array(
'id'=>$id,
'name'=>$name,
'title'=>$name,
'url'=>$url,
'img_url'=>'???',
);
$resultList[$id]['title']&=$resultList[$id]['name'];
}
}
}
unset($elements,$ele,$tmp);

if(count($resultList,COUNT_NORMAL)===0){
throw new UnexpectedValueException('could not find enteries in '.$list.' ! :( (wrong username perhaps?)');
}
if(!isset($_POST['use_curl_multi']))
{
get_imageUrls($resultList,($list==="animelist" ? 'anime':'manga'));
}else{
queueManaged_multi_get_imageUrls($resultList,($list==="animelist" ? 'anime':'manga'),
$GLOBALS['max_curl_multi_connections']);
}
//var_dump($resultList);die(memory_get_peak_usage(). "  << peak memory usage. FAKSJGG");
unset($tmp);
$tmp='';
$str='';
foreach($resultList as $animeID=>$info)
{
$tmp=$_POST['format'];
//str_replace ( mixed $search , mixed $replace , mixed $subject [, int &$count ] )
/*quote: Format (variables: "%title%" "%id%" "%url%" "%img_url%" ) <br/>*/
/*TODO: css escape? 
$tmp=str_replace('%title%',css_escape_string($info['title']),$tmp);
$tmp=str_replace('%id%',css_escape_string($info['id']),$tmp);
$tmp=str_replace('%url%',css_escape_string($info['url']),$tmp);
$tmp=str_replace('%img_url%',css_escape_string($info['img_url']),$tmp);
*/
$tmp=str_replace('%title%',$info['title'],$tmp);
$tmp=str_replace('%id%',$info['id'],$tmp);
$tmp=str_replace('%url%',$info['url'],$tmp);
$tmp=str_replace('%img_url%',$info['img_url'],$tmp);
$str.=$tmp;
}
unset($animeID,$info);
header('Content-type: text/css');
echo $str;
}



function get_imageUrls(&$id_number_array,$anime_or_manga)
{
if(!is_array($id_number_array)){
throw new InvalidArgumentException('$id_array must be an array!');
};
if($anime_or_manga !=="anime" && $anime_or_manga !=="manga")
{
throw new InvalidArgumentException('$anime_or_manga must be either "anime" OR "manga" !');
};
$ch=hhb_curl_init();
$html='';
$url='';

foreach($id_number_array as $key=>&$val){
$url='http://myanimelist.net/'.$anime_or_manga.'/'.rawurlencode($val['id']).'/';
curl_setopt($ch,CURLOPT_URL, $url);
$html=curl_exec($ch);
if(curl_errno($ch)!==0){
throw new UnexpectedValueException('trying to load "'.$url.'", error '.curl_errno($ch).': '.curl_error($ch));
};
$val['img_url']=grabImageUrlFromHTMLfunc($html,$url,$anime_or_manga);
};

curl_close($ch);
unset($key,$val,$ch);//derp.
return true;

};

function queueManaged_multi_get_imageUrls(&$id_number_array,$anime_or_manga,$max_simultaneous_connections)
{
if(!is_array($id_number_array)){
throw new InvalidArgumentException('$id_array must be an array!');
};
if($anime_or_manga !=="anime" && $anime_or_manga !=="manga")
{
throw new InvalidArgumentException('$anime_or_manga must be either "anime" OR "manga" !');
};
if($max_simultaneous_connections!=(int)$max_simultaneous_connections || $max_simultaneous_connections<1 /*|| $connections>PHP_INT_MAX is done with the (int)*/){
throw new InvalidArgumentException('$max_simultaneous_connections must be an integer>0 and <='.PHP_INT_MAX);
}
//array array_chunk ( array $input , int $size [, bool $preserve_keys = false ] )
$queues=array_chunk($id_number_array,$max_simultaneous_connections,true);
foreach($queues as $key=>&$arr)
{
//get_imageUrls($arr,$anime_or_manga);
multi_get_imageUrls($arr,$anime_or_manga);
}
unset($key,$arr);
//var_dump($queues);die("ADFJSIKJE");
foreach($queues as $key=>$arr)
{
foreach($arr as $_key=>$_val)
{
$id_number_array[$_key]=$_val;
}
}
unset($key,$arr,$_key,$_arr);

}

function multi_get_imageUrls(&$id_number_array,$anime_or_manga)
{///TODO: ADD A QUEUE MANAGER.
//seems myanimelist.net gets angry when you open lots of connections simultaneously...
//observed with a list of ~350 animes
//My server got IP-banned! -.-
if(!is_array($id_number_array)){
throw new InvalidArgumentException('$id_array must be an array!');
};
if($anime_or_manga !=="anime" && $anime_or_manga !=="manga")
{
throw new InvalidArgumentException('$anime_or_manga must be either "anime" OR "manga" !');
};
//$ret=array();

$curls=array();
$curl_multi_handle = curl_multi_init();
foreach($id_number_array as $key=>$val)
{
curl_multi_add_handle($curl_multi_handle,
$curls[]=hhb_curl_init(array(CURLOPT_URL=>
'http://myanimelist.net/'.$anime_or_manga.'/'.rawurlencode($val['id']).'/'
))
);
}
unset($key,$val);

$active = null;
$mrc=null;
//execute the handles


do {
    $mrc = curl_multi_exec($curl_multi_handle, $active);
} while ($mrc == CURLM_CALL_MULTI_PERFORM);

while ($active && $mrc == CURLM_OK) {
    if (curl_multi_select($curl_multi_handle) != -1) {
        do {
//TODO: i don't get it.. why are we calling curl_multi_exec AGAIN? x.x
//but all the examples on the docs says to do it like this so...
            $mrc = curl_multi_exec($curl_multi_handle, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
    }
}

foreach($curls as $ch){
	$html=curl_multi_getcontent($ch);
	$url=curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);
	$val=grabImageUrlFromHTMLfunc($html,$url,$anime_or_manga);
	$key=0;
	preg_match('/(\d+)\D*$/',$url,$key);//<<hack to get the number..
	$key=$key[1];
	//$ret[$key]=$val;
	$id_number_array[$key]['img_url']=$val;
curl_multi_remove_handle($curl_multi_handle, $ch);
	unset($html,$tmp,$key,$url,$val,$ch);
}
curl_multi_close($curl_multi_handle);
unset($curl_multi_handle);



//aWYoZmFsc2Upew0KDQogICAkZnVsbF9jdXJsX211bHRpX2V4ZWM9ZnVuY3Rpb24oJG1oLCAmJGFjdGl2ZSkgew0KICAgIGRvIHsNCiAgICAgICRydiA9IGN1cmxfbXVsdGlfZXhlYygkbWgsICRhY3RpdmUpOw0KICAgIH0gd2hpbGUgKCRydiA9PSBDVVJMTV9DQUxMX01VTFRJX1BFUkZPUk0pOw0KICAgIHJldHVybiAkcnY7DQogIH07DQoNCiAgIGRvIHsgLy8gIndhaXQgZm9yIGNvbXBsZXRpb24iLWxvb3ANCiAgICBjdXJsX211bHRpX3NlbGVjdCgkY3VybF9tdWx0aV9oYW5kbGUpOyAvLyBub24tYnVzeSAoISkgd2FpdCBmb3Igc3RhdGUgY2hhbmdlDQogICAgJGZ1bGxfY3VybF9tdWx0aV9leGVjKCRjdXJsX211bHRpX2hhbmRsZSwgJGFjdGl2ZSk7IC8vIGdldCBuZXcgc3RhdGUNCiAgICB3aGlsZSAoJGluZm8gPSBjdXJsX211bHRpX2luZm9fcmVhZCgkY3VybF9tdWx0aV9oYW5kbGUpKSB7DQogICAgICAvLyBwcm9jZXNzIGNvbXBsZXRlZCByZXF1ZXN0IChlLmcuIGN1cmxfbXVsdGlfZ2V0Y29udGVudCgkaW5mb1snaGFuZGxlJ10pKQ0KCSRodG1sPWN1cmxfbXVsdGlfZ2V0Y29udGVudCgkaW5mb1snaGFuZGxlJ10pOw0KCXVuc2V0KCR0bXAsJGtleSwkdXJsKTsNCgkkdXJsPWN1cmxfZ2V0aW5mbygkaW5mb1snaGFuZGxlJ10sQ1VSTElORk9fRUZGRUNUSVZFX1VSTCk7DQoJJHZhbD1ncmFiSW1hZ2VVcmxGcm9tSFRNTGZ1bmMoJGh0bWwsJHVybCwkYW5pbWVfb3JfbWFuZ2EpOw0KCSRrZXk9MDsvLyhpbnQpc3RycmV2KChpbnQpc3Vic3RyKHN0cnJldigkdXJsKSwxKSk7Ly88PHVnbHkgaGFjayB0byBnZXQgdGhlIG51bWJlci4uIDpwDQoJcHJlZ19tYXRjaCgnLyhcZCspXEQqJC8nLCR1cmwsJGtleSk7Ly88PGxlc3MgdWdseSBoYWNrIHRvIGdldCB0aGUgbnVtYmVyLi4gYWxzbyB0aGUgcHJldmlvdXMgaGFjayBkaWRudCB3b3JrIHdpdGggbnVtYmVycyBlbmRpbmcgaW4gMCA6Tw0KCSRrZXk9JGtleVsxXTsNCgkvLyRyZXRbJGtleV09JHZhbDsNCgkkaWRfbnVtYmVyX2FycmF5WyRrZXldWydpbWdfdXJsJ109JHZhbDsNCgljdXJsX211bHRpX3JlbW92ZV9oYW5kbGUoJGN1cmxfbXVsdGlfaGFuZGxlLCRpbmZvWydoYW5kbGUnXSk7DQoJY3VybF9jbG9zZSgkaW5mb1snaGFuZGxlJ10pOw0KICAgIH0NCiAgfSB3aGlsZSAoJGFjdGl2ZSk7IA0KICBjdXJsX211bHRpX2Nsb3NlKCRjdXJsX211bHRpX2hhbmRsZSk7DQoJdW5zZXQoJGN1cmxzLCRjdXJsX211bHRpX2hhbmRsZSwkYWN0aXZlLCRodG1sLCRpbmZvKTsNCn0=
////and finally we return the urls array
//return $ret;
}




function hhb_curl_init($custom_options_array=array())
{
if(!is_array($custom_options_array)){throw new InvalidArgumentException('$custom_options_array must be an array!');};
$options_array=array(
CURLOPT_AUTOREFERER=>true,
CURLOPT_BINARYTRANSFER=>true,
CURLOPT_COOKIESESSION=>true,
CURLOPT_FOLLOWLOCATION=>true,
CURLOPT_FORBID_REUSE=>false,
CURLOPT_HTTPGET=>true,
CURLOPT_RETURNTRANSFER=>true,
CURLOPT_SSL_VERIFYPEER=>false,
CURLOPT_CONNECTTIMEOUT=>10,
CURLOPT_TIMEOUT=>11,
CURLOPT_COOKIEFILE=>tmpfile(),
//CURLOPT_REFERER=>'hanshenrik.tk',
);
//we can't use array_merge() because of how it handles integer-keys, it would/could cause corruption
foreach($custom_options_array as $key=>$val)
{
$options_array[$key]=$val;
}
unset($key,$val,$custom_options_array);
$curl=curl_init();
curl_setopt_array($curl,$options_array);
return $curl;
}


function grabImageUrlFromHTMLfunc(&$html, $aUrlToLookFor,$anime_or_manga)
{
if($anime_or_manga !=="anime" && $anime_or_manga !=="manga")
{
throw new InvalidArgumentException('$anime_or_manga must be either "anime" OR "manga" !');
};
$document=new DOMDocument();
if(!@$document->loadHTML($html))
{
throw new UnexpectedValueException('i could not load the HTML to a PHP DOMDocument! :(  ... $html: '.return_var_dump($html));
};

$xpath = new DOMXPath($document);

/*var_dump(
$xpath->evaluate('//*[@id="content"]/table/tr/td[1]/div[1]/a/img')->item(0)->getAttribute("src"),
$xpath->evaluate('//*[@id="content"]/table/tbody/tr/td[1]/div[1]/a/img'),
$xpath->evaluate('//*[@id="content"]/table/tbody/tr/td[1]/div[1]/img')
);
die();*/
$url='Unknown/error trying to load image url! (maybe myanimelist.net changed layout?)';//defaults to error..
$tmp='';
try{
$tmp=$xpath->evaluate('//*[@id="content"]/table/tr/td[1]/div[1]/a/img');//->item(0);

if(!$tmp || !$tmp->item(0) || !$tmp->item(0)->getAttribute("src")){throw new Exception("failed to find the image with xpath1..");}
$url=$tmp->item(0)->getAttribute("src");

}catch(Exception $ex){
try{
unset($tmp);
$tmp=$xpath->evaluate('//*[@id="content"]/table/tr/td[1]/div[1]/img');//->item(0);
if(!$tmp || !$tmp->item(0) || !$tmp->item(0)->getAttribute("src")){throw new Exception("failed to find the image with xpath2..");}
$url=$tmp->item(0)->getAttribute("src");
}catch(Exception $ex2){
//throw new UnexpectedValueException('Failed to find image URL!!, $html: '.$html);
/*we failed...*/
//throw $ex2;
//TOD: throw new UnexpectedValueException('failed to find the image...');
}
}

return $url;
//JGFMaXN0PSRkb2N1bWVudC0+Z2V0RWxlbWVudHNCeVRhZ05hbWUoJ2EnKTsNCiRmb3VuZEltZ1VybHM9YXJyYXkoKTsNCiRyZXQ9Jyc7DQpmb3JlYWNoKCRhTGlzdCBhcyAkYSl7DQppZihwcmVnX21hdGNoKCcvXicucHJlZ19xdW90ZSgkYVVybFRvTG9va0ZvciwnLycpLicvaScsJGEtPmdldEF0dHJpYnV0ZSgnaHJlZicpKSl7DQovL2VjaG8gJGEtPmZpcnN0Q2hpbGQtPmdldEF0dHJpYnV0ZSgic3JjIik7DQovL2VjaG8gJGEtPmZpcnN0Q2hpbGQtPnRhZ05hbWU7DQoJLy8JZWNobyBodG1sZW50aXRpZXMoJGEtPm93bmVyRG9jdW1lbnQtPnNhdmVYTUwoJGEpKTsNCmlmKCEkYS0+Zmlyc3RDaGlsZCkNCnsNCnRocm93IG5ldyBFeGNlcHRpb24oJzFFcnJvciBmaWRpbmcgdGhlIGltYWdlIHVybCEgKHRoZSBkZXRlY3RlZCBhIGRpZCBub3QgaGF2ZSBhIGZpcnN0Q2hpbGQpIG1heWJlIE15QW5pbWVMaXN0Lm5ldCBjaGFuZ2VkIGxheW91dCBvciBzb21ldGhpbmcgOi8gLi4gJGh0bWwgOiAnLnJldHVybl92YXJfZHVtcCgkaHRtbCkpOw0KfQ0KaWYoc3RyY2FzZWNtcCgkYS0+Zmlyc3RDaGlsZC0+dGFnTmFtZSwnaW1nJykhPT0wKQ0Kew0KdGhyb3cgbmV3IEV4Y2VwdGlvbignMkVycm9yIGZpZGluZyB0aGUgaW1hZ2UgdXJsISAodGhlIGRldGVjdGVkIGEtPmZpcnN0Q2hpbGQtPnRhZ05hbWUgd2FzIG5vdCBpbWcsIGl0IHdhcyAnLnJldHVybl92YXJfZHVtcCgkYS0+Zmlyc3RDaGlsZC0+dGFnTmFtZSkuJykgbWF5YmUgTXlBbmltZUxpc3QubmV0IGNoYW5nZWQgbGF5b3V0IG9yIHNvbWV0aGluZyA6LyAuLiAkaHRtbCA6ICcucmV0dXJuX3Zhcl9kdW1wKCRodG1sKSk7DQp9DQppZighJHJldD0kYS0+Zmlyc3RDaGlsZC0+Z2V0QXR0cmlidXRlKCdzcmMnKSkNCnsNCnRocm93IG5ldyBFeGNlcHRpb24oJzNFcnJvciBmaWRpbmcgdGhlIGltYWdlIHVybCEgKHRoZSBkZXRlY3RlZCBhLT5maXJzdENoaWxkLT5nZXRBdHRyaWJ1dGUoInNyYyIpIHJldHVybmVkICcucmV0dXJuX3Zhcl9kdW1wKCRyZXQpLicpIG1heWJlIE15QW5pbWVMaXN0Lm5ldCBjaGFuZ2VkIGxheW91dCBvciBzb21ldGhpbmcgOi8gLi4gJGh0bWwgOiAnLnJldHVybl92YXJfZHVtcCgkaHRtbCkpOw0KfQ0KcmV0dXJuICRyZXQ7Ly93ZSBvbmx5IGNhcmUgYWJvdXQgdGhlIGZpcnN0IHJlc3VsdC4uDQp9IGVsc2V7LyplY2hvICRhLT5nZXRBdHRyaWJ1dGUoJ2hyZWYnKS4iIGRpZCBOT1QgTUFUQ0ggIi4kYVVybFRvTG9va0Zvci4nPGJyLz4nOyovfTsNCn07DQovL2lmIHdlIHJlYWNoIHRoaXMgcGxhY2UsIHdlIGZhaWxlZCBhdCBmaW5kaW5nIHRoZSB1cmwhDQp0aHJvdyBuZXcgRXhjZXB0aW9uKCcyRXJyb3IgZmlkaW5nIHRoZSBpbWFnZSB1cmwhIG1heWJlIE15QW5pbWVMaXN0Lm5ldCBjaGFuZ2VkIGxheW91dCBvciBzb21ldGhpbmcgOi8gLi4gJGh0bWwgOiAnLnJldHVybl92YXJfZHVtcCgkaHRtbCkpOw0KdW5zZXQoJGh0bWwsJGRvY3VtZW50LCRhTGlzdCwkYSk7Ly9kZXJw

};




    //CSS escape code ripped from Zend Framework ( https://github.com/zendframework/zf2/blob/master/library/Zend/Escaper/Escaper.php )
	function css_escape_string($string)
	{
 $cssMatcher=function($matches)
  {
        $chr = $matches[0];
        if (strlen($chr) == 1) {
            $ord = ord($chr);
        } else {
            $chr = mb_convert_encoding($chr,'UTF-16BE','UTF-8');//$this->convertEncoding($chr, 'UTF-16BE', 'UTF-8');
            $ord = hexdec(bin2hex($chr));
        }
        return sprintf('\\%X ', $ord);
   };
	$originalEncoding=mb_detect_encoding($string);
	if($originalEncoding===false){$originalEncoding='UTF-8';};
        $string = mb_convert_encoding($string,'UTF-8',$originalEncoding);//$this->toUtf8($string);
//		throw new Exception('mb_convert_encoding(\''.$string.'\',\'UTF-8\',\''.$originalEncoding.'\');');
        if ($string === '' || ctype_digit($string)) {
            return $string;
        }
        $result = preg_replace_callback('/[^a-z0-9]/iSu', /*$this->*/$cssMatcher, $string);
		//var_dump($result);
        return mb_convert_encoding($result,$originalEncoding,'UTF-8');//$this->fromUtf8($result);
    }
	function validate_env()
	{
	if (!(version_compare(PHP_VERSION, '5.3.0') >= 0)) {
	die('This script needs at least PHP version 5.3.0 or higher (something to do with anonymous functions iirc ;)');
	}
	$f=function($input)
	{
	if(!is_callable($input) && !class_exists($input)){
	die('This script needs '.$input.' support. sorry. refer to http://www.google.com or irc://irc.freenode.net/##php  for more information');
	};
	};
	$f('curl_multi_init');
	$f('mb_convert_encoding');
	$f('DOMDocument');
	$f('DOMXPath');
	}

?>
