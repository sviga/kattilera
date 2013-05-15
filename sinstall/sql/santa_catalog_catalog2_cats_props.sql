DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_cats_props`;
CREATE TABLE `%PREFIX%_catalog_catalog2_cats_props` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `name_db` varchar(255) NOT NULL,
  `name_full` varchar(255) NOT NULL,
  `type` enum('string','enum','number','text','html','file','pict') NOT NULL,
  `add_param` text,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_catalog_catalog2_cats_props` VALUES ('1','name','Название','string',NULL);
