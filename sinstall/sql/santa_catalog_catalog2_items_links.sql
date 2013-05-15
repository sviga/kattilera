DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_items_links`;
CREATE TABLE `%PREFIX%_catalog_catalog2_items_links` (
  `itemid1` int(5) unsigned NOT NULL,
  `itemid2` int(5) unsigned NOT NULL,
  UNIQUE KEY `uniq` (`itemid1`,`itemid2`),
  KEY `item1` (`itemid1`),
  KEY `item2` (`itemid2`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
