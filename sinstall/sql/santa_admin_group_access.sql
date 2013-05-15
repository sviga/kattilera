DROP TABLE IF EXISTS `%PREFIX%_admin_group_access`;
CREATE TABLE `%PREFIX%_admin_group_access` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `group_id` int(10) unsigned default NULL,
  `modul_id` varchar(255) default NULL,
  `access_id` varchar(255) default NULL,
  `access` int(1) unsigned default NULL,
  PRIMARY KEY  (`id`),
  KEY `group_id` (`group_id`),
  KEY `modul_id` (`modul_id`),
  KEY `access_id` (`access_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
