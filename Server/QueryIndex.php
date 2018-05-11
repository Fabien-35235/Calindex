<?php
   echo("OK\n");
   
  $possible_height = array(32, 45, 68, 100, 500, 'All');
  $database= "metadata.db";
  $css= "_Resources/style.css";
  $PageUp = "_Resources/PageUp.png";
  $PageDown = "_Resources/PageDown.png";
  $Download = "_Resources/Download.png";
   
  $xml=simplexml_load_file("CaliNdex.ini");
  if ($xml){
    
    $database = $xml->database;
    $possible_height = explode(',',$xml->height );
    $PageUp = $xml->pageup;
    $PageDown = $xml->pagedown;
    $Download = $xml->download;
    $css = $xml->css;
  } else {
    echo "<H1>ERROR: Database $database cannot be read</H1>\n";
  }
  $height = $possible_height[0];
  $URL= basename(__FILE__);

  echo("OK\n");
 
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
    echo '<title>ebooks index </title>'."\n";
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
    
  function GetConfig($Filename, $TagsList){
    $res=array();
    $f= fopen($Filename, "r");
    $res->mode='None';
    foreach ($TagsList as $t){
        $res[$t]='No';
    }    
    while ($str = fgets($f)){
        if (re.match($str, "/s*#")){
            continue;
        }
        if (re.match($str, "(\w+) *= *(.*)$")){
            $res[re.group(1)]= re.group(2);
            if ((re.group(1) === 'onlySubjects') or (re.group(1) ==='avoidSubjects')){
                $res['mode']= re.group(1);
                $tags = explode(',', re.group(1));
                foreach ($tags as $t){
                    $res[$t]='Yes' ;
                }
            }
            if (re.group(1) === 'Date'){
                $res['Date'] = date.parse($res['Date']);            
            }
        }
    }
    
    fclose($f);
    return ($res);
  }
   ?>
   
  function GetConfigValue($AllConfs, $conf, $field){
      return (isset($AllConfs[$conf], $AllConfs[$conf][$field]) ? $AllConfs[$conf][$field] : ''); 
  }
    
  function GetAllConfigs($ConfigDir, $TagsList){
 
      $dir = opendir($ConfigDir);
      $ls = readdir($dir);
      $res = [];
      foreach ($ls as $f){
          if (preg_match($f,"(\w+)\.epub.conf"),$groups){
              $name = $groups[1];
              $res[$name] = GetConfig($f, $TagsList);
          }
      }
      return $res
  }
          

  function PrintOneField($AllConfs, $ConfigList, $Field ){
      echo "<tr><td>$Field</td>";
      foreach ($ConfigList as $conf){
          echo "<td>" + GetConfigValue($AllConfs, $Conf, $Field) + "</td>";
      }
      echo "<td> </td>";
      echo '</tr>\n';
  }

  function PrintOneTag($AllConfs, $Conf, $Field ){
      echo "<tr><td>$Field</td>";
      foreach ($ConfigList as $conf){
          echo "<td>" + GetConfigValue($AllConfs, $conf, $Field) + "</td>";
      }
      echo "<td> <input type='checkbox'></td>";
      echo '</tr>\n';
  }
    
  
  function Execute($ConfigDir, $tagsList){
    $AllConfs = GetAllConfigs($ConfigDir, $tagsList);
    $ConfigList = keys($AllConfs)
    PrintPageStart();
    
    echo "<p>Please chose a pre-existing index, or create your own</p>\n"

  
    echo "<table>\n"

    PrintOneField('Name', 'name', $ConfigList);
    
    echo "<tr><td>Tags</td>";
        foreach ($ConfigList as $conf){
            echo "<td></td>";
        }
        echo ("<td></td></tr>\n");
    
    foreach ($tagsList as $tag){
        PrintOneTag($ConfigList, $tag);
    }

    PrintOneField('number of authors', 'authors', $ConfigList);
    PrintOneField('number of books', 'books', $ConfigList);
    PrintOneField('size of the epub', 'size', $ConfigList);
    PrintOneField('Creation Date', 'date', $CondigList);
    PrintOneField('Up to date', 'uptodate', $ConfigList);
    echo '<tr><td>Select this index</td>'
        foreach ($ConfigList as $conf){
            echo '<td><A href="BuildIndex.php?name=' + GetConfigValue($conf, 'name') + '">DOWNLOAD</A></td>';
        }
        echo "<td><input type=submit></td></tr>\n";
  }
  
  
  function BuildTagsRequest($conn){
        $q = "SELECT t.name AS tag_name FROM tags t ";
        $q = $q . " ORDER BY tag_name";
     
        #echo("Request(" . $q . ")\n");
        return ($conn->query($q));
      
  }


  $conn = new SQLite3($database,SQLITE3_OPEN_READONLY);
  if ($conn->connect_error) {
      echo "Connection failed: " . $conn->connect_error . "<br>\n";
      die("Connection failed: " . $conn->connect_error . "<br>\n");
  } 
  //var_dump( $conn);
 
    
  $result = BuildTagsRequest($conn);
  $myList = array();
  //echo ("Manage Bulk(" . $query_author . "," . $query_serie .")");
  //var_dump($result);
    
  while ($row = $result->fetchArray()){
      //echo ("-->" . $row['epub_URL']."<br>\n");
      array_push($myList, $row['tag_name']);
  }
  Execute($myList);
   
   
?>  
 </BODY>
</HTML>