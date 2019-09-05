<?php

// PHP script by Ken True, webmaster@saratoga-weather.org
// ec-radar.php  version 1.00 - 29-Dec-2006
//   version 1.01 - 31-Dec-2006 - added realpath() to make better use of cache file specs
//                                commented out 'display rings' code for cleaner detail display
//   Version 1.02 - 06-Aug-2007 - corrected PHP delimiter at top of script (missing php)
//   Version 1.03 - 20-Aug-2007 - added switches for overlay images, mods to getting correct directory
//   Version 1.04 - 21-Feb-2008 - added support for common SITE variables 
//   Version 1.05 - 04-Aug-2008 - added support for EC Website changes
//   Version 1.06 - 26-Sep-2008 - fixes for EC Website changes for XHTML 1.0-Strict output, improved cache handling
//   Version 1.07 - 30-Apr-2009 - fixed minor bug for PHP 5+ (missing php after <? marker)
//   Version 1.08 - 30-Sep-2010 - added new debugging features and an updated image parsing logic (from Pablo Sanchez)
//   Version 1.09 - 17-Apr-2013 - changes for new EC website design, UTF-8 convert+new path method
//   Version 1.10 - 19-Sep-2013 - changes for revised EC website radar colors -default for 14-color radar
//   Version 1.11 - 18-Oct-2014 - changes for new EC website design
//   Version 1.12 - 03-Mar-2015 - changes for EC website design
//   Version 2.00 - 05-Nov-2015 - major changes: repl EC image.php overlays, support for 8 or 14 color images
//   Version 2.01 - 30-Nov-2015 - changes for revised EC website (timezone extract+use meteo.gc.ca for french)
//   Version 2.02 - 22-Feb-2017 - use cURL fetch, HTTPS to EC website, improved error handling
//   Version 2.03 - 28-Jul-2018 - update for EC website change (get latest image issue)
//   Version 2.04 - 17-Apr-2019 - added list of operating radar sites and new CASxx sites for display
//   Version 2.05 - 27-Aug-2019 - updated radar site allowed list  
//
  $Version = "V2.05 - 27-Aug-2019";
// error_reporting(E_ALL);
//
// Settings:
// --------- start of settings ----------
// you need to set the $ECURL to the radar image for your site
//
//  Go to http://weather.gc.ca/ and select your language (sorry, I don't 
//  speak French, so these instructions are all in English)
//
//  Go to http://weather.gc.ca/radar/index_e.html
//  Click on the radar circle around your city/area.
//  You should see a radar page with an url like
//     http://weather.gc.ca/radar/index_e.html?id=xxx
//  copy the three letter radar id=XXX into $siteID = 'XXX'; below
//
$siteID = 'WKR';      // set to default Site for radar (same as id=xxx on EC website)
//
$defaultLang = 'en';  // set to 'fr' for french default language
//                    // set to 'en' for english default language
//
$cacheName = 'ec-radar.txt';     // note: will be changed to -en.txt or 
//                                  -fr.txt depending on language choice and stored in $radarDir
$radarDir = './radar/';           // directory for storing radar-XXX-0.png to radar-XXX-6.png images
//                                  note: relative to document root.
//
$refetchSeconds = 300;  // look for new radar from EC every 5 minutes (300 seconds)
//                      NOTE: EC may take up to 20 minutes to publish new images    
$noRadarMinutes = 25;   // minutes to wait before declaring the radar site as 'N/O -not operational'
//
$aniSec = 1; // number of seconds between animations
//
$linkToPage = '';         // detail url to link to when map link is clicked
//                        default '' will link to this program (ec-radar.php)
$showRivers     = true;   // set to true to include rivers in display
$showRoads		= true;	  // set to true to show major roads (default)
$showRoadLabels = false;  // set to true to show road numbers/labels
$showRoadNumber = false;  // set to true to show road numbers in display
$showRadarRings = false;  // set to true to include range rings in images
$showTowns		= true;   // set to true to include major towns (default)
$showAdditTowns = true;   // set to true to include additional towns in display
$showRegionalTowns = true; // show towns on Regional and National maps
$show14Color    = true;   // set to false to show new 8-color maps, set to true for original 14-color maps
//
$charsetOutput = 'ISO-8859-1';   // default character encoding of output
// ---------- end of settings -----------
//------------------------------------------------
// overrides from Settings.php if available
global $SITE;
if (isset($SITE['ecradar'])) 	{$siteID = $SITE['ecradar'];}
if (isset($SITE['defaultlang'])) 	{$defaultLang = $SITE['defaultlang'];}
if (isset($SITE['charset']))	{$charsetOutput = strtoupper($SITE['charset']); }
// end of overrides from Settings.php if available
//
// ---------- main code -----------------
if (isset($_REQUEST['sce']) && strtolower($_REQUEST['sce']) == 'view' ) {
//--self downloader --
   $filenameReal = __FILE__;
   $download_size = filesize($filenameReal);
   header('Pragma: public');
   header('Cache-Control: private');
   header('Cache-Control: no-cache, must-revalidate');
   header("Content-type: text/plain");
   header("Accept-Ranges: bytes");
   header("Content-Length: $download_size");
   header('Connection: close');
   readfile($filenameReal);
   exit;
}
$allowedSites = array( 
  'NAT' => 'National Map',
  'PAC' => 'Pacific',
  'WUJ' => 'Aldergrove (near Vancouver)',
  'XPG' => 'Prince George',
  'XSS' => 'Silver Star Mountain (near Vernon)',
  'XSI' => 'Victoria',
  'WRN' => 'Prairies',
  'CASBE' => 'Bethune (near Regina)',
  'WHK' => 'Carvel (near Edmonton)',
  'CASFW' => 'Foxwarren (near Brandon)',
  'WHN' => 'Jimmy Lake (near Cold Lake)',
  'CASRA' => 'Radisson (near Saskatoon)',
  'XBU' => 'Schuler (near Medicine Hat)',
  'CASSR' => 'Spirit River (near Grande Prairie)',
  'XSM' => 'Strathmore (near Calgary)',
  'XWL' => 'Woodlands (near Winnipeg)',
  'ONT' => 'Ontario',
  'WBI' => 'Britt (near Sudbury)',
  'XDR' => 'Dryden',
  'CASET' => 'Exeter (near London)',
  'XFT' => 'Franktown (near Ottawa)',
  'WKR' => 'King City (near Toronto)',
  'WGJ' => 'Montreal River (near Sault Ste Marie)',
  'CASRF' => 'Smooth Rock Falls (near Timmins)',
  'XNI' => 'Superior West (near Thunder Bay)',
  'QUE' => 'Quebec',
  'WMB' => 'Lac Castor (near Saguenay)',
  'XLA' => 'Landrienne (near Rouyn-Noranda)',
  'CASBV' => 'Blainville (near Montr&#233;al)',
  'XAM' => 'Val d\'Ir&#232;ne (near Mont Joli)',
  'WVY' => 'Villeroy (near Trois-Rivi&#232;res)',
  'ERN' => 'Atlantic',
  'XNC' => 'Chipman (near Fredericton)',
  'XGO' => 'Halifax',
  'WTP' => 'Holyrood (near St. John\'s)',
  'XME' => 'Marble Mountain',
  'XMB' => 'Marion Bridge (near Sydney)',
); // end of list of allowed sites

error_reporting(E_ALL);  // uncomment to turn on full error reporting
$hasUrlFopenSet = ini_get('allow_url_fopen');
if(!$hasUrlFopenSet) {
	print "<h2>Warning: PHP does not have 'allow_url_fopen = on;' --<br/>image fetch by ec-radar.php is not possible.</h2>\n";
	print "<p>To fix, add the statement: <pre>allow_url_fopen = on;\n\n</pre>to your php.ini file to enable ec-radar.php operation.</p>\n";
	return;
}
$t = pathinfo(__FILE__);  // get our program name for the HTML comments
$Program = $t['basename'];
$Status = "<!-- ec-radar.php - $Version -->\n";
$BasePath = $t['dirname'];
//$Status .= "<!-- basepath='$BasePath' -->\n";
$printIt = true;
if(isset($_REQUEST['inc']) && strtolower($_REQUEST['inc']) == 'y' or 
  (isset($doInclude) and $doInclude)) {$doInclude = true;}
if(isset($doPrint)) { $printIt = $doPrint; }
if(! isset($doInclude)) {$doInclude = false; }

if(isset($_REQUEST['site'])) { $siteID = strtoupper($_REQUEST['site']); }
$siteID = preg_replace('|[^A-Z]+|s','',$siteID); // Make sure only alpha in siteID
if(!isset($allowedSites[$siteID])) {
	print "<p>Sorry... site id '$siteID' is not a valid EC radar site name.</p>\n";
	return;
}
if (isset($_REQUEST['cache']) && (strtolower($_REQUEST['cache']) == 'no') ) {
  $forceRefresh = true;
} else {
  $forceRefresh = false;
}

if (isset($doAutoPlay)) {
	$autoPlay = $doAutoPlay;
} elseif (isset($_REQUEST['play']) && (strtolower($_REQUEST['play']) == 'no') ) {
  $autoPlay = false;
} else {
  $autoPlay = true;
}

if (isset($_REQUEST['imgonly']) && (strtolower($_REQUEST['imgonly']) == 'y')) {
  $imageOnly = true;  // just return the latest thumbnail image after processing
  $printIt = false;   // and don't spoil the image with any other stuff
} else {
  $imageOnly = false;
}

if (isset($_REQUEST['lang'])) {
$Lang = strtolower($_REQUEST['lang']);
}
if (isset($doLang)) {$Lang = $doLang;};
if (! isset($Lang)) {$Lang = $defaultLang;};

if ($Lang == 'fr') {
  $LMode = 'f';
  $ECNAME = "Environnement Canada";
  $ECHEAD = 'Radar météo';
  $ECNO = 'N/O - Non opérationnel';
  $LNoJS = 'Pour voir l\'animation, il faut que JavaScript soit en fonction.';
  $LPlay = 'Animer - Pause';
  $LPrev = 'Image pr&#233;c&#233;dente';
  $LNext = 'Prochaine image';
} else {
  $Lang = 'en';
  $LMode = 'e';
  $ECNAME = "Environment Canada";
  $ECHEAD = 'Weather Radar';
  $ECNO = 'N/O - Non-operational';
  $LNoJS = 'Please enable JavaScript to view the animation.';
  $LPlay = 'Play - Stop';
  $LPrev = 'Previous';
  $LNext = 'Next';
}
$cacheName = preg_replace('|.txt$|',"-$Lang.txt",$cacheName);

// 
if (isset($_SERVER['DOCUMENT_ROOT'])) {
  $ROOTDIR = $_SERVER['DOCUMENT_ROOT']; 
} else { 
  $ROOTDIR = '.';
}
$TradarDir = $radarDir;
if (substr($TradarDir,0,1) == '.') {$TradarDir = substr($TradarDir,1); } //prune off '.' from './' if need be
if (substr($TradarDir,0,1) <> '/') {$TradarDir = '/' . $TradarDir; } // put on leading slash if missing

$cacheDir = $BasePath . $TradarDir;
$imageDir = $radarDir;

$Status .= "<!-- cacheDir='$cacheDir' -->\n<!-- imageDir='$imageDir' -->\n";
date_default_timezone_set( @date_default_timezone_get());
$Status .= "<!-- date default timezone='".date_default_timezone_get()."' -->\n";
// Default radar image sizes from EC
$new_width = 580;
$new_height = 480;

$thumb_width = 290;
$thumb_height = 240;

if (! preg_match('|^[WXC]|',$siteID) || ($siteID == 'WRN') ) {
// the regional summary sizes
$new_width = 573;
$new_height = 300;

$thumb_width = 290;
$thumb_height = 150;
}

if ($siteID == 'NAT') {
// the national summary size
$new_width = 600;
$new_height = 522;

$thumb_width = 300;
$thumb_height = 261;
}
if (!$linkToPage) {
  $linkToPage = $_SERVER['PHP_SELF'];
}
if (isset($_REQUEST['linkto'])) {
  $linkToPage = $_REQUEST['linkto'];
}
  
//  all settings and overrides now loaded ... begin processing

$Status .= "<!-- siteID='$siteID' -->\n";
$cacheName = preg_replace('|.txt$|',"-$siteID.txt",$cacheName);
$RawImgURL = "https://weather.gc.ca/data/radar/detailed/temp_image/$siteID/%s";
$RawOvlURL = "https://weather.gc.ca";

$compositeSites = array(
  'NAT' => 'nat',
  'PAC' => 'pyr',
  'WRN' => 'pnr',
  'ONT' => 'ont',
  'QUE' => 'que',
  'ERN' => 'ern',
);


if(isset($compositeSites[$siteID])) {
  $RawImgURL = "https://weather.gc.ca/data/radar/temp_image/COMPOSITE_$siteID/%s";
}

$ECURL = 'https://weather.gc.ca/radar/index_' . $LMode . '.html?id=' . $siteID;

if($Lang == 'fr') {
	$RawImgURL = preg_replace('|weather|i','meteo',$RawImgURL);
	$ECURL     = preg_replace('|weather|i','meteo',$ECURL);
	$Status .= "<!-- french language - using meteo.gc.ca for data -->\n";
}

$RealCacheName = $cacheDir  . $cacheName;
$tpath = realpath($RealCacheName);
if ($tpath) { 
  $Status .= "<!-- changed '$RealCacheName' \n         to: '$tpath' -->\n";
  $RealCacheName = $tpath; 
}
$reloadImages = false;  // assume we don't have to reload unless a newer image set is around

if(file_exists($RealCacheName)) {
	$lastCacheTime = filemtime($RealCacheName);
} else {
	$lastCacheTime = time();
	$forceRefresh = true;
}

$lastCacheTimeHM = gmdate("Y-m-d H:i:s",$lastCacheTime) . " UTC";
$NOWgmtHM        = gmdate("Y-m-d H:i:s",time()) . " UTC";
$diffSecs = time() - $lastCacheTime; 
$Status .= "<!-- now='$NOWgmtHM' page cached='$lastCacheTimeHM' ($diffSecs seconds ago) -->\n";	
if(isset($_GET['force']) | isset($_GET['cache'])) {$refetchSeconds = 0;}

if($diffSecs > $refetchSeconds) {$forceRefresh = true;}
$Status .= "<!-- forceRefresh=";
$Status .= $forceRefresh?'true':'false';
$Status .= " -->\n";
// refresh cached copy of page if needed
// fetch/cache code by Tom at carterlake.org
if (! $forceRefresh) {
      $Status .= "<!-- using Cached version from $cacheName -->\n";
      $site = implode('', file($RealCacheName));
	  $forceRefresh = true;
    } else {
      $Status .= "<!-- loading $cacheName from\n  '$ECURL' -->\n";
      $site = ECR_fetchUrlWithoutHanging($ECURL,false);
      $fp = fopen($RealCacheName, "w");
	  if (strlen($site) and $fp) {
        $write = fputs($fp, $site);
        fclose($fp);  
        $Status .= "<!-- loading finished. New page cache saved to $cacheName ".strlen($site)." bytes -->\n";
		$reloadImages = true;
	  } else {
        $Status .= "<!-- unable to open $cacheName for writing ".strlen($site)." bytes.. cache not saved -->\n";
		$Status .= "<!-- file: '$RealCacheName' -->\n";
		$Status .= "<!-- html loading finished -->\n";
	  }
}
  if(strlen($site) < 100) {
	  print "<p>Sorry. Incomplete file received from Environment Canada website.</p>\n";
	  print $Status;
	  return;
  }
  preg_match('|charset="{0,1}([^"]+)"{0,1}|i',$site,$matches);
  
  if (isset($matches[1])) {
    $charsetInput = strtoupper($matches[1]);
  } else {
    $charsetInput = 'UTF-8';
  }
  
 $doIconv = ($charsetInput == $charsetOutput)?false:true; // only do iconv() if sets are different
 
 $Status .= "<!-- using charsetInput='$charsetInput' charsetOutput='$charsetOutput' doIconv='$doIconv' -->\n";

// find the site name
//
   preg_match_all('|<title>(.*)</title>|',$site,$matches);
// $Status .= "<!-- matches\n" . print_r($matches,true) . " -->\n";
   
   $siteTitle = $matches[1][0];
   if($doIconv and $siteTitle) { 
     $siteTitle = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$siteTitle);
   }
   
   preg_match_all('|<h1 id="wb-cont" property="name">(.*)</h1>|',$site,$matches);
   $siteHeading = $matches[1][0];
   if($doIconv and $siteHeading) { 
     $siteHeading = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$siteHeading);
   }
  
   if(preg_match_all('|<noscript>\s+<p[^>]+>(.*)</p>\s+</noscript>>|',$site,$matches) ) {
     $siteDescr = $matches[1][0];
   } else {
     $siteDescr = '';
   }
   if($doIconv and $siteDescr) { 
     $siteDescr = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$siteDescr);
   }
// used to be       "timezone":{"id":"CDT","title":"Central Daylight Time","offset":"-5"}
// now is           "timezone":{"id":"Canada\/Eastern","title":"","offset":""}
   preg_match_all('|"timezone"\:\{"id":"([^"]+)","title":"([^"]*)","offset":"([^"]*)"\}|Uis',$site,$matches);
//	 $Status .= "<!-- timezone matches \n" . print_r($matches,true) . " -->\n";
   if (!$matches[3][0]) { // oops.. no offset. use text to calculate proper values.
   // look for latest image text description
   // <p class="text-center margin-bottom-none">PRECIPET - Rain 2015-11-29, 07:40 PM EST</p>
     preg_match('|<p class="text-center margin-bottom-none">([^<]+)</p>|is',$site,$matches);
//	 $Status .= "<!-- img tz matches\n".print_r($matches,true)." -->\n";
	 $tStr = substr($matches[1],strpos($matches[1],'2'));
	 $Status .= "<!-- latest image local = '$tStr' -->\n";
	 // tStr = '2015-11-30, 18:50 UTC' (national)
	 // tStr = '2015-11-30, 02:00 PM EST' (English)
	 // tStr = '2015-11-30, 14:00 HNE' (French)
	 $tStr = str_replace(',','',$tStr);
	 $TZ = substr($tStr,strlen($tStr)-3); // peel off the text TZ abbreviation 
	 $TZName = $TZ;
	 $tStr = substr($tStr,0,strlen($tStr)-4);
	 //$Status .= "<!-- tStr = '$tStr' -->\n";
	 //find UTC time from latest image url
	 preg_match('!<img id="animation-image".*src=".*_([\d|_]+).GIF"!Uis',$site,$matches);
	 //$Status .= "<!-- img GIF matches\n".print_r($matches,true)." -->\n";
	 $tStrUTC = $matches[1];
	 // 2015_11_30_21_30
	 //$Status .= "<!-- tStrUTC ='$tStrUTC' -->\n";
	 $t = explode('_',$tStrUTC);
	 $tStrUTC = $t[0].'-'.$t[1].'-'.$t[2].' '.$t[3].':'.$t[4];
	 $Status .= "<!-- latest image UTC   = '$tStrUTC' -->\n";
	 
	 $TZOffsetSecs = strtotime($tStr.' GMT')-strtotime($tStrUTC.' GMT');
	 $TZHrs = $TZOffsetSecs / 3600;
   } else {
	 $TZ = $matches[1][0];
	 $TZName = $matches[2][0];
	 $TZHrs = $matches[3][0];
	 $TZOffsetSecs = $TZHrs * 3600;
   }
	 $Status .= "<!-- TZ='$TZ' TZHrs='$TZHrs' TZOffsetSecs='$TZOffsetSecs' TZName='$TZName' -->\n";
   
//this is for NAT/regional maps only   
   if(preg_match_all('|<map(.*)</map>|Uis',$site,$matches)) {
//	 $Status .= "<!-- map matches \n" . print_r($matches,true) . " -->\n";
   
     $MapDef = implode('',$matches[0]);  // extract image map text from page
	 
	 $MapDef = preg_replace('|/radar/index_[ef]\.html\?id=|is',"$linkToPage?lang=$Lang&amp;site=",$MapDef);
//	 $MapDef = preg_replace('|<area ([^>]+)>|is',"<area $1>",$MapDef);
	 $MapDef = preg_replace('| xmlns:html="http://www.w3.org/Profiles/XHTML-transitional"|','',$MapDef);
	 $MapDef = preg_replace('|<map (.*) alt="[^"]+" ([^>]+)>|','<map $1 $2 >',$MapDef);
//	 $MapDef = preg_replace('|name="([^"]+)"|i','name="$1" id="$1"',$MapDef);
	 
	 preg_match_all('|\s+name="(.*)"\s*|Ui',$MapDef,$matches2);
//	 $Status .= "<!-- map matches2 \n" . print_r($matches2,true) . " -->\n";
	 $MapName = $matches2[1][0];
	 if($doIconv) { 
	   $MapName = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$MapName);
	   $MapDef = iconv($charsetInput,$charsetOutput.'//TRANSLIT',$MapDef);
	 }
  
//     $Status .= "<!-- map \n" . print_r($MapDef,true) . " -->\n";
//	 $Status .= "<!-- mapname = '$MapName' -->\n";
   } else {
     $MapDef = '';
	 $MapName = '';
   }

// V1.12 -- special find of current image as background in animation

   $mostRecentImg = '';
   if(preg_match('|src="/data/radar/temp_image//\S+/(\S+).GIF"|is',$site,$tmatch)) {
	   $mostRecentImg = $tmatch[1];
	   $Status .= "<!-- most recent='$mostRecentImg' -->\n";
   }
   
// find all the radar images available
// in two passes.. get the base and the short image list.
	$start = strpos($site, '<div class="col-lg-3 col-md-4 col-xs-5">');
	$finish = strpos($site, '</ul>',$start);
	$length = $finish-$start;
	$shortList = substr($site, $start, $length);
//  $Status .= "<!-- start=$start finish=$finish length=$length len(shortList)=".strlen($shortList)." -->\n";
//  $Status .= "<!-- shortList = '$shortList' -->\n";	
// now pick up the short list
//	$start = strpos($site, 'image-list-title">');
//	$finish = strpos($site, '</div>',$start);
//	$length = $finish-$start;
//	$shortList .= substr($site, $start, $length);
//  $Status .= "<!-- start=$start finish=$finish length=$length len(shortList)=".strlen($shortList)." -->\n";	
    $number_found = preg_match_all("!(display|base)\=\'(.*?)\'!", $shortList, $matches);
//	 $Status .= "<!-- display/base matches = $number_found matches=".print_r($matches,true)."\n MapName '$MapName' -->\n";

//
// New EC Radar HTML has logic to handle if JavaSript is disabled
// which causes the above regular expression to generate two `base'
// .GIF's.
//
// The work-around is to determine whether cell 1 is a `base', if so, we
// delete cell 0.  To plan for possible future logic, we keep
// deleting `base' entries until there's only one remaining.
//
if ($number_found > 2 && $matches[1][1] == "base") {
  for ($i = 1; $i <= $number_found; $i++) {
    // Remove the first set of `base' values.
    unset($matches[0][0]);
    unset($matches[1][0]);
    unset($matches[2][0]);
    
    // Do we end our for-loop?
    if ($i < $number_found && $matches[1][1] != "base") {
      break;
    }
  }
  
  // Reset index numbers so data sorts properly by time.
  $matches[0] = array_values($matches[0]);
  $matches[1] = array_values($matches[1]);
  $matches[2] = array_values($matches[2]);
 }

// $Status .= "<!--  finished matches\n" . print_r($matches,true) . " -->\n";

// V1.12 -- add in most recent image to array if available
	if($mostRecentImg <> '') {
	  $imglist[] = $mostRecentImg; // add to first entry
	  foreach ($matches[2] as $i => $ourImg) {
	    $imglist[] = $ourImg;
	  }
	} else {
	  $imglist = $matches[2];
	}
    $Status .= "<!--  imglist\n" . print_r($imglist,true) . " -->\n";
//	$imglistText = array();
    $total_time = 0;
	$newestRadarCacheFile = '';
	$lastRadarGMTText ='';
	$newestRadarImgHTML = '';
	$numImages = 0;
 	$NOWgmt = time();
    $NOWdate = gmdate("D, d M Y H:i:s", $NOWgmt);
	$imglistText[0] = '';
	$lastRadarGMT = 0;
	
	if($reloadImages) {
		$default_opts =  array(
	  'http'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (ec-radar.php - saratoga-weather.org)\r\n" .
				"Accept: text/html,text/plain\r\n"
	  ),
	  'ssl'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
		'verify_peer' => false,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (ec-radar.php - saratoga-weather.org)\r\n" .
				"Accept: text/html,text/plain\r\n"
	  )
		);
	  $default = stream_context_set_default($default_opts);

		// Now generate overlays in the same order as used by EC
		/* 
		/cacheable/images/radar/layers/rivers/wkr_rivers.gif
		/cacheable/images/radar/layers/roads/WKR_roads.gif
		/cacheable/images/radar/layers/road_labels/wkr_labs.gif
		/cacheable/images/radar/layers/radar_circle/radar_circle.gif
		/cacheable/images/radar/layers/additional_cities/wkr_towns.gif
		/cacheable/images/radar/layers/default_cities/wkr_towns.gif
		
		towns overlay for summary files.
		nat: /cacheable/images/radar/layers/composite_cities/nat_composite.gif
		pac: /cacheable/images/radar/layers/composite_cities/pyr_composite.gif
		wrn: /cacheable/images/radar/layers/composite_cities/pnr_composite.gif
		ont: /cacheable/images/radar/layers/composite_cities/ont_composite.gif
		que: /cacheable/images/radar/layers/composite_cities/que_composite.gif
		atl: /cacheable/images/radar/layers/composite_cities/atl_composite.gif
		
		*/
		
		$townOverlays = array(
		  'NAT' => 'nat',
		  'PAC' => 'pyr',
		  'WRN' => 'pnr',
		  'ONT' => 'ont',
		  'QUE' => 'que',
		  'ERN' => 'ern',
		);
		$imgType = $show14Color ? '14-Color':'8-Color';
		$u8c     = $show14Color ? '':'_detailed';

		
		// V2.00 -- generate the overlay directly using GD functions
		$Status .= "<!-- generating overlay(s) -->\n";
		$Status .= "<!-- image sizes are w=$new_width h=$new_height. Thumbnail is w=$thumb_width h=$thumb_height. -->\n";
		$Status .= "<!-- using $imgType radar $u8c overlay(s) -->\n"; 
		
		$overlayIMG = imagecreatetruecolor($new_width,$new_height);
		$overlayBGColor = imagecolorallocatealpha($overlayIMG,255,255,255,127); // transparency=full
		imagecolortransparent($overlayIMG,$overlayBGColor); // what the EC uses...
		imagefill($overlayIMG,0,0,$overlayBGColor); // make image background transparent
		
		// Enable blend mode and save full alpha channel
		imagealphablending($overlayIMG, true);
		imagesavealpha($overlayIMG, true);
		$didOverlay = '';
		if(isset($townOverlays[$siteID]) and $showRegionalTowns) { // summary site.. use only one overlay
			$tIMGraw = file_get_contents($RawOvlURL.
			'/cacheable/images/radar/layers'.$u8c.'/composite_cities/' . 
			  $townOverlays[$siteID] . '_composite.gif');
			$tIMG = imagecreatefromstring($tIMGraw);
			if($tIMG) {
			  displayTrans($tIMG,'rivers');
			  imagecopy($overlayIMG,$tIMG,0,0,0,0,$new_width,$new_height);
			  imagedestroy($tIMG);
			  $didOverlay .= 'Composite cities, ';
			} else {
			  print "<!-- unable to load composite towns overlay -->\n";
			}
		  
		}
		if(!isset($townOverlays[$siteID])) { // not a summary site
		
		  if ($showRivers) {
			$tIMGraw = file_get_contents($RawOvlURL.
			'/cacheable/images/radar/layers'.$u8c.'/rivers/' . strtolower($siteID) . '_rivers.gif');
			$tIMG = imagecreatefromstring($tIMGraw);
			if($tIMG) {
			  displayTrans($tIMG,'rivers');
			  imagecopy($overlayIMG,$tIMG,0,0,0,0,$new_width,$new_height);
			  imagedestroy($tIMG);
			  $didOverlay .= 'Rivers, ';
			} else {
			  print "<!-- unable to load rivers overlay -->\n";
			}
		  } 
		  if ($showRoads) {
			$tIMGraw = file_get_contents($RawOvlURL.
			'/cacheable/images/radar/layers'.$u8c.'/roads/'. $siteID . '_roads.gif');
			$tIMG = imagecreatefromstring($tIMGraw);
			if($tIMG) {
			  displayTrans($tIMG,'roads');
			  imagecopy($overlayIMG,$tIMG,0,0,0,0,$new_width,$new_height);
			  imagedestroy($tIMG);
			  $didOverlay .= 'Roads, ';
			} else {
			  print "<!-- unable to load roads overlay -->\n";
			}
		  }
		  if ($showRoadLabels) { 
			
			$tIMGraw = file_get_contents($RawOvlURL.
			'/cacheable/images/radar/layers'.$u8c.'/road_labels/' . strtolower($siteID) . '_labs.gif');
			$tIMG = imagecreatefromstring($tIMGraw);
			if($tIMG) {
			  displayTrans($tIMG,'road labels');
			  imagecopy($overlayIMG,$tIMG,0,0,0,0,$new_width,$new_height);
			  imagedestroy($tIMG);
			  $didOverlay .= 'Road Labels, ';
			} else {
			  print "<!-- unable to load road labels overlay -->\n";
			}
		  }
		  if ($showRadarRings) { 
			
			$tIMGraw = file_get_contents($RawOvlURL.
			'/cacheable/images/radar/layers'.$u8c.'/radar_circle/radar_circle.gif');
			$tIMG = imagecreatefromstring($tIMGraw);
			if($tIMG) {
			  displayTrans($tIMG,'radar circles');
			  imagecopy($overlayIMG,$tIMG,0,0,0,0,$new_width,$new_height);
			  imagedestroy($tIMG);
			  $didOverlay .= 'Radar Circles, ';
			} else {
			  print "<!-- unable to load radar rings overlay -->\n";
			}
		  }
		  if ($showAdditTowns) {
			$tIMGraw = file_get_contents($RawOvlURL.
			'/cacheable/images/radar/layers'.$u8c.'/additional_cities/' . strtolower($siteID) .'_towns.gif');
			$tIMG = imagecreatefromstring($tIMGraw);
			if($tIMG) {
			  displayTrans($tIMG,'addit. cities');
			  imagecopy($overlayIMG,$tIMG,0,0,0,0,$new_width,$new_height);
			  imagedestroy($tIMG);
			  $didOverlay .= 'Additional Cities, ';
			} else {
			  print "<!-- unable to load additional cities overlay -->\n";
			}
		   }
		  if ($showTowns) {
			
			$tIMGraw = file_get_contents($RawOvlURL.
			'/cacheable/images/radar/layers'.$u8c.'/default_cities/' . strtolower($siteID) .'_towns.gif');
			$tIMG = imagecreatefromstring($tIMGraw);
			if($tIMG) {
			  displayTrans($tIMG,'cities');
			  imagecopy($overlayIMG,$tIMG,0,0,0,0,$new_width,$new_height);
			  imagedestroy($tIMG);
			  $didOverlay .= 'Default Cities, ';
			} else {
			  print "<!-- unable to load default cities overlay -->\n";
			}
		
		  }
		} // end of overlay creation for regular radar sites
		if(strlen($didOverlay) > 1) {
			$didOverlay = substr($didOverlay,0,strlen($didOverlay)-2); 
			$Status .= "<!-- overlay(s) generated: $didOverlay -->\n";
		}
		imagepng($overlayIMG,$cacheDir.'overlay-'.$siteID.'.png'); // save it		
	}

//print "<b>now process</b> \n";
//print "<pre>\n" . print_r($imglist,true) . "</pre>\n";
// process image file list from EC radar page
	

    foreach ($imglist as $i => $ourImg ) {
      $ourImg .= '.GIF'; // have to add back the .GIF since the preg_match_all removes it.
      $radarCacheFile = "radar-$siteID-$i.png";
	  $RealRadarCacheFile = $cacheDir . $radarCacheFile;
	  $tpath = realpath($RealRadarCacheFile);
	  if ($tpath <> '') {
	     $RealRadarCacheFile = $tpath;
	  }
      $imgURL = sprintf($RawImgURL,$ourImg);
//    $Status .= "<!-- image='$ourImg' -->\n";
//	  $Status .= "<!-- cache='$radarCacheFile' -->\n";
      preg_match('|_(\d+)_(\d+)_(\d+)_(\d+)_(\d+).GIF|',$ourImg,$matches);
//         $Status .= "<!-- matches\n" . print_r($matches,true) . " -->\n";
	 $RadarGMT = gmmktime($matches[4],$matches[5],0,$matches[2],$matches[3],$matches[1]);
 	 $RadarGMTText = gmdate("D, d M Y H:i:s", $RadarGMT);
//		 $Status .= "<!-- rdr=$RadarGMTText ($RadarGMT) -->\n";


     $imglistText[$i] = gmdate("Y-m-d H:i ",$RadarGMT+$TZOffsetSecs) . $TZ;

	  if ($i == 0) { // newest image processing
		 $lastRadarGMT = $RadarGMT;
		 $lastRadarGMTText = $RadarGMTText;
		 $diff = $NOWgmt - $lastRadarGMT;
		 $Status .= "<!-- now=$NOWdate ($NOWgmt) \n     rdr=$lastRadarGMTText ($lastRadarGMT)  age=$diff secs -->\n";

	  } // end img=0 processing
	  
	  if ($reloadImages) {  // do the reload

	    $didIt = false;
        $time_start = ECR_fetch_microtime();
		
		if($show14Color) {
			$imgURL = preg_replace('|/detailed/|','/',$imgURL); // get old detailed images only
		} else {
			$imgURL = preg_replace('|/radar/temp_image/|i','/radar/detailed/temp_image/',$imgURL);
		}
		$Status .= "<!-- Loading $imgType $imgURL \n to $radarCacheFile \n dir=$cacheDir -->\n";

	    $didIt = download_file($imgURL,$cacheDir,$radarCacheFile,$overlayIMG,$new_width,$new_height);
		$time_stop = ECR_fetch_microtime();
	    $total_time += ($time_stop - $time_start);
	    $time_fetch = sprintf("%01.3f",round($time_stop - $time_start,3));

		if ($didIt) {
		  $Status .= "<!-- reloaded $radarCacheFile in $time_fetch secs. ($RadarGMTText UTC) -->\n";
		  } else {
		  $Status .= "<!-- unable to reload $radarCacheFile ($time_fetch secs.) -->\n";
		}
	  } // end if reloadImages
      $numImages++;
    } // end foreach imglist
	
if ($reloadImages) { // make thumbnail too for latest image
	$imgname = "radar-$siteID-0.png"; // get latest image name
	$thumbname = preg_replace('|\.png|','-sm.png',$imgname);
    $time_start = ECR_fetch_microtime();
	$image = imagecreatefrompng ($cacheDir . $imgname);;  // fetch our radar
	
	if (! $image ) { // oops... no existing image, create a dummy one
       $image  = imagecreate ($new_width, $new_height); /* Create a blank image */ 
       $bgc = imagecolorallocate ($image, 128, 128, 128); 
       imagefilledrectangle ($image, 0, 0, $new_width, $new_height, $bgc); 
	}
	$MaxX = imagesx($image);
	$MaxY = imagesy($image);
	$image_p = imagecreatetruecolor($thumb_width, $thumb_height);
    imagecopyresampled($image_p, $image, 0, 0, 0, 0, $thumb_width, $thumb_height, $MaxX, $MaxY);

	if (time() > ($lastRadarGMT + $noRadarMinutes*60 + $refetchSeconds + 15)) {
	  // stale radar if > 25 minutes + refetchTime + 15 seconds old
        $text_color = imagecolorallocate ($image_p, 192,51,51);
        $bgcolor = imagecolorallocate ($image, 128, 128, 128); 
		imagefilledrectangle($image_p, 5, 95, 230, 140,$bgcolor);
        imagestring ($image_p, 5, 15, 100, "$ECNO", $text_color);
        imagestring ($image_p, 5, 15, 120, $imglistText[0], $text_color);
	}
	
    imagepng($image_p, $cacheDir . $thumbname); 
    imagedestroy($image); 
    imagedestroy($image_p); 
		$time_stop = ECR_fetch_microtime();
	    $total_time += ($time_stop - $time_start);
	    $time_fetch = sprintf("%01.3f",round($time_stop - $time_start,3));
	$Status .= "<!-- small image w=$thumb_width h=$thumb_height saved to $thumbname in $time_fetch secs. -->\n";
	$Status .= "<!-- image files cached in ".sprintf("%01.3f",round($total_time))." secs. -->\n";

}

if(isset($overlayIMG)) {imagedestroy($overlayIMG);}

if ($imageOnly) {
    $ourImg = $cacheDir . "radar-$siteID-0-sm.png";
    if (file_exists($ourImg)) {
	  $ourImgSize = filesize($ourImg);
	  $ourImgGMT = filectime($ourImg);
	  header("Content-type: image/png"); // now send to browser
	  header("Content-length: " . $ourImgSize);
	  header("Last-modified: " . gmdate("D, d M Y H:i:s", $ourImgGMT) . ' GMT');
	  header("Expires: " . gmdate("D, d M Y H:i:s", $ourImgGMT+$refetchSeconds) . ' GMT');
	  readfile($ourImg);
	}
    exit;
}

// print it out:
if ($printIt && ! $doInclude) {
//------------------------------------------------
header("Cache-Control: no-cache,no-store,  must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
$NOWdate = gmdate("D, d M Y H:i:s", time());
header("Expires: $NOWdate GMT");
header("Last-Modified: $NOWdate GMT");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Refresh" content="300" />
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Cache-Control" content="no-cache" />
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title><?php print "$siteTitle"; ?></title>
<style type="text/css">
body {
 background-color: #FFFFFF;
}
.ECradar {
  font-family:Verdana, Arial, Helvetica, sans-serif;
  font-size:12px;
  color: #000000;
}
.ECradar p {
  text-align:center;
}
</style>
</head>
<body>
<?php 
}
  print $Status;
  print "<!-- autoplay=";
  print $autoPlay?'true':'false';
  print " -->\n";

if ($printIt) {
  $ECURL = preg_replace('|&|Ui','&amp;',$ECURL); // make link XHTML compatible
//  print "<!-- imglistTxt \n" . print_r($imglistText,true) . " -->\n";
//  print $newestRadarImgHTML;
  print "<div class=\"ECradar\">\n";
  gen_animation($numImages, $siteID, $radarDir,$aniSec);
//  print $imgHTML;
  print "<p><a href=\"$ECURL\">$siteHeading - $ECNAME</a></p>\n</div> <!-- end of ECradar -->\n";
}
if ($printIt && ! $doInclude) {?>
</body>
</html>
<?php
}


// ----------------------------functions ----------------------------------- 
function ECR_fetchUrlWithoutHanging($url,$useFopen) {
// get contents from one URL and return as string 
  global $Status, $needCookie;
  
  $overall_start = time();
  if (! $useFopen) {
   // Set maximum number of seconds (can have floating-point) to wait for feed before displaying page without feed
   $numberOfSeconds=6;   

// Thanks to Curly from ricksturf.com for the cURL fetch functions

  $data = '';
  $domain = parse_url($url,PHP_URL_HOST);
  $theURL = str_replace('nocache','?'.$overall_start,$url);        // add cache-buster to URL if needed
  $Status .= "<!-- curl fetching '$theURL' -->\n";
  $ch = curl_init();                                           // initialize a cURL session
  curl_setopt($ch, CURLOPT_URL, $theURL);                         // connect to provided URL
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);                 // don't verify peer certificate
  curl_setopt($ch, CURLOPT_USERAGENT, 
    'Mozilla/5.0 (ec-radar.php - saratoga-weather.org)');

  curl_setopt($ch,CURLOPT_HTTPHEADER,                          // request LD-JSON format
     array (
         "Accept: text/html,text/plain"
     ));

  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $numberOfSeconds);  //  connection timeout
  curl_setopt($ch, CURLOPT_TIMEOUT, $numberOfSeconds);         //  data timeout
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);              // return the data transfer
  curl_setopt($ch, CURLOPT_NOBODY, false);                     // set nobody
  curl_setopt($ch, CURLOPT_HEADER, true);                      // include header information
//  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);              // follow Location: redirect
//  curl_setopt($ch, CURLOPT_MAXREDIRS, 1);                      //   but only one time
  if (isset($needCookie[$domain])) {
    curl_setopt($ch, $needCookie[$domain]);                    // set the cookie for this request
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);             // and ignore prior cookies
    $Status .=  "<!-- cookie used '" . $needCookie[$domain] . "' for GET to $domain -->\n";
  }

  $data = curl_exec($ch);                                      // execute session

  if(curl_error($ch) <> '') {                                  // IF there is an error
   $Status .= "<!-- curl Error: ". curl_error($ch) ." -->\n";        //  display error notice
  }
  $cinfo = curl_getinfo($ch);                                  // get info on curl exec.
/*
curl info sample
Array
(
[url] => http://saratoga-weather.net/clientraw.txt
[content_type] => text/plain
[http_code] => 200
[header_size] => 266
[request_size] => 141
[filetime] => -1
[ssl_verify_result] => 0
[redirect_count] => 0
  [total_time] => 0.125
  [namelookup_time] => 0.016
  [connect_time] => 0.063
[pretransfer_time] => 0.063
[size_upload] => 0
[size_download] => 758
[speed_download] => 6064
[speed_upload] => 0
[download_content_length] => 758
[upload_content_length] => -1
  [starttransfer_time] => 0.125
[redirect_time] => 0
[redirect_url] =>
[primary_ip] => 74.208.149.102
[certinfo] => Array
(
)

[primary_port] => 80
[local_ip] => 192.168.1.104
[local_port] => 54156
)
*/
  $Status .= "<!-- HTTP stats: " .
    " RC=".$cinfo['http_code'] .
    " dest=".$cinfo['primary_ip'] ;
	if(isset($cinfo['primary_port'])) { 
	  $Status .= " port=".$cinfo['primary_port'] ;
	}
	if(isset($cinfo['local_ip'])) {
	  $Status .= " (from sce=" . $cinfo['local_ip'] . ")";
	}
	$Status .= 
	"\n      Times:" .
    " dns=".sprintf("%01.3f",round($cinfo['namelookup_time'],3)).
    " conn=".sprintf("%01.3f",round($cinfo['connect_time'],3)).
    " pxfer=".sprintf("%01.3f",round($cinfo['pretransfer_time'],3));
	if($cinfo['total_time'] - $cinfo['pretransfer_time'] > 0.0000) {
	  $Status .=
	  " get=". sprintf("%01.3f",round($cinfo['total_time'] - $cinfo['pretransfer_time'],3));
	}
    $Status .= " total=".sprintf("%01.3f",round($cinfo['total_time'],3)) .
    " secs -->\n";

  //$Status .= "<!-- curl info\n".print_r($cinfo,true)." -->\n";
  curl_close($ch);                                              // close the cURL session
  //$Status .= "<!-- raw data\n".$data."\n -->\n"; 
  $i = strpos($data,"\r\n\r\n");
  $headers = substr($data,0,$i);
  $content = substr($data,$i+4);
  if($cinfo['http_code'] <> '200') {
    $Status .= "<!-- headers returned:\n".$headers."\n -->\n"; 
  }
  return $data;                                                 // return headers+contents

 } else {
//   print "<!-- using file_get_contents function -->\n";
   $STRopts = array(
	  'http'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (ec-radar.php - saratoga-weather.org)\r\n" .
				"Accept: text/html,text/plain\r\n"
	  ),
	  'ssl'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
		'verify_peer' => false,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (ec-radar.php - saratoga-weather.org)\r\n" .
				"Accept: text/html,text/plain\r\n"
	  )
	);
	
   $STRcontext = stream_context_create($STRopts);

   $T_start = ECR_fetch_microtime();
   $xml = file_get_contents($url,false,$STRcontext);
   $T_close = ECR_fetch_microtime();
   $headerarray = get_headers($url,0);
   $theaders = join("\r\n",$headerarray);
   $xml = $theaders . "\r\n\r\n" . $xml;

   $ms_total = sprintf("%01.3f",round($T_close - $T_start,3)); 
   $Status .= "<!-- file_get_contents() stats: total=$ms_total secs -->\n";
   $Status .= "<-- get_headers returns\n".$theaders."\n -->\n";
//   print " file() stats: total=$ms_total secs.\n";
   $overall_end = time();
   $overall_elapsed =   $overall_end - $overall_start;
   $Status .= "<!-- fetch function elapsed= $overall_elapsed secs. -->\n"; 
//   print "fetch function elapsed= $overall_elapsed secs.\n"; 
   return($xml);
 }

}    // end ECR_fetchUrlWithoutHanging
// ------------------------------------------------------------------

function ECR_fetch_microtime()
{
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + (float)$sec);
}
// --------------------------------------------------------------------------- 

function setTransparency($new_image,$image_source) 
{ 

   $transparencyIndex = imagecolortransparent($image_source); 
   $transparencyColor = array('red' => 255, 'green' => 255, 'blue' => 255); 
	
   if ($transparencyIndex >= 0) { 
	   $transparencyColor    = imagecolorsforindex($image_source, $transparencyIndex);    
   } 
   
   $transparencyIndex    = imagecolorallocate($new_image, $transparencyColor['red'], $transparencyColor['green'], $transparencyColor['blue']); 
   imagefill($new_image, 0, 0, $transparencyIndex); 
	imagecolortransparent($new_image, $transparencyIndex); 

}
// --------------------------------------------------------------------------- 

function displayTrans($image_source,$for) {
	global $Status;
	$tI = imagecolortransparent($image_source); 
    $cI = imagecolorsforindex($image_source,$tI);          
	if ($tI >= 0) { 
      $Status .= "<!-- transparent color for $for is (".$cI['red'].','.$cI['green'].','.$cI['blue'].') alpha='.$cI['alpha']." -->\n";

	} 
}
// --------------------------------------------------------------------------- 

function download_file($file_source,$file_dir, $file_target,$overlay,$width,$height) {
  global $Status;
  // load the source gif and do the overlay, then save the resulting file V2.00
  
  $tIMG = imagecreatetruecolor($width,$height);
  // Enable blend mode and save full alpha channel
  imagealphablending($tIMG, true);
  imagesavealpha($tIMG, true);
   $STRopts = array(
	  'http'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (ec-radar.php - saratoga-weather.org)\r\n" .
				"Accept: text/html,text/plain\r\n"
	  ),
	  'ssl'=>array(
	  'method'=>"GET",
	  'protocol_version' => 1.1,
		'verify_peer' => false,
	  'header'=>"Cache-Control: no-cache, must-revalidate\r\n" .
				"Cache-control: max-age=0\r\n" .
				"Connection: close\r\n" .
				"User-agent: Mozilla/5.0 (ec-radar.php - saratoga-weather.org)\r\n" .
				"Accept: text/html,text/plain\r\n"
	  )
	);
	
   $STRcontext = stream_context_create($STRopts);

   $T_start = ECR_fetch_microtime();
   $rawIMGstring = file_get_contents($file_source,false,$STRcontext);
   $T_close = ECR_fetch_microtime();
   $headerarray = get_headers($file_source,0);

   $ms_total = sprintf("%01.3f",round($T_close - $T_start,3)); 
   $Status .= "<!-- fetch $file_source in $ms_total secs -->\n";

   $sceIMG = imagecreatefromstring($rawIMGstring);
   if(!$sceIMG) {
	  $Status .= "<!-- unable to open $file_source for read -->\n";
	  $Status .= "<!-- headers returned:\n".print_r($headerarray,true)."\n-->\n";
	  imagedestroy($tIMG);
	  return false;
  }
  imagecopy($tIMG,$sceIMG,0,0,0,0,$width,$height);
  imagecopy($tIMG,$overlay,0,0,0,0,$width,$height);
  
  if(!imagepng($tIMG,$file_dir . $file_target)) {
	  $Status .= "<!-- unable to open $file_dir$file_target for writing composite image -->\n";
	  imagedestroy($tIMG);
	  return false;
  }
  imagedestroy($tIMG);
  return true;
}

// ------------------------------------------------------------------


function gen_animation ( $numImages, $siteID, $radarDir,$aniSec) {
// generate JavaScript and control buttons for rotating the images
  global $new_width, $new_height, $siteTitle, $imglistText, $LPlay, $LPrev, $LNext, $LNoJS,
    $siteHeading, $siteDescr, $ECNO, $MapDef,$MapName, $TZ, $TZOffsecSecs, $autoPlay;
  if ($numImages < 1) {
	print "<p>Sorry, no current radar images for site $siteID are available.</p>\n";
	return;
  }
  
  if ($numImages > 1) {
	// generate the animation for 2 or more images 
?>
<script type="text/javascript">
// <!--
// clever.. we put out buttons only if JavaScript is enabled
document.write( '<p><input type="button" id="<?php echo $siteID; ?>btnPrev" value="<?php echo $LPrev; ?>" onclick="<?php echo $siteID; ?>Prev();" />' +
'<input type="button" id="<?php echo $siteID; ?>bntPlay" value="<?php echo $LPlay; ?>" onclick="<?php echo $siteID; ?>Play()" />' +
'<input type="button" id="<?php echo $siteID; ?>btnNext" value="<?php echo $LNext; ?>" onclick="<?php echo $siteID; ?>Next();" /></p>' );
// -->
</script>
<?php echo $MapDef; ?>
<p><span id="<?php echo $siteID; ?>description"><?php 
	$rT = $imglistText[0];
	$rN = $numImages;
    print "$siteHeading - $rT, $rN/$rN"; ?></span><br />
<img src="<?php echo $radarDir . "radar-$siteID-0.png"; ?>" alt="<?php echo $siteTitle; ?>" width="<?php echo $new_width; ?>" height="<?php echo $new_height; ?>" id="<?php echo $siteID; ?>_Ath_Slide" title="<?php echo $siteTitle; ?>" <?php if($MapName <> '') {echo " usemap=\"#" . $MapName . "\" "; }?>/>
<?php if ($siteDescr <> '') { print "<br /> $ECNO"; }?> </p>
<?php if ($siteDescr <> '') { print "<p>$siteDescr</p>\n"; }?>
<noscript><p><?php echo $LNoJS; ?></p></noscript>

<script type="text/javascript">
/*
Interactive Image slideshow with text description
By Christian Carlessi Salvadó (cocolinks@c.net.gt). Keep this notice intact.
Visit http://www.dynamicdrive.com for script
*/
<?php echo $siteID; ?>g_fPlayMode = 0;
<?php echo $siteID; ?>g_iimg = -1;
<?php echo $siteID; ?>g_imax = 0;
<?php echo $siteID; ?>g_ImageTable = new Array();
<?php echo $siteID; ?>g_dwTimeOutSec=<?php echo $aniSec;?>

function <?php echo $siteID; ?>ChangeImage(fFwd)
{
  if (fFwd)
   {
    if (++<?php echo $siteID; ?>g_iimg==<?php echo $siteID; ?>g_imax)
      <?php echo $siteID; ?>g_iimg=0;
   }
  else
  {
    if (<?php echo $siteID; ?>g_iimg==0)
      <?php echo $siteID; ?>g_iimg=<?php echo $siteID; ?>g_imax;
       <?php echo $siteID; ?>g_iimg--;
  }
  <?php echo $siteID; ?>Update();
}

function <?php echo $siteID; ?>getobject(obj){
  if (document.getElementById)
    return document.getElementById(obj)
  else if (document.all)
    return document.all[obj]
}

function <?php echo $siteID; ?>Update(){
  <?php echo $siteID; ?>getobject("<?php echo $siteID; ?>_Ath_Slide").src = <?php echo $siteID; ?>g_ImageTable[<?php echo $siteID; ?>g_iimg][0];
  <?php echo $siteID; ?>getobject("<?php echo $siteID; ?>description").innerHTML = '<?php echo $siteHeading; ?> - '+<?php echo $siteID; ?>g_ImageTable[<?php echo $siteID; ?>g_iimg][1];
	<?php echo $siteID; ?>OnImgLoad();
}


function <?php echo $siteID; ?>Play()
{
  <?php echo $siteID; ?>g_fPlayMode = !<?php echo $siteID; ?>g_fPlayMode;
  if (<?php echo $siteID; ?>g_fPlayMode)
   {
    <?php echo $siteID; ?>getobject("<?php echo $siteID; ?>btnPrev").disabled = <?php echo $siteID; ?>getobject("<?php echo $siteID; ?>btnNext").disabled = true;
    <?php echo $siteID; ?>Next();
   }
  else 
   {
    <?php echo $siteID; ?>getobject("<?php echo $siteID; ?>btnPrev").disabled = <?php echo $siteID; ?>getobject("<?php echo $siteID; ?>btnNext").disabled = false;
   }
}
function <?php echo $siteID; ?>OnImgLoad()
{
  if (<?php echo $siteID; ?>g_fPlayMode)
    window.setTimeout("<?php echo $siteID; ?>Tick()", <?php echo $siteID; ?>g_dwTimeOutSec*1000);
}
function <?php echo $siteID; ?>Tick() 
{
  if (<?php echo $siteID; ?>g_fPlayMode)
    <?php echo $siteID; ?>Next();

}
function <?php echo $siteID; ?>Prev()
{
  <?php echo $siteID; ?>ChangeImage(false);
}
function <?php echo $siteID; ?>Next()
{
  <?php echo $siteID; ?>ChangeImage(true);
}
//current file list/description 
<?php
  for ($i=$numImages-1;$i>=0;$i--) {
    $radarCacheFile = $radarDir . "radar-$siteID-$i.png";
	$rT = $imglistText[$i];
	$rN = $numImages - $i;

    print "${siteID}g_ImageTable[${siteID}g_imax++] = new Array (\"$radarCacheFile\",\"$rT,  $rN/$numImages\");\n"; 
  }
?>
//end current file list/description

<?php if($autoPlay) {echo $siteID . 'Play();' . "\n";} ?>
</script>
<?php

 } // end of if 2 or more images
   else { // only one image 
   ?>
<p><span id="<?php echo $siteID; ?>description"><?php echo $imglistText[0] . ' 1/1'; ?></span><br />
<img src="<?php echo $radarDir . "radar-$siteID-0.png"; ?>" alt="<?php echo $siteHeading; ?>" width="<?php echo $new_width; ?> " height="<?php echo $new_height; ?>" title="<?php echo $siteHeading; ?>" /> </p>
<?php
 } // end only one image
}

?>