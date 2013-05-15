DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_basket_orders`;
CREATE TABLE `%PREFIX%_catalog_catalog2_basket_orders` (
  `id` int(5) unsigned NOT NULL auto_increment,
  `sessionid` varchar(32) character set utf8 NOT NULL,
  `lastaccess` datetime default NULL,
  `isprocessed` tinyint(1) unsigned NOT NULL default '0',
  `name` varchar(255) default NULL,
  `email` varchar(255) default NULL,
  `text` text default NULL,
  `userid` int(5) unsigned default '0' ,
  `totalprice` decimal(10,2) unsigned DEFAULT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `sessionid` (`sessionid`),
  KEY `userid` (`userid`),
  KEY `lastaccess` (`lastaccess`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
