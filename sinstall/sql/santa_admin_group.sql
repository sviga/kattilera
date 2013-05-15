DROP TABLE IF EXISTS `%PREFIX%_admin_group`;
CREATE TABLE `%PREFIX%_admin_group` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `full_name` varchar(255) default NULL,
  `main_admin` tinyint(1) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_admin_group` VALUES ('1','admin','Главные администраторы','1');
INSERT INTO `%PREFIX%_admin_group` VALUES ('2','all_admin','Администраторы','0');
