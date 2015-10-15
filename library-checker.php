#!/usr/bin/env php

<?php

include 'Moinax/TvDb/Http/HttpClient.php';
include 'Moinax/TvDb/Http/CurlClient.php';
include 'Moinax/TvDb/CurlException.php';
include 'Moinax/TvDb/Client.php';
include 'Moinax/TvDb/Serie.php';
include 'Moinax/TvDb/Banner.php';
include 'Moinax/TvDb/Episode.php';

use Moinax\TvDb\Client;

define('TVDB_API_KEY', 'B6EA9CF79F45EC78');

//Settings
$config["ignoreSpecials"] = true;
$config["scanDir"] = "/mnt/storage/video/TV/";

$opts = getopt("dh");

if (isset($opts["h"])){
	$help =  "library-checker.php -h -d [series dir]
	series dir is optional, if not supplied all series under the scanDir config will be checked
	-d	debug
	-h	help
";
	print $help;
	die;
}


if(count($argv) == 2){
	$config["singleSeries"] = $argv[1];
}

$tvdb = new Client("http://thetvdb.com", TVDB_API_KEY);

if (isset($config["singleSeries"])){
	$nodes[] = $config["singleSeries"];
} else {
	$nodes = scandir($config["scanDir"]);
}

//check against FS
foreach ($nodes as $node){
	$serieDir = $config["scanDir"] . "/" . basename($node);
	if(!is_dir($serieDir) || $node == "." || $node == "..")
		continue;
	$serieName = basename($node);

	//load all the filename we have for this serie
	$files = recursiveScanDir($serieDir);

	printDebug("Retrieving episode info for $serieName");
	$data = $tvdb->getSeries($serieName);

	if(count($data) == 0){
		print "Failed to find series on TVDB: $serieName";
		continue;
	}
	//just take the first for now
	$serie = $data[0];

	$episodes = $tvdb->getSerieEpisodes($serie->id);
	$episodes = $episodes["episodes"];
	
	foreach($episodes as $episode){
		if($config["ignoreSpecials"] && $episode->season == 0)
			continue;
		
		$season_str = str_pad($episode->season, 2, "0", STR_PAD_LEFT);
		$episode_str = str_pad($episode->number, 2, "0", STR_PAD_LEFT);
//		$glob_str1 = $serieDir."/Season ". $season_str ."/*" . $season_str ."[Ex]".$episode_str."*";
//		$glob_str2 = $serieDir."/Season ". $episode->season ."/*" . $episode->season ."[Ex]".$episode->number."*";
		$match_regex = "/[^0-9]0?" . $episode->season . "[Ex]0?" . $episode->number . "[^0-9]/";		

//		if(!(glob($glob_str1) || glob($glob_str2))){
		if(!($matches = preg_grep($match_regex, $files))){
			print "Missing $serieName S" . $season_str . "E" . $episode_str . " - " . $episode->name . "\n";
		} else {
			foreach($matches as $match){
				printDebug("Matched '". $match. "' for $serieName S" . $season_str . "E" . $episode_str . " with regex:" . $match_regex);
			}
		}
	}
}

//list all files under $dir - note, returns file names only, no paths
function recursiveScanDir($dir){
	$result = array();
	$nodes = scandir($dir);
	foreach ($nodes as $node){
		if ($node == "." || $node == "..")
			continue;
		$path = $dir . "/" . $node;
		if (is_dir($path)){
//			print "--- recursing on $node\n";
			$result = array_merge($result, recursiveScanDir($path));			
		} else if (is_file($path)){
//			"print +++ adding file $node\n";
			$result[] = $node;
		}
	}
	return $result;
}

function printDebug($str, $level = 1){
	global $opts;
	if (isset($opts["d"])){
		$STDERR = fopen('php://stderr', 'w+');
		fwrite($STDERR, "$str\n");
		fclose($STDERR);
	}
}

?>
