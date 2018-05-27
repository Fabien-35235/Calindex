 <?php
 
  include 'inits.php';
  $URL= basename(__FILE__);
  
  function describeTable($name,$conn){
      
        $q = "SELECT * from ". $name;
        echo "<br><br>Table(".$name.")<br>\n";
        $result = $conn->query($q);
        $row = $result->fetchArray();
        for($i = 0; $i < $result->numColumns(); $i++) {
            $field = $result->columnName($i);
            echo  $field . ": ". $row[$field] ."<br>\n ";
        }
        echo "End of Table(".$name.")<br>\n";
  }
  
 
  function CreateTmpFile(){
    $today = date("Y-m-d-H-i-s"); 
    $Filename="books_$today.zip";
    return ($Filename);
  }
  
   // ----------------------------------------------------
   // ------------------ When request is a POST download
   
  function ExecuteDownload($myList){
      $zip = new ZipArchive();
      $thisdir = getcwd();
      $filename = CreateTmpFile();  
      $tmpfile = "/tmp/$filename";
      if (file_exists($tmpfile)){
        unlink($tmpfile);
      }
      if ($zip->open($tmpfile, ZipArchive::CREATE)!==TRUE) {
        echo("Impossible d'ouvrir le fichier <$tmpfile><br>\n");
        exit();
      }
      
      foreach ($myList as $i => $f ){
        //echo "adding(".$f.")<br>\n";
        $zip->addFile($f);
      }
      
      $zip->close();
      header("Content-Type: application/zip");
      header("Content-Disposition: attachment; filename=$filename");
      header("Content-Length: " . filesize($tmpfile));
      
      readfile($tmpfile);
      unlink($tmpfile);
      exit();
  }
  
  function ManageDownload(){
      $myList = array();
      foreach ($_POST as $k => $v ){
        //echo "POST[$k]=$v<br>\n";
        if (strpos($k,"Book_") !== false){
          $val =   json_decode($v);
          //print_r( $val);
          
          array_push($myList, $val->{'url'});
          //echo 'ADDED('.$val->{'url'}.")<br>\n";
        }
      }
      ExecuteDownload($myList);
  }
  

  // ----------------------------------------------------
  // ------------------ When request is a GET bulk

  function BuildBulkRequest($conn, $author, $serie){
      $q = "SELECT aut.sort AS author_name ";
      $q = $q . "   , b.path || '/' || epub.name || '.epub' AS epub_URL ";
      $q = $q . "   , ser.name AS serie_name, ser.id AS serie_id ";
      $q = $q . " FROM books b ";
      $q = $q . " LEFT OUTER JOIN books_authors_link bal  ON bal.book=b.id ";
      $q = $q . " LEFT OUTER JOIN authors            aut  ON bal.author=aut.id ";
      $q = $q . " LEFT OUTER JOIN books_series_link  bsl  ON bsl.book=b.id ";
      $q = $q . " LEFT OUTER JOIN series             ser  ON bsl.series=ser.id ";
      $q = $q . " LEFT OUTER JOIN data               epub ON b.id=epub.book AND epub.format ='EPUB' ";
      $q = $q . " WHERE author_name LIKE '$author' ";
      $q = $q . " AND ser.name LIKE '$serie' ";
      $q = $q . " ORDER BY b.series_index";
   
      
      #echo("Request(" . $q . ")\n");
      return ($conn->query($q));
    
  }
  
  
   //exemple: https://192.168.1.16/ebooks/index.php?bulk&author=Douglas Adams&serie=H2G2
   //exemple: https://192.168.1.16/ebooks/index.php?bulk&author=Asimov, Isaac&serie=Fondation
   //mais PAS https://192.168.1.16/ebooks/index.php?bulk&author=Isaac+Asimov&serie=Fondation
   function ManageBulk($conn){
      
      $query_author  =  $_GET['author'];
      $query_serie   =  $_GET['serie'];
      
      $result = BuildBulkRequest($conn, $query_author, $query_serie);
      $myList = array();
      //echo ("Manage Bulk(" . $query_author . "," . $query_serie .")");
      //var_dump($result);
      
      while ($row = $result->fetchArray()){
        //echo ("-->" . $row['epub_URL']."<br>\n");
        array_push($myList, $row['epub_URL']);
      }
      ExecuteDownload($myList);
   }
   
  // ----------------------------------------------------
  // ------------------ When request is the default, ie. a GET

   
    //Get at most $query_limit Rows, starting at $Offset
    // with Author == $Author [To Do]
    // with Tag  == $Tag [To DO]
    // Get: ID, Author, Title, url.mobi, url.epub, Tag list, Serie, NumberInSerie
    
    function BuildRequest($conn, $query_limit, $Offset, $query_tag, $query_letter){
      $q = "SELECT b.id AS book_id,  b.title, b.series_index, aut.sort AS author_name ";
      $q = $q . "   , ser.name AS serie_name, ser.id AS serie_id ";
      $q = $q . "   , b.path || '/' || epub.name || '.epub' AS epub_URL ";
      $q = $q . "   , b.path || '/' || mobi.name || '.mobi' AS mobi_URL ";
      $q = $q . "   , tg.name AS tag_name ";
      $q = $q . " FROM books b ";
      $q = $q . " LEFT OUTER JOIN books_authors_link bal  ON bal.book=b.id ";
      $q = $q . " LEFT OUTER JOIN authors            aut  ON bal.author=aut.id ";
      $q = $q . " LEFT OUTER JOIN books_series_link  bsl  ON bsl.book=b.id ";
      $q = $q . " LEFT OUTER JOIN series             ser  ON bsl.series=ser.id ";
      $q = $q . " LEFT OUTER JOIN data               epub ON b.id=epub.book AND epub.format ='EPUB' ";
      $q = $q . " LEFT OUTER JOIN data               mobi ON b.id=epub.book AND epub.format ='MOBI' ";
      $q = $q . " LEFT OUTER JOIN books_tags_link    btl  ON btl.book=b.id ";
      $q = $q . " LEFT OUTER JOIN tags               tg   ON btl.tag=tg.id ";
      
   
      if ($query_tag !== 'Tous'){
          $q = $q . " WHERE tag_name LIKE '$query_tag' ";
      }
      if ($query_letter !== 'A'){
          $liaison = ($query_tag !== 'Tous')? 'AND' : 'WHERE'; 
          $q = $q . " $liaison author_sort >= '$query_letter' "; 
      }

      $q = $q . " ORDER BY author_sort, serie_id, b.series_index, title ";
      if ($query_limit == 'All') {
        //$q = $q . " LIMIT 26 ";
        //$q = $q . " OFFSET 0 ";
      } else{
        $q = $q . " LIMIT $query_limit ";
        $q = $q . " OFFSET $Offset ";
      } 
      
    
      //echo "BuildRequest($query_limit, $Offset, $query_tag, $query_letter)<br>\n";
      //echo $q."<br>\n";
      return ($conn->query($q));
    }
     
    function CountNewAuthors($res, $max){
        //echo "CountNewAuthors($max)\n<br>";
        $new = 0;
        $LastAuthor = '';
        while ($row = $res->fetchArray()){
          if ($row['author_name'] != $LastAuthor){
            $LastAuthor = $row['author_name'];
            $new += 1;
            $max -= 1;
          }
          $max -= 1;
          if ($max <= 0) {
            break;
          }
        }
         
        $res->reset();
        //echo "CountNewAuthors($max) = $new\n<br>";   
        return $new;
     }
    
    function BuildHREF($tag,$tart,$height,$letter,$text,$class){
      global $URL;
      $res = "<A HREF='$URL?raison=$test&tag=$tag&start=$start&height=$height&letter=$letter'>$text</A>";
      return $res;
    }
    
 
    
    // _GET may have the following parameters:
    // tag={'','Tous', 'Espionnage', ...}
    // start= index du premier livre
    // height= max number of books, or 'All'
    // letter=first letter to start with
    
  function PrintOnePage($conn){
      global $possible_height;
      global $height;
      global $URL, $PageDown, $PageUp, $Download;
      
      PrintPageStart("Ebooks Index");
      
      $ActuallyPrinted = 0;
      $LastAuthor = "";
      $query_tag  = (isset($_GET['tag']) && $_GET['tag'] != '') ?  $_GET['tag'] : 'Tous';
      $query_start= (isset($_GET['start']) && $_GET['start'] != '') ?  $_GET['start'] : '0';
      $query_limit= (isset($_GET['height']) && $_GET['height'] != '') ?  $_GET['height'] : $height;
      $query_letter=(isset($_GET['letter']) && $_GET['letter'] != '') ?  $_GET['letter'] : 'A';
      
      if ($query_limit == 'All'){
        $previous = 0;
        $result = BuildRequest($conn, $query_limit, $query_start, $query_tag, $query_letter);
        $next=0;
      } else {  
        $previous= max(0,$query_start - $query_limit);
    
        $result = BuildRequest($conn, $query_limit, $previous, $query_tag, $query_letter);
        $new_authors = CountNewAuthors($result, $query_limit);
        $previous= max(0,$query_start - $query_limit +$new_authors);

        $result->finalize();
      
        $result = BuildRequest($conn, $query_limit, $query_start, $query_tag, $query_letter);
        $new_authors = CountNewAuthors($result, $query_limit);
    
        $next= $query_start + $query_limit - $new_authors;
      }
      
      
        $mq = "SELECT name FROM tags ORDER BY name";
        $nr = $conn->query($mq);
        
        /// --------------- Pagination index ---------------
        echo "\n<Table >\n";
        
        $today = date("d/m/Y H:i:s"); 
        echo "<TR><TD><B>Ebooks</B> $today</TD>\n";
        echo "<TD  class=pages> <A HREF='$URL?tag=$query_tag&start=$previous&height=$query_limit&letter=$query_letter'><IMG src='$PageDown' height=40px> </A></TD>\n";
        
 
        foreach ($possible_height as $h){
          $class =  ($query_limit == $h) ? 'segap' : 'pages';
          echo "<TD>";
          //echo " <A HREF='$URL?tag=$query_tag&start=$query_start&height=$h&letter=$query_letter'>$h</A>";
          echo BuildHREF($query_tag,$query_start,$h,$query_letter,$h, $class);
          echo " </TD>\n";
        }
        echo "<TD  class=pages> <A HREF='$URL?tag=$query_tag&start=$next&height=$query_limit&letter=$query_letter'> <IMG src='$PageUp' height=40px> </A></TD>\n";
        
        echo "<TD  class=pages> ";
        echo "<form method='POST' action='$URL'>";
        echo "<input type='image' name='download' value='Download' src='$Download' height=40px/>";
        echo "</TD>\n";
        echo "<TD  class=pages> ";
        echo "<a href='QueryIndex.php'>Indexes in EPUB ebooks</A>\n";
        echo "</TD>\n";
        echo "</TR>\n";
        
   
        
        echo "\n</Table>\n";
        
        /// --------------- Tags index ---------------
        
        echo "\n<Table>\n";
        $class =  ($query_tag == 'Tous') ? 'segap' : 'pages';

        // if the tag is changed, we restart the selection from 0
        echo "<TD> ";
        //echo "<A HREF='$URL?tag=Tous&start=0&height=$query_limit&letter=$query_letter'>Tous </A>";
        echo BuildHREF('Tous',0,$query_limit,$query_letter,'Tous',$class);
        echo "</TD>\n";
       
        while ($row = $nr->fetchArray()){
          $class =  ($query_tag == $row['name']) ? 'segap' : 'pages';
          echo "<TD> ";
          //echo "<A HREF='$URL?tag=".$row['name']. "&start=0&height=$query_limit&letter=$query_letter'> ".$row['name'];
          echo BuildHREF($row['name'],0,$query_limit,$query_letter,$row['name'],$class);
          echo "</TD>\n";
        }
        echo "</TR></Table>\n";
        // --------------- letters list ---------------
        echo "\n<Table>\n";
        echo "<TR>\n";
        for ($c=ord('A'); $c<=ord('Z'); $c++){
            
          $class =  ($query_letter == chr($c)) ? 'segap' : 'pages';
          echo "<TD> ";
          echo BuildHREF($query_tag, 0, $query_limit, chr($c), chr($c), $class );
          echo "<TD> ";
        }
         echo "</TR></Table>\n";
        
      /// --------------- Books list ---------------
      
      
 
   
      
      $max = ($query_limit == 'All' ? 10000 : $query_limit) ;
      echo "<br>\n";
      echo "\n<Table  style='width:100%'>\n";       
      while ($row = $result->fetchArray()){
        if ($row['author_name'] != $LastAuthor){
          if ($ActuallyPrinted != 0){
            // So there was an author before, have to close it
            //echo "\n</Table>\n";
          }
          //echo "<br>\n";
          //echo "\n<Table  style='width:100%'>\n";            
          echo "<TR><TD colspan = 6 style='background-color:#8A8ACA'><B> " . $row['author_name'] ." </B></TD>";
          //echo "<TD style='background-color:#8A8ACA'> " . getLetter(). "</TD></TR>\n";
          $LastAuthor = $row['author_name'];
          $ActuallyPrinted += 2;
          $max -= 1;
        }
               
        echo "<TR><TD class='down'><input type='checkbox' name='Book_".$row['book_id'] . "' value='" ;
        $val = array( 'title' => $row['title'], 'author' => $row['author_name'], 'id' => $row['book_id'], 'url' => $row['epub_URL']);
        echo json_encode($val);
        echo "'/></TD>";

        echo "<TD class=title> ".$row['title'].       " &nbsp;</TD>";

        echo "<TD> ";
        if (isset($row['epub_URL']) && $row['epub_URL'] != '') {
          echo '<A HREF="' . $row['epub_URL']. '"> Android </A>';
          //echo '<A HREF="' . $row['epub_URL']. '"> <IMG src="_Resources/android-bw.png" height=25px> </A>';
        }
        echo " </TD>";
        
        echo "<TD> ";
        if (isset($row['mobi_URL']) && $row['mobi_URL'] != '') {
          echo '<A HREF="' .$row['mobi_URL']. '"> Kindle </A>';
        }
        echo " </TD>";
        echo "<TD> <I>".$row['tag_name'].   " </I></TD>";
        
        //echo "<TD> ".$row['book_id'].     " &nbsp;</TD>";
        
        if (isset($row['serie_name']) && $row['serie_name'] != '') {
          echo "<TD class=serie><I>" .$row['series_index']." </I>&nbsp; <B>".$row['serie_name']. "</B> &nbsp;</TD>";
        } else {
          echo "<TD class=serie></TD>";
        }
        //echo "<TD> ".$row['author_name'].   " </TD>";
        
        //echo "<TD>" . getLetter(). "</TD></TR>\n";
        echo "</TR>\n";
        $ActuallyPrinted += 1;
        $max -= 1;
        if ($max <= 0) {
            break;
        }
 
      }
      echo "\n</Table>\n";
      
      $result->finalize();
      $conn->close();
      return $ActuallyPrinted;
  } // PrintOnePage
    
  
  // ---------------------------------------------------------------------
  // 'Main()'
  // ---------------------------------------------------------------------
  
  
    
  $conn = new SQLite3($database,SQLITE3_OPEN_READONLY);
  if ($conn->connect_error) {
      echo "Connection failed: " . $conn->connect_error . "<br>\n";
      die("Connection failed: " . $conn->connect_error . "<br>\n");
  } 
  
    
  // ---------------------- Downloading
  if (isset($_POST['download']) || isset($_POST['download_x'])|| isset($_POST['download_y'])){
    ManageDownload();
  }
  elseif (isset($_GET['bulk'])) {
    ManageBulk($conn);
  }
  else {
    PrintOnePage($conn);
  }
    
      
   // Cf    calibre/resources/metadata_sqlite.sql

    //describeTable("books",$conn);
    //describeTable("authors",$conn);
    //describeTable("series",$conn);
    //describeTable("publishers",$conn);
    //describeTable("ratings",$conn);
    //describeTable("data",$conn);
    //describeTable("tags",$conn);
    //describeTable("books_authors_link",$conn);
    //describeTable("books_series_link",$conn);
    //describeTable("books_ratings_link",$conn);
    //describeTable("books_publishers_link",$conn);
    //describeTable("books_tags_link",$conn);
    //describeTable("custom_columns",$conn);
    
    //describeTable("authors_books_link",$conn);
      
  // -------- Table(books)
  //id: 2
  //title: L'Affaire Lerouge
  //sort: Affaire Lerouge, L'
  //timestamp: 2014-12-02 21:43:12+00:00
  //pubdate: 1865-12-01 23:00:00+00:00
  //series_index: 1
  //author_sort: Gaboriau, Émile
  //isbn:
  //lccn:
  //path: Gaboriau, Emile/L'Affaire Lerouge (2)
  //flags: 1
  //uuid: b049c314-858e-4a57-ac25-86333c2a2cdc
  //has_cover: 1
  //last_modified: 2017-12-26 10:52:12.625519+00:00
  //_________ End of Table(books)
  
  //-------- Table(authors)
  //id: 1
  //name: Gaboriau| Émile
  //sort: Gaboriau, Émile
  //link:
  //-------- End of Table(authors)
  
  //-------- Table(books_authors_link)
  //id: 1
  //book: 2
  //author: 1
  //-------- End of Table(books_authors_link)
  
  //--------   Table(books_series_link)
  //id: 1
  //book: 19
  //series: 1
  //-------- End of Table(books_series_link)

  //-------- Table(series)
  //id: 1
  //name: Fondation
  //sort: Fondation
  //-------- End of Table(series)

  //-------- Table(authors)
  //id: 1
  //name: Gaboriau| Émile
  //sort: Gaboriau, Émile
  //link:
  //-------- End of Table(authors)
  
  
  //--------Table(data)
  //id: 1
  //book: 2
  //format: EPUB
  //uncompressed_size: 4544362
  //name: L'Affaire Lerouge - Gaboriau, Emile
  //--------End of Table(data)

  //--------Table(books_tags_link)
  //id: 1
  //book: 2
  //tag: 1
  //--------End of Table(books_tags_link)

  //--------Table(tags)
  //id: 1
  //name: Policier
  //--------End of Table(tags)
   
//      #cf https://github.com/kovidgoyal/calibre et calibre/resources/metadata_sqlite.sql
//#     CREATE TABLE comments ( id INTEGER PRIMARY KEY,
//#                               book INTEGER NOT NULL,
//#                               text TEXT NOT NULL COLLATE NOCASE,
//#                               UNIQUE(book)
//# );
?>
    
 </BODY>
</HTML>