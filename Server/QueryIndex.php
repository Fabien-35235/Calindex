<?php
 
 
   // Default values for variables in the configuration file
   $ConfigDir = "_Indexes";
   $css= "_Resources/style.css";
   $database= "metadata.db";
   $script='BuildIndex.py';
   $logs= '_Indexes';
  

   
   $xml=simplexml_load_file("CaliNdex.ini");
   if ($xml){
    
      if (isset($xml->database)) {$database = $xml->database;}
      if (isset($xml->download)) {$Download = $xml->download;}
      if (isset($xml->css))      {$css = $xml->css;}
      if (isset($xml->indexes))  {$ConfigDir = $xml->indexes;}
      if (isset($xml->logs))     {$logs = $xml->logs;}
    
   } else {
      echo "<H1>ERROR: Database $database cannot be read</H1>\n";
   }
 
  $URL= basename(__FILE__);

 
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

  // Configuration of each index file is stored in "$ConfigDir/filename.epub.conf"
  // syntax is:
  // # Index configuration file for Calisson
  // [BuildIndex]
  // Date=  Mon Apr 23 19:58:52 2018
  // configFile = BuildIndex.ini
  // MaxAuthors = None
  // URL = https://quickconnect.to/fabien35235/myebooks/
    
   function GetConfig($Filename, $TagsList, $Name, $DatabaseDate){
      $res=array();
      $f= fopen($Filename, "r");
      $res['mode']='None';
      foreach ($TagsList as $t){
         $res[$t]='No';
      }
      $res['Name'] = $Name;
      
      while ($str = fgets($f)){
         if (preg_match("/\s*#/", $str)){
            continue;
         }
         if (1 === preg_match("/(\w+) *= *(.*)$/",$str,$groups)){
            //echo ("Found "); print_r($groups[1]); echo (" = "); print_r( $groups[2]); echo("<br>\n");
            $val = preg_replace("/(\n|\r|\f)/","",$groups[2]);
            $res[$groups[1]]=$val ;
            if (($groups[1] === 'onlySubjects') or ($groups[1] ==='avoidSubjects')){
                $res['mode']= $groups[1];
                //echo ("Found($groups[1])=$groups[2]");
                $tags = explode(',', $val);
                foreach ($tags as $t){
                     $t = preg_replace("/\%%20/"," ",$t); // Surprising, but because % has to be doubled in the .ini file due to python libs.     
                     $res[$t]='Yes' ;
                     //echo("TAG '$t'\n");
               }
            }
            //if ($groups[1] === 'Date'){
            //    $res['Date'] = date_parse($res['Date']);            
            //}
         }
      }
    
      fclose($f);
      // now, let's find the date and size of the epub
      $EpubFile = $res['EpubIndex'];
      $stats = stat($EpubFile); 
      if (isset($stats)){
         $res['Date'] = date("d/m/Y H:i:s",$stats['mtime']);
         $res['Size'] = sprintf("%2.2f MB",intval($stats['size'])/(1024*1024));
         $res['uptodate'] = ($stats['mtime'] >= $DatabaseDate) ? 'Yes' : 'No';
         //-------- TODO:should also find the number of ebooks and of authors.
      } else {
         $res['Date'] =  '?';
         $res['Size'] = '?';
         $res['uptodate'] = 'No';
      }
      //echo("GetConfig($Filename,$EpubFile)");
      //print_r($res);
      return ($res);
   }
   
   
  function GetConfigValue($AllConfs, $conf, $field){
      //echo ("GetConfigValue($conf, $field)\n");
     
      return (isset($AllConfs[$conf], $AllConfs[$conf][$field]) ? $AllConfs[$conf][$field] : '???'); 
  }
    
   function GetAllConfigs($ConfigDir, $TagsList, $DatabaseDate){
      $res = array();
      $dir = opendir($ConfigDir);
      
      while (false !== ($f = readdir($dir))) {
          //echo "GetAllConfigs($ConfigDir/$f)\n";
         
          if (preg_match("/((\w|-)+)\.ini/",$f, $matches) === 1){
              $res[$matches[1]] = GetConfig("$ConfigDir/$f", $TagsList, $matches[1], $DatabaseDate);
          }
      }
      closedir($dir);
      return $res;
  }
    
          


   function PrintOneTag($AllConfs, $ConfigList, $Field ){
      echo "<tr><td>$Field</td>";
      foreach ($ConfigList as $conf){
          echo "<td>" . GetConfigValue($AllConfs, $conf, $Field) . "</td>";
      }
      echo "<td> <input type='checkbox' name='".urlencode($Field)."'></td>";
      echo "</tr>\n";
   }
    
   function PrintOneField($AllConfs, $ConfigList, $Field , $TagName, $Default='N/A'){
      echo "<tr><td>$TagName</td>\n";
      foreach ($ConfigList as $conf){
          echo "<td>" . GetConfigValue($AllConfs, $conf, $Field) . "</td>";
      }
      echo "<td>$Default</td>";
      echo "</tr>\n";
   }
   
   function IsAFreeName($AllConfs, $ConfigList, $name){
      foreach ($ConfigList as $conf){
         echo("CONF ($conf)");
         if ($conf == $name){return False;}
      }
      return True;
   }
   
   function GetFreeName($AllConfs, $ConfigList){
      // returns the name of the first index in the form custom-%d which is not used
      for($i=1;$i<100;$i++){
         $name = "custom-$i";
         if (IsAFreeName($AllConfs, $ConfigList, $name)) {return "$name.epub";}
      }
      echo "<H1>ERROR: No more free custom-INT.epub names</H1>";
      return "index-". date("Y-m-d_H-i-s"). ".epub"; 
   }
   
   function Execute($ConfigDir, $tagsList, $DatabaseDate){
      
      $AllConfs = GetAllConfigs($ConfigDir, $tagsList, $DatabaseDate);
      $ConfigList = array_keys($AllConfs);  // the list of .conf files
      PrintPageStart();
      echo "<h1>Index of books as a ePub</h1>\n";
      echo "<p>The date of the database is " . date("d/m/Y H:i:s",$DatabaseDate) . "</p>\n";
      echo "<p>Please chose a pre-existing index, or create your own</p>\n";
      echo "<p>NB: if an index is marked NOT up to date, it will be rebuilt before download<p>\n";
      echo "<form method='GET' action='BuildIndex.php'>\n";
      echo "<table>\n";

      PrintOneField($AllConfs, $ConfigList, 'Name' , 'Name','Build my own');
      $NewEpub = $ConfigDir."/".GetFreeName($AllConfs, $ConfigList);
      $InputField = "<input type='text' name='EpubIndex' value = '$NewEpub' maxLength='32'>";
      PrintOneField($AllConfs, $ConfigList, 'EpubIndex' , 'File', $InputField);
    
    
      echo "<tr><td>Tags</td>";
      foreach ($ConfigList as $conf){
         echo "<td></td>";
      }
      echo ("<td></td></tr>\n");
      PrintOneField($AllConfs, $ConfigList,'mode','Mode','avoidSubject');
      foreach ($tagsList as $tag){
         PrintOneTag($AllConfs, $ConfigList, $tag);
      }

      //PrintOneField($AllConfs, $ConfigList,'authors','number of authors');
      //PrintOneField($AllConfs, $ConfigList,'books','number of books');
      PrintOneField($AllConfs, $ConfigList,'Size','size of the epub');
      PrintOneField($AllConfs, $ConfigList,'Date', 'Creation Date');
      
      PrintOneField($AllConfs, $ConfigList,'uptodate','Up to date'); 
      
      echo '<tr><td>Select this index</td>';
      foreach ($ConfigList as $conf){
         if (GetConfigValue($AllConfs, $conf,'uptodate') == 'Yes'){
            echo '<td><A href="'.GetConfigValue($AllConfs, $conf, 'EpubIndex') . '">Download</A></td>';
         } else {
            echo '<td><A href="BuildIndex.php?configFile=' . GetConfigValue($AllConfs, $conf, 'Name') . '.ini';
            echo '&EpubIndex=' . GetConfigValue($AllConfs, $conf, 'EpubIndex') ;
            echo '">DOWNLOAD</A></td>';
         }
      }  
      echo "<td><input type=submit value='Download'></td></tr>";
      echo "</table>\n";
      echo "</form>\n";
  }
  
   
  function BuildTagsRequest($conn){
        $q = "SELECT t.name AS tag_name FROM tags t ";
        $q = $q . " ORDER BY tag_name";
     
        #echo("Request(" . $q . ")\n");
        return ($conn->query($q));
      
  }

  function GetDateOfFile($Filename){
      $stats = stat($Filename);
      return $stats['mtime'];
  }

  $conn = new SQLite3($database,SQLITE3_OPEN_READONLY);
  if ($conn->connect_error) {
      echo "Connection failed: " . $conn->connect_error . "<br>\n";
      die("Connection failed: " . $conn->connect_error . "<br>\n");
  } 
  //var_dump( $conn);
 
    
  $result = BuildTagsRequest($conn);
  $myTagsList = array();
    
  while ($row = $result->fetchArray()){
      //echo ("-->" . $row['tag_name']."<br>\n");
      array_push($myTagsList, $row['tag_name']);
  }
  Execute($ConfigDir, $myTagsList, GetDateOfFile($database));
   
   
?>  
 </BODY>
</HTML>