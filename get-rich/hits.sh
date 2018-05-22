#!/bin/bash

cp /var/www/html/counter.txt /var/www/html/last.txt
/usr/bin/php balance.php > /var/www/html/counter.txt

LAST=`cat /var/www/html/last.txt`
NOW=`cat /var/www/html/counter.txt`

if [ $LAST -lt $NOW ];
then
   RAT=`expr $LAST - $NOW`
   RAT2=`expr $RAT / $NOW`
   echo "+ ($RAT) $RAT2" > /var/www/html/diff.txt
else
   RAT=`expr $LAST - $NOW`
   RAT2=`expr $RAT / $NOW`
   echo "- ($RAT) $RAT2" > /var/www/html/diff.txt
fi

