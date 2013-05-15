DROP TABLE IF EXISTS `%PREFIX%_backup_ignoredpaths`;
CREATE TABLE `%PREFIX%_backup_ignoredpaths` (
  `ruleid` int(5) unsigned default NULL,
  `path` varchar(255) NOT NULL,
  UNIQUE KEY `path` (`path`,`ruleid`),
  KEY `ruleid` (`ruleid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_backup_ignoredpaths` VALUES ('2','files');
