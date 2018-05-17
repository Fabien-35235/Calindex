# -#*- cod#ing: la#t#in-#1 -*-


#pip install Pillow   #This is an option, if PIL is not installed, will use ImageMagic 
#pip install url-normalize

#sudo chgrp http _Indexes/
#sudo chown http _Indexes/
#   avec FileStation, donner au group http les doits de lectue et  riture sur _Indexes

#----------------------------------------------------------------------
# BuildIndex.py
#----------------------------------------------------------------------
DocString = """ An addition to Calibre (https://calibre-ebook.com/)

 From a Calibre Database, Builds an index ePub of ebooks that allows download books & series 

 Additional features compared to the equivalent fonction embeded in Calibre:
    * individual Ebooks can be downloaded from the index file
    * all ebooks from the same serie to be downloaded within a single zip
    * runs on a Synology NAS

 How to use it:
  1) Modify BuildIndex.ini, with:
     URL= the Base URL of your server, defaults to https://www.mysite.com/ebooks/
     Cover= the picture to use as a cover for the index, defaults to _Resources/Library.jpg
     Database = The file that holds the Calibre Database, defaults to metadata.db
     EpubIndex = The name of the index file to generate, defaults to index.epub
  2) run BuildIndex.py
  3) read the generated index.epup
  
  Copyright (C) 2018  Fabien BATTINI, fabien.battini(At)gmail.com
  
  Program distributed under the MIT License
  
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

  """

import os
import sys
import stat
import re
import tempfile
import datetime
from datetime import date
import zipfile
import argparse
import importlib
import sqlite3
import configparser
import signal
import json
import urllib, urllib.parse


class myLogger:
    """ Class for print and log file """
    def __init__(self):
      
        self.logfile = None      
        self.filehandle = None
        self.silent = None
        self.buffer = ''
        self.print("--- Start " + datetime.datetime.now().ctime())

    def startLog(self, silent, dirName):
        
        self.logfile = dirName + "/BuildIndex-" + date.today().isoformat() + ".log"
        self.filehandle = open(self.logfile, 'a', encoding='iso-8859-1', errors='replace')

        if (self.silent == None) and (silent == False):
            #Silent was not initiallized
            print("--- Bufferred")
            print(self.buffer)
            print("--- end of Bufferred")
            
        if (self.silent == None) and (self.filehandle != None):
            #Silent was not initiallized
            print(self.buffer, file=self.filehandle, flush=True)           
        
        self.buffer = ''
        self.silent = silent
        
        
    def print(self, *args):
        if (self.filehandle != None):
            print(*args, file=self.filehandle, flush=True)
#       truncated = list(map(lambda v:  str(str(v).encode(encoding='latin_1',errors='ignore')), args))
        #print("Trunc", truncated)

        locVal = ''
        for arg in args:
            nval = str(arg)        
            bytes = nval.encode(encoding ='ascii', errors='backslashreplace')
            nval = bytes.decode()
            locVal = locVal + ' ' + nval
             
        if (self.silent == None):
            #Not initialized, lets buffer
            self.buffer = self.buffer + ' ' + locVal +"\n"
                
        if (self.silent == False):
            print(locVal)
    
    def mustPrint(self, *args):
        if (self.filehandle != None):
            print(*args, file=self.filehandle, flush=True)
        
        locVal = ''
        for arg in args:
            nval = str(arg)        
            bytes = nval.encode(encoding ='ascii', errors='backslashreplace')
            nval = bytes.decode()
            locVal = locVal + ' ' + nval
        print(locVal)    
                 


print("Creating Logger " )        
myLog = myLogger()
print("Logger Created")


#----------------------------------------------------------------------
# EXTRA Module verifications
#----------------------------------------------------------------------

def CheckPip():
    """Check if Pip is installed. if not, installs it"""
    try:
        import pip 
        myLog.print("pip is available")
        return
    except ImportError:
        myLog.print("pip is NOT available")

    #we assume curl is available, which is the standard on synology
    
    if (os.path.isfile("get-pip.py")):
        myLog.print("  get-pip.py is available")
    else:
        myLog.print("  getting get-pip.py")
        os.system("curl https://bootstrap.pypa.io/get-pip.py -o get-pip.py")
    
    os.system("python3 get-pip.py --force-reinstall")


     
def ImportModule(Descr, Err):
    try:  
        Mod = importlib.import_module(Descr)
        myLog.print("Module ", Descr, " is available")
        return Mod
    except ImportError:
        myLog.print("Module ", Descr, " is NOT available ", Err)
        return None

def CheckModule(Descr):
    Mod = ImportModule(Descr, "Download it with pip")
    if (Mod):
        return Mod
    
    #https://pip.pypa.io/en/stable/reference/pip_install/#usage
    os.system("python3 -m pip install   " + Descr) #--only-binary :all:
    
    Mod = ImportModule(Descr, "Abort")
    if (Mod):
        return Mod
    
    myLog.print(Descr," is NOT available after an attempt to install it via pip")
    myLog.print("install it manually through the following command:")
    myLog.print("python3 -m pip install " + Descr)
    sys.exit(0)




def Resize(inImage, outImage, width, height):
    if (Image):
        im = Image.open(inImage) 
        im.thumbnail((width, height), Image.ANTIALIAS)
        im.save(outImage, "JPEG")
    else:
        # https://www.imagemagick.org/script/convert.php
        os.system('convert "'+ inImage + '" -resize '+ str(width)+"x"+str(height)+' -strip "' + outImage +'"')
    #myLog.print('Resize("', inImage, '", "', outImage, '", "',width, 'x', height,")")
   
    
#----------------------------------------------------------------------
# EPUB library
#----------------------------------------------------------------------

class EpubChapter:
    """ a chapter in the eBook"""
    globID = 0 # a class variable
    def __init__(self, title, filename, properties=None):
        self.id = str(EpubChapter.globID)
        EpubChapter.globID += 1
        self.title = title
        self.filename = filename
        self.properties = properties
        self.content = "<?xml version='1.0' encoding='utf-8'?>\n"
        self.content += "<!DOCTYPE html>\n"
        self.content += '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" epub:prefix="z3998: http://www.daisy.org/z3998/2012/vocab/structure/#" lang="fr" xml:lang="fr">'
        self.content += "\n"
        
    def GenerateOpf(self):
        if (self.properties):
            prop = 'properties="'+self.properties+'" '
        else:
            prop=''
        return '   <item href="'+self.filename +'" id="chapter_'+ self.id +'" '+prop+' media-type="application/xhtml+xml"/>\n'
 
    def GenerateSpine(self):
        return '   <itemref idref="chapter_'+ self.id +'"/>\n'

    def GenerateToc(self):
        toc =  '  <navPoint id="navpoint_'+ self.id +'" playOrder="'+ self.id +'">\n'
        toc += '    <navLabel>\n'
        toc += '      <text>'+ self.title +'</text>\n'
        toc += '    </navLabel>\n'
        toc += '    <content src="'+ self.filename +'"/>\n'
        toc += '  </navPoint>\n'
        return toc
    
    def GenerateNav(self):
        nav  = '   <li>\n'
        nav += '      <a href="'+self.filename +'" >' + self.title +'</a>\n'
        nav += '   </li>\n'
        return nav
    
    
class EpubPicture:
    """ a picture in the eBook"""
    globID = 0 # a class variable
    def __init__(self,internal, real):
        self.id = str(EpubPicture.globID)
        EpubPicture.globID += 1
        self.internal = internal
        self.real = real
    
    def GenerateOpf(self):
        return '   <item href="'+self.internal +'" id="static'+ self.id +'" media-type="image/jpeg"/>\n'
        

class Epub:
    """ An EPUB ebook"""
    def __init__(self, title, filename, ide, lang):
        self.id = ide
        self.title = title
        self.filename = filename
        self.lang = lang
        self.contentdir = "EPUB"
        self.opfname = self.contentdir + "/content.opf"
        self.chapters = []
        self.pictures = []
        self.coverPic = None
        self.style = "body{margin-left:2%;margin-right:2%;margin-top:2%;margin-bottom:2%}\n"
        self.style += ".nomargin{margin:0}\n"
        self.style += "p{text-indent:1.4em;margin-left:0;margin-right:0;text-align:justify;margin-top:.42em;margin-bottom:.42em;font-family:Georgia,serif;font-size:109%}\n"
        self.style += ".p0{text-indent:0;margin-left:0;text-align:center;margin-top:0;margin-bottom:0}\n"
        self.style += ".p1{text-indent:0;margin-left:0;text-align:center;margin-top:4em;margin-bottom:-.083em}\n"
        self.style += ".p3{text-indent:3em;margin-left:18em}\n"
        self.style += ".p4{text-indent:3em;margin-left:27em}\n"
        self.style += ".p5{text-indent:3em;margin-left:6em}\n"
        self.style += ".p6{text-indent:0;margin-left:0;text-align:center;margin-top:4em;margin-bottom:1.5em}\n"
        self.style += ".p7{text-indent:0;margin-left:0;text-align:center}\n"
        self.style += ".p8{text-indent:0;margin-left:-1.2em;margin-right:-1.2em;text-align:center}\n"
        self.style += ".p9{text-indent:0;margin-left:0}\n"
        self.style += ".p10{text-indent:0;margin-left:2.4em}\n"
        self.style += ".p11{text-indent:0;margin-left:2.3em}\n"
        self.style += ".t1{font-size:1.7em}\n"
        self.style += ".t2{font-size:1.8em}\n"
        self.style += ".t3{font-size:1.3em}\n"
        self.style += ".t4{text-decoration:underline}\n"
        
    def AddChapter(self,chapter):
        self.chapters.append(chapter)
        return chapter
    
    def AddPicture(self, picture):
        self.pictures.append(picture)
        return picture
    
    def SetCover(self,filename, title, internal,real):
        chapter = EpubChapter(title,filename)
        chapter.content += " <head>\n"
        chapter.content += "  <title>"+title+"</title>\n"
        chapter.content += " </head>\n"
        chapter.content += " <body><img src='"+internal + "' alt='Cover'></img>\n"
        # chapter.content += " </body>\n"
        # chapter.content += "</html>\n"
    
        self.coverPic = EpubPicture(internal, real)
        self.coverChapter = chapter
        return chapter
        
    def Generate(self):
        myLog.print("Generate")
        ebookFile = zipfile.ZipFile(self.filename,'w')
        ebookFile.writestr('mimetype', "application/epub+zip", None)
        cont = '<?xml version="1.0" encoding="UTF-8" ?>\n'
        cont += '<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">\n'
        cont += '   <rootfiles>\n'
        cont += '     <rootfile full-path="'+self.contentdir + '/content.opf" media-type="application/oebps-package+xml"/>\n'
        cont += '   </rootfiles>\n'
        cont += ' </container>\n'
        
        ebookFile.writestr('META-INF/container.xml', cont, None)
        
        opf = "<?xml version='1.0' encoding='utf-8'?>\n"
        opf += ' <package unique-identifier="id" version="3.0" xmlns="http://www.idpf.org/2007/opf" prefix="rendition: http://www.idpf.org/vocab/rendition/#">\n'
        opf += '  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:opf="http://www.idpf.org/2007/opf">\n'
        opf += '    <meta property="dcterms:modified">2018-04-08T18:12:34Z</meta>\n'  #Bug HERE: DATE
        opf += '    <meta content="Calisson 1.0" name="generator"/>\n'
        opf += '    <dc:identifier id="id">'+self.id+'</dc:identifier>\n'
        opf += '    <dc:title>'+self.title+'</dc:title>\n'
        opf += '    <dc:language>'+self.lang+'</dc:language>\n'
        opf += '    <dc:creator id="creator">Calisson 1.0</dc:creator>\n'
        opf += '    <dc:subject>Index</dc:subject>\n'
        opf += '    <dc:description>List of books in the library</dc:description>\n'
        opf += '    <dc:date>2011-09-25</dc:date>\n'                                #Bug HERE: DATE
        if (self.coverPic):
            opf += '    <meta name="static'+ self.coverPic.id   +'" content="cover-img"></meta>\n'
        opf += '  </metadata>\n'
        opf += '  <manifest>\n'
        #BUG?   opf += '   <item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>\n'
       
        for chapter in self.chapters:
            opf += chapter.GenerateOpf()
        
        for picture in self.pictures:
            opf += picture.GenerateOpf() 
        
        opf += '     <item href="toc.ncx" id="ncx" media-type="application/x-dtbncx+xml"/>\n'
        opf += '     <item href="nav.xhtml" id="nav" media-type="application/xhtml+xml" properties="nav"/>\n'
        opf += '     <item href="style.css" id="style_nav" media-type="text/css"/>\n'
        opf += '   </manifest>\n'
        
        opf += '  <spine toc="ncx">\n'
        #opf += '    <itemref idref="cover" linear="no"/>\n'
        #opf += '    <itemref idref="nav"/>\n'
        for chapter in self.chapters:
            opf += chapter.GenerateSpine()
            
        opf += '  </spine>\n'
        #opf += '  <guide>\n'
        #if self.coverPic:
        #   opf += '    <reference type="cover" title="Cover" href="'+self.coverPic.internal +'"/>\n'        ############################ BUG : href = le .xhtml
        #    pass
        #opf += '  </guide>\n'
        opf += '</package>\n'
        
        ebookFile.writestr(self.opfname, opf, zipfile.ZIP_DEFLATED)

        nav  = '<?xml version="1.0" encoding="UTF-8"?>\n'
        nav += '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:lang="en-US" lang="en-US">\n'
        nav += '   <head>\n'
        nav += '      <title>EPUB 3 Navigation Document</title>\n'
        nav += '      <meta charset="utf-8"/>\n'
        nav += '      <link rel="stylesheet" type="text/css" href="style.css"/>\n'
        nav += '   </head>\n'
        nav += '   <body>\n'
        nav += '      <nav epub:type="toc">\n'
        nav += '       <ol>\n'
        for chapter in self.chapters:
            nav += chapter.GenerateNav()
        nav += '        </ol>\n'            
        nav += '       </nav>\n'
        #management of nav is still unclear
        #nav += '       <nav epub:type="page-list">\n'
        #nav += '         <ol>\n'       
        #nav += '            <li><a href="georgia.xhtml#page758">758</a></li>\n'
        #nav += '         </ol>\n'
        #nav += '        </nav>\n'       
        nav += '     </body>\n'
        nav += '    </html>\n'
        ebookFile.writestr(self.contentdir +"/nav.xhtml", nav, zipfile.ZIP_DEFLATED)
        
        for chapter in self.chapters:
            ebookFile.writestr(self.contentdir +"/"+chapter.filename, chapter.content+"</body></html>\n", zipfile.ZIP_DEFLATED)
           
            
        for picture in self.pictures:    
            ebookFile.write(picture.real, self.contentdir +"/"+picture.internal)
            
        ebookFile.writestr(self.contentdir +"/style.css", self.style, zipfile.ZIP_DEFLATED)
        
        
        toc = "<?xml version='1.0' encoding='utf-8'?>\n"
        toc += '<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">\n'
        toc += ' <head>\n'
        toc += '  <meta content="'+ self.id +'" name="dtb:uid"/>\n'
        toc += '  <meta content="0" name="dtb:depth"/>\n'
        toc += '  <meta content="0" name="dtb:totalPageCount"/>\n'
        toc += '  <meta content="0" name="dtb:maxPageNumber"/>\n'
        toc += ' </head>\n'
        toc += ' <docTitle>\n'
        toc += '  <text>' +self.title+ '</text>\n'
        toc += ' </docTitle>\n'
        toc += ' <navMap>\n'
        for chapter in self.chapters:
            toc += chapter.GenerateToc()
        
        toc += ' </navMap>\n'
        toc += '</ncx>\n'
        ebookFile.writestr(self.contentdir +"/"+"toc.ncx", toc, zipfile.ZIP_DEFLATED)
        ebookFile.close()
        
#----------------------------------------------------------------------
# Building the Index Epub
#----------------------------------------------------------------------

def Help():
    myLog.print(DocString)

def clean(myString):
    """ Used to clean the 'summary' of a book, which may hold some innapropriate HTML"""
    res = re.sub('&lt;','<',myString)
    res = re.sub('&gt;','>',res)
    res = re.sub('<p([^>]*)>','<p>',res)
    res = re.sub('</*(genre|div|span|h1|h2|h3|font|table|tbody|tr|td|br)([^>]*)>', '', res)
  
    return res

#cf https://www.w3schools.com/tags/ref_urlencode.asp
def NormalizeURL(myString):
    res =urllib.parse.quote_plus(myString,safe='/:',errors='xmlcharrefreplace')
    return res

    res = re.sub(',','%2C',myString)
    res = re.sub(" ",'%20',res) 
    res = re.sub("!",'%21',res)
    res = re.sub('"','%22',res)
    res = re.sub('#','%23',res)
    res = re.sub('\$','%24',res) 
    #res = re.sub('%','%25',res) #SURTOUT PAS, on a deja remplac avec des % avec url_normalize 
    res = re.sub("&",'%26',res)
    res = re.sub("'",'%27',res)
    res = re.sub('\(','%28',res)
    res = re.sub('\)','%29',res)
    res = re.sub('é','%C3%A9',res)
    res = re.sub('è','%C3%A8',res)
    res = re.sub('à','%C3%A0', res)
    #myLog.print("normalize(", myString,")=", res)
    return res

def deNormalize(myString):
    #res =urllib.parse.unquote(myString)
    #res = re.sub('\udcc3\udca9','&eacute;',res)
    #return res

    res = re.sub('%2C',',',myString)
    res = re.sub('%20'," ",res) 
    res = re.sub('%21',"!",res)
    res = re.sub('%22','"',res)
    res = re.sub('%23','#',res)
    res = re.sub('%24','\$',res) 
    res = re.sub('%26',"&",res)
    res = re.sub('%27',"'",res)
    res = re.sub('%28','\(',res)
    res = re.sub('%29','\)',res)
   
    res = re.sub('%C3%A9','é',res)
    res = re.sub('%C3%A8','è',res)
    res = re.sub('%C3%A0','à',res)
    u=chr(56515)+chr(56489)
    res = re.sub(u,'é',res)
    #myLog.print("deNormalize(",encode_for_xml(myString),")=",encode_for_xml(res))
    return res

def encode_for_xml(myString):
    #myLog.print("encode_for_xml(", myString, ")")
    bytes = myString.encode(encoding = 'ascii', errors = 'xmlcharrefreplace')
    #myLog.print("==>(", res, ")")
    res = bytes.decode()
    #myLog.print("==>(", res, ")")
    return res

def SurNormalize(myString):
    """ quote, specific to config files """
    
    res =urllib.parse.quote_plus(myString,safe='/:',errors='xmlcharrefreplace')
    res = res.replace('%','%%')
    return res

    res = re.sub(' ','%%20',myString) #Specific to config files
    return res
    
def stringify(myString):
    res = re.sub('\W','_',myString)
    return res
    

class Book:
    """ Describes a Book """
    globID = 0 # a class variable
       
    def __init__(self, myAuthor, bookID, Title, Serie, Index, epub_URL, mobi_URL, tag_name, Cover, Descr):
        """ Constructor called when reading a Calibre Database """
        self.author = myAuthor
        self.authorName = myAuthor.name
        self.title = Title
        #myLog.print("Book(" + str(myAuthor.name) +","+ str(bookID)+","+  str(Title)+","+  str(Serie)+","+  str(Index)+","+  str(epub_URL) +","+  str(mobi_URL)+","+  str(tag_name) +")")
        self.name = self.title 
        self.subject=tag_name if tag_name else ''
        self.description= clean(Descr) if Descr else ''
        self.serie= Serie if Serie else '' # NormalizeURL(Serie) if Serie else ''
        self.index = int(Index) if Index else '1'
        self.epub = epub_URL if epub_URL else ''
        self.group="book" #default value
        self.id = Book.globID
        Book.globID +=1
        self.page = 'Book_'+str(self.id)+'.xhtml'
        self.cover= Cover
        self.thumbnail = 'Thumb_'+str(self.id)+'.jpg'
        self.HashOfSubjects = {}  #BUG HERE: should be in Library
    
 
        if (self.serie):
            self.group = self.serie
        else:
            self.group = self.subject
        self.sortname = str(self.group) + "_"+ str(self.index) + "_" + str(self.title)
        
        #myLog.print("               "+ self.title+ " <"+ self.group + "> " + self.cover + " - " + self.epub)

 
    def __lt__(self, other):    
        return self.sortname < other.sortname
    
    
    def WriteBook(myBook, myLibrary):
        """ writes the fragments of the index file for this book
        and appends it in myLibrary.ListOfBookPages
        returns the fragment of AuthorText for this book
        """
       
        AuthorText = ''
        
        bookText = EpubChapter(title=myBook.title, filename=myBook.page)
        bookText.content += u'<head></head><body><h1>'+myBook.title+'</h1>\n'
        bookText.content += '<p></p>\n'
    
        myGroup = myBook.serie + "  <i>" + myBook.subject + "</i>"
            
        if (myBook.author.curGroup != myBook.group):
           
            if (myBook.serie):
                AuthorText += '<tr><td  style="background-color:#8A8ACA">' + myGroup +'</td>'
                AuthorText += '    <td  style="background-color:#8A8ACA">' 
                
                AuthorText += '<a href="'+ NormalizeURL(myLibrary.BaseURL+'index.php') + '?bulk'
                AuthorText +=  '&amp;author=' + NormalizeURL(myBook.author.name) 
                AuthorText +=  '&amp;serie=' + NormalizeURL(myBook.serie)
                AuthorText +=  '">download </a></td></tr>\n'
                #print ('&author=' + NormalizeURL(myBook.author.name) + '&serie=' +NormalizeURL(myBook.serie))
            else:
                AuthorText += '<tr><td colspan="2" style="background-color:#8A8ACA">'
                AuthorText += myGroup + '</td></tr>\n'
          
            
            myBook.author.curGroup = myBook.group
        
           
        bookText.content += '<h2><a href="'+myBook.author.page+'">' + myBook.author.firstname + " " +myBook.author.lastname + '</a></h2>\n'
        bookText.content += '<p>'+ myBook.subject + ' \n'
        
        #bookText.content += '<td>' + myBook.language + '</td>'
        if (myBook.serie != ''):
            bookText.content += '<b>' + myBook.serie + "</b> <i>" + str(myBook.index) + '</i>\n'
            SerieText = '<b>[' + str(myBook.index) + ']</b> '
        else:
            SerieText = ''
            
        shorttitle = myBook.title[:42]
        AuthorText += '<tr><td>'+SerieText +'<a href="'+myBook.page+ '">'+shorttitle+'</a></td>'
     
      
        bookText.content += '</p>\n'
        if (myBook.epub != ''):
            bookText.content += '<p>Android Download <a href="' + NormalizeURL(myLibrary.BaseURL+myBook.epub) + '">'+myBook.name+'</a> ('+ str(myBook.id) +')</p>\n'
            AuthorText += '<td><a href="' + NormalizeURL(myLibrary.BaseURL+myBook.epub) + '">Android (epub)</a></td>'
            myEpub = myLibrary.filebase + myBook.epub
            if (not os.path.isfile(myEpub)):
                myLog.print("ERROR",myBook.author.name,':', shorttitle, ':', myEpub,' does NOT exist')
                #sys.exit(0)
  
        else:
            AuthorText += '<td></td>'
        AuthorText += '</tr>\n'
       
        bookText.content +=  myBook.description +'\n'
        bookText.content += '<p><img src="' + myBook.thumbnail + '" alt="Cover Image"></img></p>\n'
        #bookText.content += '</body></html>'
        
        myLibrary.ListOfBookPages.append(bookText)
        
          
        myCover = myLibrary.filebase + myBook.cover
        thumbnail = re.sub('cover','thumbnail',myCover)
        if (os.path.isfile(myCover) and not os.path.isfile(thumbnail)):
            myLog.print(myBook.title,":",myBook.author.firstname + " " +myBook.author.lastname,": generating thumbnail")
            Resize(myCover, thumbnail, 256, 256)
        
        if (os.path.isfile(  thumbnail)): 
            pict = EpubPicture(internal=myBook.thumbnail, real=thumbnail)        
            myLibrary.ListOfCovers.append(pict)
    
        return AuthorText



        
class Author:
    """ Describes an Author, with a list of Books """
    globID = 0 # a class variable
    def __init__(self, Name):
        #self.dirname = dirname
        self.ListOfBooks = []
        self.name= Name
       
        self.id = Author.globID
        Author.globID +=1
        self.page = 'Author_'+str(self.id)+'.xhtml'
        self.curGroup = ''   #used for printing, will be serie or subject
   
          
        # example: Balzac, Honore de
        #myLog.print("Author:<",Name,">\n")
        m = re.match("([^ ,]+)([ ,]*)(.*)", Name)
        if m:
            self.lastname = m.group(1)
            self.lastname = re.sub("_","",self.lastname)
            self.firstname = m.group(3)
            self.firstname = re.sub("_","",self.firstname)
            #self.name = self.firstname + " " + self.lastname
            #myLog.print("Author:<",self.lastname,",",self.firstname,">\n")
             
    def __repr__(self):
        return '{}: {} {}'.format(self.__class__.__name__,
                                  self.firstname,
                                  self.lastname)        
    def __cmp__(self, other):
        return self.name.__cmp__(other.name)


    
    def readAuthor(self):
        self.hashOfGroups = {}
        for myBook in self.ListOfBooks:
            if (myBook.group):
                if (myBook.group in self.hashOfGroups):
                    self.hashOfGroups[myBook.group].append(myBook)
                else:
                    self.hashOfGroups[myBook.group] = [myBook]
        
        
    def printAuthor(self, Lib):
        for book in self.listOfBooks:
            book.WriteBook(Lib)
            
    def BuildAuthorPage(self, myLibrary):
        """ Creates the Chapter for this Author,
        and appends it to myLibrary.ListOfAuthorPages
        Returns the fragment of text for the Category"""
              
        if (len(self.ListOfBooks) == 0):
            return '';
        
        CatText = ''
        myFirstChar =  self.name[0].upper()
        if (myLibrary.CurrentLetter < myFirstChar):
            CatText += "<tr><td style='background-color:#8A8ACA'><b>"+myFirstChar+'</b></td></tr>\n'
            myLibrary.CurrentLetter = myFirstChar
        CatText += "<tr><td><a href='"+self.page + "'>"+self.name+'</a></td></tr>\n'
        
        authorBook = EpubChapter(title=self.name, filename=self.page)
        authorBook.content += u'<head></head><body><h1>'+self.firstname+' ' + self.lastname + '</h1>\n'
        authorBook.content += '<p></p>\n'
        authorBook.content += '<table style="width:100%">\n'
        for book in self.ListOfBooks:
            authorBook.content += book.WriteBook( myLibrary)
        authorBook.content += '<tr><td></td><td></td></tr>\n'
        authorBook.content += '<tr><td></td><td></td></tr>\n'
        authorBook.content += '<tr><td></td><td></td></tr>\n'
        authorBook.content += '<tr><td colspan="2" style="background-color:#CA8A8A; text-align:center;"> <b><a href="index.xhtml"> Index A-Z</a></b></td></tr>\n'
        authorBook.content += '</table>\n'    
        #authorBook.content += '</body></html>'
        
        myLibrary.ListOfAuthorPages.append(authorBook)
        
        return CatText

       

class Library:
    """Describes a Library, i.e.
    a collection of Authors"""
    
    def buildRequest(self):
        """builds the request for SQLITE3"""
        
        q = "SELECT b.id AS book_id,  b.title, b.series_index, aut.sort AS author_name ";
        q += "   , ser.name AS serie_name, ser.id AS serie_id "
        q += "   , b.path || '/' || epub.name || '.epub' AS epub_URL "
        q += "   , b.path || '/' || mobi.name || '.mobi' AS mobi_URL "
        q += "   , b.path || '/cover.jpg' AS cover "
        q += "   , tg.name AS tag_name "
        q += "   , co.text AS description "
        q += " FROM books b "
        q += " LEFT OUTER JOIN books_authors_link bal  ON bal.book=b.id "
        q += " LEFT OUTER JOIN authors            aut  ON bal.author=aut.id "
        q += " LEFT OUTER JOIN books_series_link  bsl  ON bsl.book=b.id "
        q += " LEFT OUTER JOIN series             ser  ON bsl.series=ser.id "
        q += " LEFT OUTER JOIN data               epub ON b.id=epub.book AND epub.format ='EPUB' "
        q += " LEFT OUTER JOIN data               mobi ON b.id=epub.book AND epub.format ='MOBI' "
        q += " LEFT OUTER JOIN books_tags_link    btl  ON btl.book=b.id "
        q += " LEFT OUTER JOIN tags               tg   ON btl.tag=tg.id "
        q += " LEFT OUTER JOIN comments           co   ON b.id=co.book "
        q += " ORDER BY author_name, serie_id, b.series_index, title "
        #q += " ORDER BY author_sort, serie_id, b.series_index, title " #Ne trie pas correctement, on ne sait pas pourquoi
        return q
              
    def excluded(self, tag):
        if (self.onlyTags):
            return  len(list(filter(lambda x: x == tag, self.onlyTags))) == 0
        if (self.excludedTags):
            return  len(list(filter(lambda x: x == tag, self.excludedTags))) != 0
        return False
    
    
    def __init__(self, baseURL, myCover,dataBase, fileBase, onlyTags=None, excludedTags=None):
        self.BaseURL = baseURL
        self.coverFile = myCover
        self.ListOfAuthorPages = []        
        self.ListOfBookPages = []
        self.ListOfCovers = []
        self.CurrentLetter = 'A'
        self.database = dataBase
        self.filebase = fileBase
        self.Authors = []
        self.onlyTags = onlyTags
        self.excludedTags = excludedTags
        self.connection = sqlite3.connect(dataBase)
        self.connection.row_factory = sqlite3.Row
        cur = self.connection.cursor()
        cur.execute(self.buildRequest())
        curRow= cur.fetchone()
        curName=""
        curAuthor=None
        #myLog.print(curRow.keys())
        while(curRow != None):
            #myLog.print("TEST",  curRow['author_name'])
            #myLog.print("TEST", curRow['title'],  curRow['tag_name'])
            if (self.excluded(curRow['tag_name'])):
                #myLog.print("EXCLUDED", curRow['title'], curRow['tag_name'])
                curRow= cur.fetchone()
                continue
            
            
            if (curRow['author_name'] != curName):
                curAuthor = Author(curRow['author_name'])
                curName = curRow['author_name']
                self.Authors.append(curAuthor)
                
            curBook = Book(curAuthor,
                           curRow['book_id'],
                           curRow['title'],
                           curRow['serie_name'],
                           curRow['series_index'],
                           curRow['epub_URL'],
                           curRow['mobi_URL'],
                           curRow['tag_name'],
                           curRow['cover'],
                           curRow['description'])

            curAuthor.ListOfBooks.append(curBook)
            curRow= cur.fetchone()
            
        #self.allAuthors.sort(key=lambda x: x.name)
        for myAuthor in self.Authors:
            myLog.print("Author ",myAuthor.lastname,myAuthor.firstname)
            
        myLog.print("Author generation DONE")    
            
    def Build(self,Filename, maxAuthors):
        today = date.today().ctime()
        myLog.print("Starting to BUILD(", Filename,maxAuthors,')')   
        book = Epub('Bibliotheque ' + str(today), Filename, 'ID1234567890', 'fr')
      
        
        book.SetCover("cover.xhtml", "Cover", "bookCover.jpg", self.coverFile)
      

        GlobIndex=EpubChapter(title='Index', filename='index.xhtml')#, properties = 'nav')
        
        GlobIndex.content += " <head>\n"
        GlobIndex.content += "  <title>A-Z Index</title>\n"
        GlobIndex.content += " </head>\n"
        GlobIndex.content += " <body>\n"

        GlobIndex.content += u'<h1>A-Z Index</h1>\n'
        excludeURL = ''
        onlyURL = ''
        
        GlobIndex.content += "<p> Date of the Library = " + datetime.datetime.now().ctime() + "</p>\n"
         
        if self.excludedTags:
            GlobIndex.content += '<p>Excluded tags: '
            excludeURL = '&avoidSubjects='
            for tag in self.excludedTags:
                
                GlobIndex.content += encode_for_xml(tag) + ' '  #because this can be bizzare unicode due to deNormalize
                excludeURL += NormalizeURL(tag) + ','
                  
            GlobIndex.content += '</p>'    
        
        if self.onlyTags :
            GlobIndex.content += '<p>Only the following tags: '
            onlyURL = '&onlySubjects='
            for tag in self.onlyTags:
                GlobIndex.content += encode_for_xml(tag) + ' '
                onlyURL += NormalizeURL(tag + ',')
            GlobIndex.content += '</p>'    
                
        
        reloadURL  = self.BaseURL+ 'BuildIndex.php?EpubIndex='+Filename # onlyURL+excludeURL #NO, these are useless now, because everythng is stores in config file 
        
        GlobIndex.content += '<p style="text-align:center;"><a href="'+ reloadURL + '">Reload this index book</a></p>\n'
        
        
        GlobIndex.content += '<p></p>\n'
        GlobIndex.content += '<table style="width:100%">\n'
        GlobIndex.content  += "<tr><td style='background-color:#8A8ACA'><b>A</b></td></tr>"
            
        curAuthor = 0    
        for myAuthor in self.Authors:
            curAuthor += 1
            #myLog.print ("Printing ",myAuthor.firstname,myAuthor.lastname )
            GlobIndex.content += myAuthor.BuildAuthorPage(self)
            if (maxAuthors and (curAuthor >= maxAuthors)):
                break
            
        book.AddChapter(book.coverChapter)
        GlobIndex.content += '</table>\n'
        
        book.AddChapter(GlobIndex)
        
        for page in self.ListOfAuthorPages:
            book.AddChapter(page)
        
        for page in self.ListOfBookPages:
            book.AddChapter(page)
        
        book.AddPicture(book.coverPic)
        
        for page in self.ListOfCovers:
            book.AddPicture(page)  
       
        
        myLog.print("End of BUILD")   
        # write to the file
        book.Generate()
        myLog.print("End of Generate")   

#--------------------------------------------------------------
#   Args library
#--------------------------------------------------------------


class Arg:
    def __init__(self, key,  defaultVal = None, helper = None, typedescr = None):
        self.key = key
        self.default = defaultVal
        self.value = None
        self.helpers= helper
        self.typedescr = typedescr
 
       
    def getConfig(self):
        
        line = self.key + ' = ' 
        if (isinstance(self.value, list)):
            first = True
            for v in self.value:
                if (first != True):
                    line = line +','
                first = False
                line = line + SurNormalize(v)
        else:
            line = line + SurNormalize(str(self.value))  
            
        
        return line

    def getHelp(self):
        return self.key +  str(self.value) + ' : default=' + str(self.default) + ' ' + self.helpers                       
        #return self.getConfig() + ' : default=' + str(self.default) + ' ' + self.helpers     BUUUUUUUUUUUUUUUUUG 
 

def isProcessRunning(process_id):
    pass
    try:
        os.kill(process_id, 0)
        return True
    except OSError:
        return False
    

        
class ArgsList:
    def __init__(self, helper):
        self.help = helper
        self.arglist = {}
        self.parser = argparse.ArgumentParser(description=helper)
        self.containedInConfig = True #All meaningful configurations are contained in the config file,
        self.addArg('configFile',  defaultVal='BuildIndex.ini', helper='Configuration file (default: BuildIndex.ini)')
        self.addArg('Log','.','the path where logs are generated')    
        self.addArg('Verbose',False,'Print this Help')
        self.addArg('Silent',False,'Does not print on STDOUT, only in log file')
        
        self.config = configparser.ConfigParser()

        
    def addArg(self, key, defaultVal = None, helper = None,  typedescr=None):
        self.arglist[key] = Arg(key, defaultVal, helper, typedescr=typedescr)
        self.parser.add_argument('--'+key, dest=key, action='store', default=None, help=helper)
        self.configFile = 'BuildIndex.ini'
        
    def doParse(self):
        self.args = self.parser.parse_args()
        
        #self.configFile has already a default value
        if (self.args.configFile and os.path.isfile( self.args.configFile)):
            self.configFile = self.args.configFile
            
        if (os.path.isfile(self.configFile)):
            self.config.read(self.configFile,encoding='utf-8')
            myLog.print("Read configuration file ", self.configFile)
            fileValues = self.config['BuildIndex']
            
        else:
            myLog.print("ERROR : configFile ",self.configFile,"does NOT exist" )
            self.configFile = None
            fileValues = None
            self.containedInConfig = False
            
        self.arglist['configFile'].value = self.configFile
        
        myLog.print("doParse File=", self.configFile)
        for key, arg in self.arglist.items():
            if (key != 'configFile'):
                val = fileValues.get(key,arg.default) if fileValues else None
                
                if (hasattr(self.args,key) and getattr(self.args,key)):
                    val = getattr(self.args,key)
                    if (key != 'Verbose'):
                        self.containedInConfig = False
                        
                #myLog.print("doParse", val, key)
                
                if (arg.typedescr == 'int') and val:
                    arg.value = int(val)
                elif (arg.typedescr == 'list') and val:
                    arg.value = list(map(deNormalize,  val.split(',')))
                else:
                    arg.value = val
                
                myLog.print("doParse-->", arg.value, key)

        myLog.startLog(self.getValue('Silent'), self.getValue('Log'))
        if self.getValue('Verbose'):
            self.Verbose()
       
        
    def getValue(self, key):
        if key in self.arglist:
            return self.arglist[key].value
        return None
    
    def Verbose(self):
        myLog.print("\n\nBuildIndex.py", self.help)
        for key,arg in self.arglist.items():
            myLog.mustPrint(arg.getHelp())
        myLog.print('\n\n')
    
    def createLock(self, lockfile):
        if (os.path.isfile(lockfile)):
            os.remove(lockfile)
        try:
            with open(lockfile, 'x') as myFile:
                content = {}
                content['date']= datetime.datetime.now().ctime()
                content['pid'] = os.getpid()
                json.dump( content, myFile)
            myLog.mustPrint("Locked ", lockfile)
            return True
        except OSError:
            myLog.mustPrint("ERROR: Lock file  ", lockfile, " CANNOT BE CREATED")
            return False
    
    def LockConfig(self, lockfile):
        self.lockfile = lockfile
        myLog.mustPrint("Locking ", lockfile)
        if (os.path.isfile(lockfile)):
            with open(lockfile,"r") as reading:
                vals = json.load(reading)
                pid = vals['pid']
                
            myLog.print('WARNING: PID ',pid, ' has locked the epub')
            for i in range(0,20):
                foo = isProcessRunning(pid)
                if (foo == True):
                    myLog.mustPrint("WARNING:",lockfile," is alredy in use, waiting 1 seconds")
                    time.sleep(1)
                else:
                    myLog.mustPrint('WARNING: PID ',pid, ' has stopped abnormally') 
                    return self.createLock(lockfile)
                     
            myLog.mustPrint("WARNING: PID ",pid, ' is probably blocked')
            exit(-1)
        myLog.mustPrint("Lock file ", lockfile, " does not exist")    
        return self.createLock(lockfile)    
       
            

        #20 seconds elapsed, no unlock
        #Kill the process
       
        # BUG here, should be an error lock file unavailable 
            
    def UnlockConfig(self):
        os.unlink(self.lockfile)
        myLog.mustPrint("INFO: unlocked ", self.lockfile)


    def DumpConfig(self, filename, comment=''):
        if (self.containedInConfig):
            #dumping a new config file is useless, because everything is contained in the initial config file
            myLog.mustPrint("INFO: The information about the .epub is entirely contained in the config file\n")
            return
        
        myLog.mustPrint("INFO: generating new config file\n")
        with open(filename, 'w') as myFile:
            print(comment, file=myFile)
            print('[BuildIndex]', file=myFile)      
           
            for key,arg in self.arglist.items():
                #myLog.print("key", key, "value", arg.value)
                if (key != 'configFile'):
                    #myLog.print(arg.getConfig())
                    print(arg.getConfig(), file=myFile)
                    
            # 'authors','number of authors';
            # 'books','number of books';
            # size is directly readfrom the epub file
            # date is indicated by the last modification ti e of the file        
        
#--------------------------------------------------------------
#   Main
#--------------------------------------------------------------


if __name__ == "__main__":
    # first, --conf is used,
    #   if defined, the configuration file is read
    #   if not defined, the default configuration file is read
    # then args are parsed in the order they appear in the commandline
    
   
    
    parser = ArgsList('Build an index epub from Calibre Database.')
    
    parser.addArg('MaxAuthors', defaultVal=None, helper='maximum number of books (default: none)',  typedescr = 'int')
    parser.addArg('URL','https://www.mysite.com/ebooks/', 'Calisson web site URL'),
    parser.addArg('Cover','_Resources/Library.jpg', 'Book cover Image'),
    parser.addArg('Database','metadata.db', 'the Calibre datapath'),
    parser.addArg('Filebase','.','the path to the Calibre files')
    parser.addArg('EpubIndex','index.epub','basename of generated epub')
    parser.addArg('onlySubjects', None, 'Only subjects in this LIST will be indexed', typedescr = 'list')
    parser.addArg('avoidSubjects', None, 'Subjects in this LIST will NOT be indexed', typedescr = 'list')
    
    
    parser.doParse()
    myLog.print("DEBUG: avoidSubjects", parser.getValue('avoidSubjects'))
    
    
    lockfile =  parser.getValue('EpubIndex') + '.lock'
    
    print("PARSING", lockfile)
    parser.LockConfig(lockfile)
   
    
    CheckPip()
    Image = ImportModule('PIL.Image', "defaulting to ImageMagic")
    url_normalize = CheckModule('url_normalize')

    
    myLibrary = Library(parser.getValue('URL'),
                        parser.getValue('Cover'),
                        parser.getValue('Database'),
                        parser.getValue('Filebase'),
                        onlyTags = parser.getValue('onlySubjects'),
                        excludedTags = parser.getValue('avoidSubjects'))
    
    libraryName = parser.getValue('EpubIndex')
    myLibrary.Build(libraryName, parser.getValue('MaxAuthors'))
    myLog.print("Library BUILT\n")
    
    
    iniFile = re.sub('.epub','',libraryName)+ '.ini'
    
    parser.DumpConfig(iniFile, '# Index configuration file for Calisson')
    parser.UnlockConfig()
    
                