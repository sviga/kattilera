DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_inner_filters`;
CREATE TABLE `%PREFIX%_catalog_catalog2_inner_filters` (
  `id` int(5) unsigned NOT NULL auto_increment,
  `stringid` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `query` text NOT NULL,
  `limit` int(5) unsigned default NULL,
  `catids` varchar(255) default NULL,
  `targetpage` varchar(255) default NULL,
  `template` varchar(255) NOT NULL,
  `perpage` int(5) unsigned default NULL,
  `maxpages` int(5) unsigned default NULL,
  `groupid` int(5) unsigned default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `stringid` (`stringid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_catalog_catalog2_inner_filters` VALUES ('1', 'allphoto', 'все фото', 'true', '1000', '0', 'index', 'catalog1_photos_list.html', '1000', '1000', '2');
