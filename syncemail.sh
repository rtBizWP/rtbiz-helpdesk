#!/bin/bash
echo start;
path='/var/www/rtcamp.com/htdocs/wp-content/plugins/rt-helpdesk/'
count=$(cat $path/'mailaccount.txt')
for i in $(seq 1 $count)
do
        cd $path;
        (/usr/bin/php -f hdmailcron.php;)&
        echo $i;
        sleep 1;
done
