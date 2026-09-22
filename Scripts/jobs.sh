#!/bin/sh
IFS=','
while true 
do
	php /var/www/admin/crons/crons.outflow.php
sleep 30
done

