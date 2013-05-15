DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_basket_items`;
CREATE TABLE `%PREFIX%_catalog_catalog2_basket_items` (
  `id` int(5) unsigned NOT NULL auto_increment,
  `sessionid` varchar(32) NOT NULL,
  `itemid` int(5) unsigned NOT NULL,
  `qty` int(5) unsigned NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `itemid_sessionid` (`sessionid`,`itemid`),
  KEY `itemid` (`itemid`),
  KEY `sessionid` (`sessionid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
