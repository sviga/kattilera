DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog1_basket_items`;
CREATE TABLE `%PREFIX%_catalog_catalog1_basket_items` (
  `id` int(5) unsigned NOT NULL auto_increment,
  `orderid` int(5) unsigned NOT NULL,
  `itemid` int(5) unsigned NOT NULL,
  `qty` int(5) unsigned NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `itemid_orderid` (`orderid`,`itemid`),
  KEY `orderid` (`orderid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
