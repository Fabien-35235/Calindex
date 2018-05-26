 <?php
   // -------------------------------------------------------------------------
   // CaliSSon is a very lightweight PHP service for Calibre
   // CaliSSon is published under MIT license
   // -------------------------------------------------------------------------
   // Copyright (c) 2018 fabien.battini(AT)gmail.com

   
  // Default values for variables in the configuration file   

  $database= "metadata.db";
  $Download = "_Resources/Download.png";
  $css= "_Resources/style.css";
  $PageUp = "_Resources/PageUp.png";
  $PageDown = "_Resources/PageDown.png";
  $ConfigDir = "_Indexes";
  $logs= '_Indexes';
  $possible_height = array(32, 45, 68, 100, 500, 'All');
  $python=''; 
  $script='BuildIndex.py';

 
  $URL= basename(__FILE__);
 
 
  $xml=simplexml_load_file("CaliNdex.conf");
  if ($xml){  
    if (isset($xml->database)) {$database = $xml->database;}
    if (isset($xml->download)) {$Download = $xml->download;}
    if (isset($xml->css))      {$css = $xml->css;}
    if (isset($xml->pageup))   {$PageUp = $xml->pageup;}
    if (isset($xml->pagedown)) {$PageDown = $xml->pagedown;}
    if (isset($xml->indexes))  {$ConfigDir = $xml->indexes;}
    if (isset($xml->logs))     {$logs = $xml->logs;}
    if (isset($xml->height))   {$possible_height = explode(',',$xml->height );}  
    if (isset($xml->python))   {$python = $xml->python;}
    if (isset($xml->script))   {$script = $xml->script;}

  } else {
    PrintPageStart($URL);
    echo "<H1>ERROR: Configuration file CaliNdex.conf cannot be read</H1>\n";
  }
  $height = $possible_height[0];
 

  function PrintPageStart($Title){
    global $css;
    
    echo '<!DOCTYPE html>'."\n";
    echo '<html><head>'."\n";
    echo '<meta http-equiv="Content-Type" content="text/html;charset=ISO-8859-1" />'."\n";
    echo '<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">'."\n";
    echo '<head>'."\n";
    echo '  <link rel="stylesheet" href="'.$css.'">'."\n";
    echo '</head>'."\n";
    echo "<title>$Title</title>\n";
    echo '<BODY>'."\n";
  }
