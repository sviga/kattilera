DROP TABLE IF EXISTS `%PREFIX%_catalog_items_catalog1_panels`;
CREATE TABLE `%PREFIX%_catalog_items_catalog1_panels` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `Weight` decimal(12,2) default NULL,
  `Overall_dimensions` varchar(255) default NULL,
  `Guarantee` decimal(12,2) default NULL,
  `Guarantee1` varchar(255) default NULL,
  `colour_display` enum('есть','нет') default NULL,
  `screen` enum('есть','нет') default NULL,
  `screen_touch` enum('есть','нет') default NULL,
  `food` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_catalog_items_catalog1_panels` VALUES ('1','110.00','20x50x224','1.00','черный/белый',NULL,'нет',NULL,'аккумулятор');
INSERT INTO `%PREFIX%_catalog_items_catalog1_panels` VALUES ('2','200.00','20x54x227','1.00','черный','нет','есть','нет','аккумулятор');
INSERT INTO `%PREFIX%_catalog_items_catalog1_panels` VALUES ('3','320.00','34x166.4x100','1.00','черный/серебристый','есть','есть','есть','аккумулятор');
INSERT INTO `%PREFIX%_catalog_items_catalog1_panels` VALUES ('4',NULL,NULL,'1.00','черный','есть','есть','нет',NULL);
INSERT INTO `%PREFIX%_catalog_items_catalog1_panels` VALUES ('5',NULL,NULL,'1.00','темно-серый/черный','нет','есть','нет',NULL);
INSERT INTO `%PREFIX%_catalog_items_catalog1_panels` VALUES ('6',NULL,NULL,'1.00','темно-серый/черный',NULL,'нет',NULL,NULL);
INSERT INTO `%PREFIX%_catalog_items_catalog1_panels` VALUES ('7',NULL,NULL,'1.00','черно-серый','есть','есть','есть','Тип AA / щелочная LR6');
INSERT INTO `%PREFIX%_catalog_items_catalog1_panels` VALUES ('8',NULL,NULL,'1.00','черно-серый','есть','есть','нет',NULL);
INSERT INTO `%PREFIX%_catalog_items_catalog1_panels` VALUES ('9',NULL,NULL,'1.00','черный','есть','есть','есть','1.350 мАч литиево-ионная батарея');
INSERT INTO `%PREFIX%_catalog_items_catalog1_panels` VALUES ('10','213.00','188x78x30','1.00','58500','есть','есть','есть',NULL);
