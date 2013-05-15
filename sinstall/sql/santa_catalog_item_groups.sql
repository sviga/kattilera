DROP TABLE IF EXISTS `%PREFIX%_catalog_item_groups`;
CREATE TABLE `%PREFIX%_catalog_item_groups` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `module_id` varchar(255) NOT NULL,
  `name_db` varchar(255) NOT NULL,
  `name_full` varchar(255) NOT NULL,
  `front_tpl_md5` varchar(32) default NULL,
  `back_tpl_md5` varchar(32) default NULL,
  `template_items_list` varchar(255) default NULL,
  `template_items_one` varchar(255) default NULL,
  `defcatids` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  KEY `module_id` (`module_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_catalog_item_groups` VALUES ('1','catalog1','default','Основная',NULL,NULL,'catalog1_group1_list.html','catalog1_group1_card.html','1');
INSERT INTO `%PREFIX%_catalog_item_groups` VALUES ('2','catalog2','gallery','Галерея',NULL,NULL,'catalog1_photos_list.html','catalog1_photos_card.html',NULL);
INSERT INTO `%PREFIX%_catalog_item_groups` VALUES ('3','catalog1','ZHK_televizory','ЖК телевизоры',NULL,NULL,'catalog1_group1_list.html','catalog1_group1_card.html','');
INSERT INTO `%PREFIX%_catalog_item_groups` VALUES ('4','catalog1','Panels','Пульты',NULL,NULL,'catalog1_group1_list.html','catalog1_group1_card.html','11');
