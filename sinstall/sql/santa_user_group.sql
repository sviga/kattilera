DROP TABLE IF EXISTS `%PREFIX%_user_group`;
CREATE TABLE `%PREFIX%_user_group` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `full_name` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COMMENT='Группы пользователей';
INSERT INTO `%PREFIX%_user_group` VALUES ('1','standart','Обычные посетители');
INSERT INTO `%PREFIX%_user_group` VALUES ('2','extended','Близкие посетители');
