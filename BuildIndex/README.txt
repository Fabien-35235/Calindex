------------------------------------------------------
---- BuildIndex
------------------------------------------------------
A Module of Calisson

 From a Calibre Database, Builds an index ePub of ebooks that allows download books & series 

 Additional features compared to the equivalent fonction embeded in Calibre:
    * individual Ebooks can be downloaded from the index file
    * all ebooks from the same serie can be downloaded within a single zip
    * runs on a Synology NAS
    * epub generated can be check against https://github.com/idpf/epubcheck

 How to use it:
  1) Modify BuildIndex.ini, or use cmdline option (e.g. --Verbose = t or --Help)
     URL= the Base URL of your server, defaults to https://www.mysite.com/ebooks/
     Cover= the picture to use as a cover for the index, defaults to _Resources/Library.jpg
     Database = The file that holds the Calibre Database, defaults to metadata.db
     EpubIndex = The name of the index file to generate, defaults to index.epub
     Verbose
     configFile = the configuration file to use, defaults to BuildIndex.ini
  2) run BuildIndex.py
  3) read the generated index.epup
  
  Copyright (C) 2018  Fabien BATTINI, fabien.battini(At)gmail.com
  
----------------------------------------------------------
--Details:

+ Synology NAS
  Synology PYTHON 3.0 is lacking
        + PIP
        + a few modules that require compilation of C code
  Installing them would be painful for many users
  So BuildIndex embeds
        + a lightweight Epub Generator
        + a minimal args/cofig file parser, based on configparser & args parse
        + an auto-install of PIP and url_normalize
        + a dynamic self-configuration:
          if PIL.Image is not available, will use ImageMagic instead
     
+ Runs on windows and Linux (Synology)
    A common problem is Case sensitivity of filenames/directories
    BuildIndex verifies that the generated URL are valid,
    i.e. that the epub and cover pictures exist
     
+ automatically generates thumbnails, and stoes them in the Calibre directories

+ epub readers typically will NOT read files greater than 10MB
    To avoid issues, use one of the following options:
        +subjects = (a list of subjects)
        -subjects = (a list of subjects)