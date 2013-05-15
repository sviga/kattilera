DROP TABLE IF EXISTS `%PREFIX%_catalog_item_props`;
 CREATE TABLE `%PREFIX%_catalog_item_props` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `group_id` int(10) unsigned NOT NULL,
  `module_id` varchar(255) NOT NULL,
  `name_db` varchar(255) NOT NULL,
  `name_full` varchar(255) NOT NULL,
  `type` enum('string','enum','number','text','html','file','date','pict') NOT NULL,
  `showinlist` tinyint(1) unsigned NOT NULL default '0',
  `sorted` tinyint(1) unsigned NOT NULL default '0',
  `ismain` tinyint(1) unsigned NOT NULL default '0',
  `add_param` text,
  `order` smallint(3) unsigned default '1',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `gmn` (`group_id`,module_id(100),name_db(100)),
  KEY `group_id` (`group_id`),
  KEY `module_id` (module_id(100)),
  KEY `order` (`order`),
  KEY `group_main` (`group_id`,`ismain`),
  KEY `group_sort` (`group_id`,`sorted`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('1','0','catalog1','name','Название','string','1','0','0',NULL,'10');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('23','0','catalog1','description','Описание','html','0','0','0',NULL,'80');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('3','0','catalog1','price','Цена','number','1','0','0',NULL,'40');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('4','0','catalog1','image','Изображение','pict','1','0','0','a:4:{s:9:\"pict_path\";s:0:\"\";s:6:\"source\";a:6:{s:5:\"isset\";b:1;s:5:\"width\";i:220;s:6:\"height\";i:220;s:9:\"water_add\";i:0;s:10:\"water_path\";s:0:\"\";s:14:\"water_position\";i:0;}s:3:\"big\";a:6:{s:5:\"isset\";b:1;s:5:\"width\";i:220;s:6:\"height\";i:220;s:9:\"water_add\";i:0;s:10:\"water_path\";s:0:\"\";s:14:\"water_position\";i:3;}s:5:\"small\";a:3:{s:5:\"isset\";b:1;s:5:\"width\";i:65;s:6:\"height\";i:65;}}','20');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('5','0','catalog2','name1','Название','string','1','0','0',NULL,'10');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('6','0','catalog2','image1','Изображение','pict','0','0','0','a:4:{s:9:\"pict_path\";s:0:\"\";s:6:\"source\";a:6:{s:5:\"isset\";b:1;s:5:\"width\";i:500;s:6:\"height\";i:0;s:9:\"water_add\";i:0;s:10:\"water_path\";s:0:\"\";s:14:\"water_position\";i:0;}s:3:\"big\";a:6:{s:5:\"isset\";b:1;s:5:\"width\";i:500;s:6:\"height\";i:0;s:9:\"water_add\";i:1;s:10:\"water_path\";s:0:\"\";s:14:\"water_position\";i:3;}s:5:\"small\";a:3:{s:5:\"isset\";b:1;s:5:\"width\";i:75;s:6:\"height\";i:75;}}','20');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('22','0','catalog1','manufacturer','Производитель','enum','0','0','1',NULL,'30');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('21','0','catalog1','series','Серия','string','0','0','0',NULL,'35');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('9','3','catalog1','guarantee','Гарантия','number','0','0','0',NULL,'45');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('10','3','catalog1','colour','Цвет','string','0','0','0',NULL,'47');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('11','3','catalog1','panel','Пульт','enum','0','0','0',NULL,'50');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('24','3','catalog1','Overall_dimensions','Габаритные размеры','string','0','0','0',NULL,'48');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('13','3','catalog1','Sound_capacity','Звуковая мощность','string','0','0','0',NULL,'56');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('14','3','catalog1','Quantity_dinamikov','Количество динамиков','number','0','0','0',NULL,'59');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('15','3','catalog1','diagonal','Диагональ','number','0','0','0',NULL,'62');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('16','3','catalog1','Quantity_colours','Количество цветов','number','0','0','0',NULL,'65');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('17','3','catalog1','max_permission','Максимальное разрешение','string','0','0','0',NULL,'67');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('18','3','catalog1','brightness','Яркость','number','0','0','0',NULL,'69');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('19','3','catalog1','Review_corner','Угол обзора','number','0','0','0',NULL,'71');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('25','4','catalog1','Weight','Вес','number','0','0','0',NULL,'65');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('26','4','catalog1','Overall_dimensions','Габаритные размеры','string','0','0','0',NULL,'60');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('30','4','catalog1','Guarantee','Гарантия','number','0','0','0',NULL,'33');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('31','4','catalog1','Guarantee1','Цвет','string','0','0','0',NULL,'37');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('32','4','catalog1','colour_display','Цветной дисплей','enum','0','0','0',NULL,'45');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('33','4','catalog1','screen','Экран','enum','0','0','0',NULL,'50');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('34','4','catalog1','screen_touch','Экран сенсорный','enum','0','0','0',NULL,'55');
INSERT INTO `%PREFIX%_catalog_item_props` VALUES ('35','4','catalog1','food','Питание','string','0','0','0',NULL,'70');
