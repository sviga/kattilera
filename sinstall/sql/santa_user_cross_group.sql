DROP TABLE IF EXISTS `%PREFIX%_user_cross_group`;
CREATE TABLE `%PREFIX%_user_cross_group` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `user_id` int(10) unsigned default NULL,
  `group_id` int(10) unsigned default NULL,
  PRIMARY KEY  (`id`),
  KEY `user_id` (`user_id`),
  KEY `group_id` (`group_id`),
  UNIQUE KEY `uniq` (`user_id`,`group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Принадлежность пользователей к группам';