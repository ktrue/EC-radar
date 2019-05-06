<?php
// Version 1.00 - 01-Mar-2008 - initial release
// Version 1.01 - 27-Oct-2017 - updated for ec-radar.php V2.02 checks for functionality
// ------------settings -----------------------------------
// These should be the same as in ec-radar.php and
// this file should be in the same directory as ec-radar.php
//
//
$cacheName = 'ec-radar.txt';     // note: will be changed to -en.txt or 
//                                  -fr.txt depending on language choice and stored in $radarDir
$radarDir = './radar/';           // directory for storing radar-XXX-0.png to radar-XXX-6.png images
//                                  note: relative to document root.
//-------------end of settings-------------------------------
$Version = 'V1.01 - 27-Oct-2017';
if (isset($_REQUEST['sce']) && strtolower($_REQUEST['sce']) == 'view' ) {
//--self downloader --
   $filenameReal = __FILE__;
   $download_size = filesize($filenameReal);
   header('Pragma: public');
   header('Cache-Control: private');
   header('Cache-Control: no-cache, must-revalidate');
   header("Content-type: text/plain,charset=ISO-8859-1");
   header("Accept-Ranges: bytes");
   header("Content-Length: $download_size");
   header('Connection: close');
   readfile($filenameReal);
   exit;
}
error_reporting(E_ALL);  // uncomment to turn on full error reporting
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>ec-radar-cachetest.php utility</title>
<style type="text/css">
body {
  background-color:#FFFFFF;
  font-family:Verdana, Arial, Helvetica, sans-serif;
  font-size: 12px;
}
</style>
</head>
<h1>Test for ec-radar.php needed PHP functions and file cache functioning</h1>
<?php
//
// constants
    $siteID = 'TST';
    $Lang = 'en';
// run the tests
//
 echo "<p>PHP Version <b>" . phpversion() ."</b></p>";

$hasUrlFopenSet = ini_get('allow_url_fopen');
if(!$hasUrlFopenSet) {
	print "<h2>Warning: PHP does not have 'allow_url_fopen = on;' --<br/>image fetch by ec-radar.php is not possible.</h2>\n";
	print "<p>To fix, add the statement: <pre>allow_url_fopen = on;\n\n</pre>to your php.ini file to enable ec-radar.php operation.</p>\n";
	
}

	$toCheck = array('curl_init','curl_setopt','curl_exec','curl_error','curl_close',
	    'iconv','imagecreatefrompng','imagepng','imagecreatefromgif','imagegif');

	print "<h2>Status of needed built-in PHP functions</h2><p>\n";
  $notFound = 0;
	foreach ($toCheck as $n => $chkName) {
		print "function <b>$chkName</b> ";
		if(function_exists($chkName)) {
			print " is available<br/>\n";
		} else {
			print " is <b>NOT available</b><br/>\n";
			$notFound++;
		}
		
	}

  if($notFound == 0 ) {
		print "<p>All required PHP functions appear to be available.</p>\n";
	} else {
		print "<p>Warning: $notFound function(s) are not available in PHP but are required for ec-radar.php operation.</p>\n";
	}

print "<h2>Checking for cache functionality</h2>\n";
$t = pathinfo(__FILE__);  // get our program name for the HTML comments
$Program = $t['basename'];
$Status = "<!-- ec-radar.php - $Version -->\n";
$BasePath = $t['dirname'];

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
$cacheName = preg_replace('|.txt$|',"-$siteID.txt",$cacheName);

$RealCacheName = $cacheDir  . $cacheName;
$tpath = realpath($RealCacheName);
if ($tpath) { 
  //print "<p>changed: '$RealCacheName'<br/> to: '$tpath'</p>\n";
  $RealCacheName = $tpath; 
}
	
	$NOWgmt = time();
  $NOWdate = gmdate("D, d M Y H:i:s", $NOWgmt);
	echo "<p>Using $RealCacheName as test file.</p>\n";
	echo "<p>Now date='$NOWdate'</p>\n";
	$fp = fopen($RealCacheName,"w");
	if ($fp) {
	  $rc = fwrite($fp,$NOWdate);
	  if ($rc <> strlen($NOWdate)) {
	    echo "<p>unable to write $RealCacheName: rc=$rc</p>\n";
	  }
	  fclose($fp);
	} else {
	  echo "<p>Unable to open $RealCacheName for write.</p>\n";
	}
	
	$contents = implode('',file($RealCacheName));
	
	echo "<p>File says='$contents'</p>\n";
	if ($contents == $NOWdate) {
	  echo "<p>Write and read-back successful.. contents identical -- ec-radar.php cache should work fine.</p>\n";
	} else {
	  echo "<p>Read-back unsuccessful. contents different -- ec-radar.php cache will not work correctly</p>\n";
	}
	
?>

<body>
</body>
</html>