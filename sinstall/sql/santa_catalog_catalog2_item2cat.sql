DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_item2cat`;
CREATE TABLE `%PREFIX%_catalog_catalog2_item2cat` (
  `cat_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `order` smallint(3) unsigned default '1',
  UNIQUE KEY `pri` (`cat_id`,`item_id`),
  KEY `order` (`order`),
  KEY `item_id` (`item_id`),
  KEY `cat_id` (`cat_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('2','6','6');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('2','5','4');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('2','4','2');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('2','7','8');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('2','8','10');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('2','9','12');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('3','10','2');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('3','11','4');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('3','12','6');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('3','13','8');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('3','14','10');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('3','15','12');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('4','16','2');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('4','17','4');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('4','18','6');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('4','19','8');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('4','20','10');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('4','21','12');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('4','22','14');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('4','23','16');
INSERT INTO `%PREFIX%_catalog_catalog2_item2cat` VALUES ('4','24','18');
