DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog1_variables`;
CREATE TABLE `%PREFIX%_catalog_catalog1_variables` (
  `name_db` varchar(255) NOT NULL,
  `name_full` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY  (`name_db`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
