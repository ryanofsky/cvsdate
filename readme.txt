cvsdate.php is a small utitilty that sets the revision dates of files stored
in a CVS repository to the modification dates of a set of corresponding files
in another directory (which is probably, but not neccessarily, a CVS working
directory).

It was written because the 'cvs commit' command does not currently have an 
option to preserve the timestamps of files that are uploaded into the 
repository. Instead it stamps files with the current time on the server at the 
time of the commit command. There'd be no reason to ever use an timestamp 
preserving option on a CVS repository that is actively used by more than a few 
developers, but this feature would be useful when managing CVS repositories
that are used more as code archival systems (glorified RCS) than collaborative
tools.

Anyway, cvsdate gets around this minor deficiency by simply going through the
RCS files in a CVS repository and changing the date fields where appropriate.
cvsdate understands cvs tags, and when used in combination with the 'cvs tag'
(or rtag) command, can offer a very high level of granularity. A basic 
example of its use is: 

php -q cvsdate.php c:\cvsroot\myrepository c:\myworkingdir

(The -q option here just tells the php interpreter not to output HTTP headers.)

This command causes cvsdate to obtain the timestamp of each file in 
c:\myworkingdir, find the corresponding RCS file in the repository, find the
date field of the HEAD revision in the RCS file and insert the new timestamp
there if the new timestamp is less than the one already there.

This command:

php -q cvsdate.php --force c:\cvsroot\myrepository c:\myworkingdir

does the same as the previous, except that is causes cvsdate to skip the date
comparison and overwrite the date in the HEAD revision even if it is earlier
than the one in the file's modification timestamp.

To change the file revisions which are not HEAD revisions, you must give
those revisions symbolic names with the 'cvs tag' or 'cvs rtag' commands.
Once that is done, this command

php -q cvsdate.php c:\cvsroot\myrepository c:\myworkingdir tagname

will update the file revisions that are tagged as 'tagname' with the new,
earlier timestamps. 

(The tag you specify has be a static tag rather than a branching tag.
You can easily get around this restriction though by creating a 
temporary tag, as in the following sequence:

cvs rtag -r the_branch temp_tag myrepository  # create temporary tag
net stop cvs                                  # shut down cvsnt
php -q cvsdate.php c:\cvsroot\myrepository c:\myworkingdir temp_tag
net start cvs                                 # start up cvs server
cvs rtag -d temp_tag myrepository             # delete the temporary tag

)

cvsdate has an optional fourth parameter that also takes the name of a
tag. 

php -q cvsdate.php c:\cvsroot\myrepository c:\myworkingdir tagname1 tagname2

When it recieves these arguments, it will scan the repository and only update
the revisions that are tagged with 'tagname1' and NOT tagged with 'tagname2'

Here is an example of how this can be used:

cvs tag before_commit                   # create a temporary tag
cvs commit                              # upload some changes to the repository
cvs tag after_commit                    # create another tag
net stop cvs                            # shut down cvs server
php cvsdate.php c:\cvsroot\repository c:\workingdir after_commit before_commit
net start cvs
cvs rtag -d before_commit repository    # delete temporary tag 1
cvs rtag -d after_commit repository     # delete temporary tag 2

After the first three steps, the files that have changed since the previous
commit will have the before_commit and after_commit tags pointing to
*different* revisions. These files will have the dates refreshed. cvsdate
will simply skip over any files which haven't changed since the last commit
because on those files the revision that is tagged with after_commit
will also be tagged with before_commit.