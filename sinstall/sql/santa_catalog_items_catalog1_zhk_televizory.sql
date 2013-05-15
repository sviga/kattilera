DROP TABLE IF EXISTS `%PREFIX%_catalog_items_catalog1_zhk_televizory`;
CREATE TABLE `%PREFIX%_catalog_items_catalog1_zhk_televizory` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `guarantee` decimal(12,2) default NULL,
  `colour` varchar(255) default NULL,
  `panel` enum('есть','нет') default NULL,
  `Sound_capacity` varchar(255) default NULL,
  `Quantity_dinamikov` decimal(12,2) default NULL,
  `diagonal` decimal(12,2) default NULL,
  `Quantity_colours` decimal(12,2) default NULL,
  `max_permission` varchar(255) default NULL,
  `brightness` decimal(12,2) default NULL,
  `Review_corner` decimal(12,2) default NULL,
  `Overall_dimensions` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_catalog_items_catalog1_zhk_televizory` VALUES ('1','1.00','зеркальный','есть','14 (7x2)','2.00','32.00','16.77','1280x768','500.00','170.00',NULL);
INSERT INTO `%PREFIX%_catalog_items_catalog1_zhk_televizory` VALUES ('2','1.00','рамка - черное стекло','есть','14 (7x2)','2.00','32.00','16.77','1280x768','500.00','170.00',NULL);
INSERT INTO `%PREFIX%_catalog_items_catalog1_zhk_televizory` VALUES ('3','1.00','черный','есть','14','2.00','42.00','16.77','1920x1080','500.00','178.00',NULL);
INSERT INTO `%PREFIX%_catalog_items_catalog1_zhk_televizory` VALUES ('4','1.00','черный','есть','6 (3x2)','2.00','19.00',NULL,'1366x768','300.00','170.00','308x462x65');
INSERT INTO `%PREFIX%_catalog_items_catalog1_zhk_televizory` VALUES ('5','1.00','черный','есть','6 (3x2)','4.00','22.00',NULL,'1366x768','350.00','178.00','352x533x65');
INSERT INTO `%PREFIX%_catalog_items_catalog1_zhk_televizory` VALUES ('6','1.00','черный','есть','14 (7x2)','4.00','32.00',NULL,'1920x1080','500.00','178.00','544x802x79');
INSERT INTO `%PREFIX%_catalog_items_catalog1_zhk_televizory` VALUES ('7','1.00','черный','есть','20 (10x2)','2.00','32.00',NULL,'1366x768',NULL,'178.00','511x798x87');
INSERT INTO `%PREFIX%_catalog_items_catalog1_zhk_televizory` VALUES ('8','1.00','черный','есть','20 (10x2)','2.00','37.00',NULL,'1920x1080',NULL,'178.00','578x896x67');
INSERT INTO `%PREFIX%_catalog_items_catalog1_zhk_televizory` VALUES ('9','1.00',NULL,'есть','20 (10x2)','2.00','42.00',NULL,'1024x768','1500.00','178.00','677x1040x64');
INSERT INTO `%PREFIX%_catalog_items_catalog1_zhk_televizory` VALUES ('10','1.00','черный','есть','20 (2x10)','2.00','42.00',NULL,'1024x768','1500.00',NULL,'721x1031x308');
INSERT INTO `%PREFIX%_catalog_items_catalog1_zhk_televizory` VALUES ('11','1.00','черный','есть','20 (2x10)','4.00','50.00',NULL,'1366x768','1500.00','160.00','745x1253x110');
INSERT INTO `%PREFIX%_catalog_items_catalog1_zhk_televizory` VALUES ('12','3.00','черный','есть',NULL,NULL,'42.00',NULL,'1920x1080',NULL,NULL,'610x1020x99');
INSERT INTO `%PREFIX%_catalog_items_catalog1_zhk_televizory` VALUES ('13','3.00','черный','есть','20 (10x2)','2.00','42.00',NULL,'1920x1080',NULL,NULL,'674x1064x92');
INSERT INTO `%PREFIX%_catalog_items_catalog1_zhk_televizory` VALUES ('14','3.00','черный','есть','20 (10x2)','2.00','42.00',NULL,'1024x768',NULL,NULL,'661x1029x100');
INSERT INTO `%PREFIX%_catalog_items_catalog1_zhk_televizory` VALUES ('15','1.00','черный','есть','20 (10x2)','2.00','50.00',NULL,'1920x1080',NULL,NULL,'768x1218x105');
INSERT INTO `%PREFIX%_catalog_items_catalog1_zhk_televizory` VALUES ('16','1.00','черный','есть','14 (7x2)','2.00','50.00',NULL,'1366x768',NULL,'178.00','753x1256x112');
INSERT INTO `%PREFIX%_catalog_items_catalog1_zhk_televizory` VALUES ('17','1.00','сереюристый','есть','14 (7x2)','2.00','50.00',NULL,'1366x768','178.00',NULL,'81500');
