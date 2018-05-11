 
--------------------------------------------------------------
- Server : the Apache PHP etc service
--------------------------------------------------------------

--------------------------------------------------------------
CaliNdex.php

Reads its configuration in CaliNdex.ini

Displays the main UI

--------------------------------------------------------------
BuildIndex.php

Reads the configuration in BuildIndex.conf

Reads or Generates an epub index of some books, serves it.

Parameters:
	&name=basename	the name of the generated index
	&onlySubjects = (a list of subjects, separated by ',')
    &avoidSujects = (a list of subjects, separated by ',')

The BuildIndex.py script is used to perform the core work
This script updates/creates the configuration file for each generated index
  name.conf
  + onlySubjects 
  + avoidSubjects
  + number of authors and books
  + size of the epub
  + date of creation
  + up to date or not



The date of the indexfile, found in basename.conf, 
is compared to the date of the database to understand if the index needs to be rebuilt

There is a race condition on each configuration file, 
if BuildIndex is used simultaneously by 2 instances.
To avoid this, the configuration file is WRITE-locked, 
so that a second instance of BuildIndex will NOT run, ans will wait for the previous instance to return a valid index.

Communication between the PHP and the PY is based, as usual, on ?????? Python library

--------------------------------------------------------------
QueryIndex.php

Displays a page that allows the user to specify the index required

Proposes:
  + index.php The global index
  + All partial indexe previously built
  + only index, with the list of tags to include
  + avoid index, with the list of tags to exclude

Each index is displayed with the information stored in its basename.conf
  