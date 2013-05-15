DROP TABLE IF EXISTS `%PREFIX%_admin_cross_group`;
CREATE TABLE `%PREFIX%_admin_cross_group` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `user_id` int(10) unsigned default NULL,
  `group_id` int(10) unsigned default NULL,
  PRIMARY KEY  (`id`),
  KEY `user_id` (`user_id`),
  KEY `group_id` (`group_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COMMENT='Принадежность администраторов группам';
INSERT INTO `%PREFIX%_admin_cross_group` VALUES ('4','1','1');
INSERT INTO `%PREFIX%_admin_cross_group` VALUES ('3','2','1');
