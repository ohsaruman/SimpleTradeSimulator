#  Simple Trade Simulator

Trade Simulator written by php.

## Database Setting

config.txt
 database['user']= ""
 databsse['pass'] = ""
 databsse['name'] = ""
 database['table'="rate"


create table price data like this

CREATE TABLE `rate` (
  `dt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `code` char(6) NOT NULL DEFAULT '',
  `open` float DEFAULT NULL,
  `high` float DEFAULT NULL,
  `low` float DEFAULT NULL,
  `close` float DEFAULT NULL,
  PRIMARY KEY (`dt`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



