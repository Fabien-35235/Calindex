<?php
  include 'inits.php';
  $URL= basename(__FILE__);
  PrintPageStart("Calisson");
  ?>
  
<H1>Calisson, la bibliothèque familiale</h1>
  <p></p>
  <Table>
        
        
<TR><TD><B>Ebooks</B>&nbsp;<?php echo(date("d/m/Y H:i:s"));  ?> </TD></tr>
<tr><TD><A HREF='CaliNdex.php'>L'index en HTML paginé</A></TD></tr>
<tr><TD><A HREF='_Indexes/index.epub'>L'index COMPLET en epub.</A> Attention: plus de 10MB</TD></tr>
<tr><TD><A HREF='QueryIndex.php'>Les autres index en epub.</A> et aussi comment créer le sien propre</TD></tr>
<tr><TD><A HREF='Doc'>La Doc.</A>(en cours de développement)</TD></tr>
<tr><TD><A HREF='Doc'>Administration du site</A> (en cours de développement)</TD></tr>
 </Table>
    
 </BODY>
</HTML>