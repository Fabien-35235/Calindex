<?php

  function PrintPageStart(){
    echo '<!DOCTYPE html>'."\n";
    echo '<html><head>'."\n";
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'."\n";
    echo '<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">'."\n";
    echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'."\n";
    echo '<head>'."\n";
    echo '  <link rel="stylesheet" href="_Resources/style.css">'."\n";
    echo '</head>'."\n";
    echo '<title>ebooks index </title>'."\n";
    echo '<BODY>'."\n";
  }

  

    
    function PrintOnePage($conn){
       
      PrintPageStart();
        echo "<H1>Calisson, la bibliothèque familiale</h1>\n";
        echo "<p></p>\n";
        echo "\n<Table >\n";
        
        $today = date("d/m/Y H:i:s"); 
        echo "<TR><TD><B>Ebooks</B> $today</TD></tr>\n";
        echo "<tr><TD><A HREF='CaliNdex.php'>L'index en HTML paginé</A></TD></tr>\n";
        echo "<tr><TD><A HREF='_Indexes/index.epub'>L'index COMPLET en epub.</A>Attention: plus de 10MB</TD></tr>\n";
        echo "<tr><TD><A HREF='QueryIndex.php'>Les autres index en epub.</A>et aussi comment créer le sien propre</TD></tr>\n";
        echo "<tr><TD><A HREF='Doc'>La Doc.</A>(en cours de développement)</TD></tr>\n";
        echo "<tr><TD><A HREF='Doc'>Administration du site</A>(en cours de développement)</TD></tr>\n";
   
        
        echo "\n</Table>\n";
    }
    
   
    
  
      PrintOnePage($conn);
    ?>
    
 </BODY>
</HTML>