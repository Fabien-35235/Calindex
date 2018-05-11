<?php

  //------------------------------------------------------------------------
  // Creates an index file, 
  // then, downloads it
  //
  // instance 1: the config file exist
  // BuildIndex.php?configFile=index.ini&EpubIndex=index.epub
  //
  // instance 2: the config file does NOT exist, all parameters are specified
  // BuildIndex.php?EpubIndex=index-2018-05-10_18-27-11.epub&Fantastique=on&Fantasy=on
  // the mode is always "avoidSubject"
  //------------------------------------------------------------------------
  
 
  $ConfigDir = "_Indexes";
  $css= "_Resources/style.css";
  
  function PrintPageStart(){
    global $css;
    
    echo '<!DOCTYPE html>'."\n";
    echo '<html><head>'."\n";
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'."\n";
    echo '<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">'."\n";
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'."\n";
    echo '<head>'."\n";
    echo "  <link rel='stylesheet' href='$css'>\n";
    echo '</head>'."\n";
    echo '<title>ePub as an index </title>'."\n";
    echo '<BODY>'."\n";
  }
  
  $xml=simplexml_load_file("CaliNdex.ini");
  
  
  if ($xml){   
    if (isset($xml->indexes))  {$ConfigDir = $xml->indexes;}
  } else {
    PrintPageStart();
    echo "<H1>ERROR: Database $database cannot be read</H1>\n";
  }



  // ---------------------------------------------------------------------
  // 'Main()'
  // ---------------------------------------------------------------------

  if (isset($_GET['configFile'], $_GET['EpubIndex'] )) {
    $myFile = "$ConfigDir/".$_GET['configFile'];
    $myEpub = $_GET['EpubIndex']
    $myCmd = "BuildIndex.py ".escapeshellarg("--configFile $myFile");
    $ res = exec(myCmd);
    header('Location: $myEpub'); 
  }
  
?>

