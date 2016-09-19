#!/bin/bash

file=.config
if [ -e $file ]; then
 /usr/bin/php STS/run.php $1 $2 $3 $4 $5 $6 $7
else
  echo "<?php " >$file
  echo "\$database['user']='fxsim';" >>$file
  echo "\$database['pass']='fxsim_user';" >>$file
  echo "\$database['name']='fxsim';" >>$file
  echo "\$database['table']='rate';" >>$file
  echo "\$database['dsn']='mysql:dbname='.\$database['name'];" >>$file
  echo "\$database['opt']=array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);" >>$file

  echo "Modify File $file for  Databse access and execute this"
fi
