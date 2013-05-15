DROP TABLE IF EXISTS `%PREFIX%_catalog_visible_gprops`;
CREATE TABLE `%PREFIX%_catalog_visible_gprops` (
  `group_id` int(5) unsigned NOT NULL,
  `prop` varchar(255) NOT NULL,
  `module_id` varchar(255) NOT NULL,
  UNIQUE KEY `uniq` (group_id,module_id(100),prop(100)),
  KEY `module_id` (module_id(100)),
  KEY `group_module` (group_id,module_id(100)),
  KEY `prop_module` (prop(100),module_id(100))
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('1','description','catalog1');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('1','image','catalog1');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('1','manufacturer','catalog1');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('1','name','catalog1');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('1','price','catalog1');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('1','series','catalog1');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('2','description','catalog2');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('2','image1','catalog2');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('2','name1','catalog2');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('2','price','catalog2');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('3','description','catalog1');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('3','image','catalog1');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('3','manufacturer','catalog1');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('3','name','catalog1');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('3','price','catalog1');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('3','series','catalog1');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('4','description','catalog1');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('4','image','catalog1');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('4','manufacturer','catalog1');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('4','name','catalog1');
INSERT INTO `%PREFIX%_catalog_visible_gprops` VALUES ('4','price','catalog1');
