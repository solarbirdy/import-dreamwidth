# import-dreamwidth
This is a tool for importing Dreamwidth journal XML backups into WordPress

I did a lot of searching trying to figure out how to get my Dreamwidth blog entries and their comments brought over into my new WordPress install, and from what I could find, nobody's managed it.

Until now.

It's not a good importer; I wrote it well enough to be used successfully by me, once. Then it turned out more people wanted to do this and asked me to share my code. So I made it _somewhat_ less terrible and here we go.

Don't worry; it's still terrible.

STUFF YOU NEED TO DO AND RESOURCES YOU NEED TO ALLOCATE:

FIRST -- create an XML backup of your Dreamwidth journal using this tool from the official dreamwidth github:

https://github.com/dreamwidth/dreamwidth/blob/main/src/jbackup/jbackup.pl

Where it says "Password," it actually wants an API key, which you can generate through Dreamwidth's web interface. If you're using JournalPress with Dreamwidth to echo posts from a WordPress blog to your journal, you can use that one.

SECOND -- set a whole bunch of resource allocations:

In [wp-home]/wp-includes/functions.php set runtime to 300 seconds max (default is 30 – and yes this is core code, deal with it) by adding this up top:

ini_set('max_execution_time', '300');

In /etc/php/[your version]/apache2/php.ini set runtime maximum to 90 seconds (default is 30):

max_execution_time = 90

and set process memory allocation limit to 1GB (default is like 132MB):

memory_limit = 1G

Using mysql console for MariaDB, increase maximum allowed packet size (default is 1:6777216)

SET GLOBAL max_allowed_packet=1073741824;

THIRD -- Install the import tool. The default Wordpress install provides a Livejournal importer, accessible from the Tools/Import menu. Back up the livejournal-importer.php file and overwrite it that with this nonsense. Make sure to keep the same permissions.

FOURTH -- Take the XML backup you made in Step 1 and place it into the same livejournal-importer directory. Name it "importme.xml" and give it the same permissions as the other files already in the directory.

FIFTH - You're finally ready to run. Go to Tools/Import, select Livejournal. It'll bring up a Dreamwidth importer instead! Read the directions, provide a password for protected posts if you want to, and hit go.

And then hopefully, you'll be done!
