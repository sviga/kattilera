DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog1_item2cat`;
CREATE TABLE `%PREFIX%_catalog_catalog1_item2cat` (
  `cat_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `order` smallint(3) unsigned default '1',
  UNIQUE KEY `pri` (`cat_id`,`item_id`),
  KEY `order` (`order`),
  KEY `item_id` (`item_id`),
  KEY `cat_id` (`cat_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('4','9','4');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('4','8','2');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('3','7','6');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('3','6','4');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('3','5','2');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('4','10','6');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('6','11','2');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('6','12','4');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('8','13','2');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('8','14','4');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('8','15','6');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('9','16','2');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('9','17','4');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('9','18','6');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('9','19','8');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('10','20','2');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('10','21','4');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('11','22','2');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('13','22','2');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('11','23','4');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('13','23','4');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('11','24','6');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('13','24','6');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('11','25','8');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('12','25','2');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('11','26','10');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('12','26','4');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('11','27','12');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('14','27','2');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('11','28','14');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('14','28','4');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('11','29','16');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('14','29','6');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('11','30','18');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('15','30','2');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('11','31','20');
INSERT INTO `%PREFIX%_catalog_catalog1_item2cat` VALUES ('15','31','4');
