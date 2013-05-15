DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_basket_order_fields`;
CREATE TABLE `%PREFIX%_catalog_catalog2_basket_order_fields` (
  `id` int(5) unsigned NOT NULL auto_increment,
  `name_db` varchar(255) NOT NULL,
  `name_full` varchar(255) NOT NULL,
  `type` enum('text','number','enum','string') NOT NULL,
  `order` smallint(3) unsigned NOT NULL,
  `isrequired` tinyint(1) unsigned NOT NULL default '0',
  `regexp` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_catalog_catalog2_basket_order_fields` VALUES ('1','name','имя','string','10','0','');
INSERT INTO `%PREFIX%_catalog_catalog2_basket_order_fields` VALUES ('2','email','мыло','string','20','1','/^[a-z0-9]{1}[a-z0-9_.-]*@[a-z0-9.-]{1,}.[a-z]{2,6}$/i');
