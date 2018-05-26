
-------------------------------------------------------------------------
CaliSSon is a very lightweight PHP service for Calibre
CaliSSon displays an Index as a dynamic HTML page
CaliSSon can serve books bookmarked in a ePub Index generated automatically
CaliSSon is published under MIT license
-------------------------------------------------------------------------
Copyright (c) 2018 fabien.battini(AT)gmail.com

+ Targets eReaders, such as Bookeen, Nook etc
  eReaders do not manage efficiently page scrolling
  but are efficient with "page turn"
  So CaliSSon manages both modes
  and also generates index as an ePub

+ the web service:
  + runs as a simple PHP page in an Apache server e.g. from a Synology NAS
  + lets the user download ebooks from the web page
  + lets the user to download a whole serie of books
  + allows the user to customize the Index.epub generated, by either
    allowing selected tags
    rejecting selected tags
    
+ the index.epub generated allows to download ebooks

+ the Calibre directory to be shared between a Windows PC and a Synology NAS 
  administrator can run Calibre on the PC
  administrator can serve epubs and indexes from the NAS, with a lightweight server

+ Manages completely epub (for Android)
+ Manages mobi (for Kindle) only throug web service (no mobi index generated)
  
  
More technical:

+ Configurable through CaliNdex.ini

+ Is pure GET requests,
    so, can be bookmarked
    so, the URL can be stored in an ebook,
    such as the one created by BuildIndex
    the Python-based ePub index generator
    which is included in CaliSSon

+ Allows the administrator to run BuildIndex.py from either Linux or Windows

-------------------------------------------------------------------------
---- Known restrictions 
    
+ allow only 1 TAG per ebook

-------------------------------------------------------------------------
---- TODO
Change Request CRX-YY-ZZ
X = 0 easy
X = 1 complex
YY = 99  General  
YY = 01  BuildIndex
YY = 02  Server
YY = 03  Test

General
CR1-99-01 allow multiple tags per ebook
CR0-99-02 change CaliNdex.ini to .conf & reuse the .conf as default values for every index.ini  [DONE]

BuildIndex
CR0-01-01 add mode --All generates all index.epub for .ini files [DONE]
CR0-01-02 change mode --Verbose with several values

Server
CR1-02-01 compute the appropriate number of lines from screen size
CR0-02-02 nicer CSS
CR1-02-03 document install on a Apache as HTML Pages
CR1-02-04 Synology package
CR0-02-05 QueryIndex shows only .ini from the current System : Now useless [DONE]

Test/demo
CR1-03-01 Build a test env with public domain epubs

-------------------------------------------------------------------------
---- HOW TO Install for a Synology NAS

Prerequisites:
  Apache HTTP server
  Python 3
  PHP 