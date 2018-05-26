<?php

  //------------------------------------------------------------------------
  // Creates an index file, 
  // then, serves it through a redirect
  //
  // reads the configuration on CaliNdex.ini
  //    <database> is the path to the database
  //    <css>      is the path to the css file (just for error messages)
  //    <logs>     is used as the --LogDir argument to BuildIndex.py
  //    <python>   is the name of thepython interpreter to use, or empty
  //    <script>   is the path to the python script
  //  
  // GET arguments:
  //     EpubIndex  = .epub full name of the ebook to build, relative to the directory of the PHP page. 
  //     configFile = .ini file to use (optional)
  //     All other arguments should have the form:
  //     Tag=on,  listing a Tag to be OMITTED from the index
  //
  // example:
  // http://192.168.1.16/ebooks/BuildIndex.php?EpubIndex=_Indexes/custom-1.epub&Fantastique=on&Fantasy=on
  //
  // The date of the EpubIndex is compared to the date of the database
  //     If the EpubIndex exists and is more recent, then it is served immediately
  //     Otherwise it is first created
  //
  //------------------------------------------------------------------------
  
 
  include 'inits.php';
 
  function GetDateOfFile($Filename){
      $stats = stat($Filename);
      if (isset($stats)) return $stats['mtime']; 
      return 0;
  }


  // ---------------------------------------------------------------------
  // 'Main()'
  // ---------------------------------------------------------------------

  if (! isset($_GET['EpubIndex'] )){
    PrintPageStart('BuildIndex.php');
    echo "<H1>ERROR: Malformed URL ?EpubIndex= must be specified</H1>\n";
    die();
  }
  
  $DatabaseDate = GetDateOfFile($database);
  $ConfigDir = urldecode($_GET['ConfigDir']);
  // safety check: ConfigDir must start by letters
  if (preg_match("/^\w/", $ConfigDir)){
    $EpubFile = $ConfigDir.'/'.urldecode($_GET['EpubIndex']).'.epub';
  } else {
    $EpubFile = './'.urldecode($_GET['EpubIndex']).'.epub';
  }
  $EpubDate = GetDateOfFile($EpubFile);
  
  
  if ($EpubDate > $DatabaseDate){
     //echo("Epub Exists and is more recent\n"); 
     header("Location: $EpubFile"); 
     die();
  }
  
  //echo("Epub must be created again ! $EpubFile $EpubDate < $database $DatabaseDate\n"); 
  // so Epub must be created again
  if (isset($_GET['configFile'])) {
      $myConfig = urldecode($_GET['configFile']);
  } elseif (preg_match("/(.+)\.epub/",$EpubFile, $matches) === 1){
      $myConfig = $matches[1].".ini";
  }
  
  $separator = '';
  $myArgs = '';
  foreach($_GET as $key => $value) {
      if (($key != 'EpubIndex') and ($key != 'configFile') and ($key != 'ConfigDir')){
        $myArgs = $myArgs . $separator . urldecode($key);
        //echo("ARG($key)=$value, ".urldecode($key)."\n"); 
        $separator = ',';
      }
    }

  $myCmd = '';
  if ($python != ''){
    $myCmd = $python." ";
  }
  $myCmd =  $myCmd." ".escapeshellarg($script); ///================================= CAVEAT/ Security FLOW Here: Should test the path
  $myCmd = $myCmd." --EpubIndex ". escapeshellarg($EpubFile);
  if (isset($myConfig)){
      $myCmd = $myCmd." --configFile ".escapeshellarg( $myConfig);
  }
  if ($myArgs != ''){
      $myCmd = $myCmd." --avoidSubjects ". escapeshellarg(urlencode($myArgs));
  }
  $myCmd = $myCmd." --Verbose ". escapeshellarg('t');
  $myCmd = $myCmd." --Log ". escapeshellarg($ConfigDir);
  
  $debug = False;
  //$debug = True;

  if ($debug){
    echo("CMD = $myCmd");
    $res = exec($myCmd, $retArr, $retVal);
  
    
    echo("<br>RetArray:</br>\n"); 
    foreach($retArr as $l){
      echo("<br>$l</br>\n");
    }
    echo("<br>RetVal:"); echo($retVal);
  } else {
    
    $res = exec($myCmd, $retArr, $retVal);
    header("Location: $EpubFile"); 
    die();
 
  }
  
  //
?>