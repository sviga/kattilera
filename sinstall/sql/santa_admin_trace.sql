DROP TABLE IF EXISTS `%PREFIX%_admin_trace`;
CREATE TABLE `%PREFIX%_admin_trace` (
  `id_admin` int(8) unsigned NOT NULL,
  `time` datetime NOT NULL,
  `place` varchar(255) NOT NULL,
  `ip` varchar(255) NOT NULL,
  `host` varchar(255) NOT NULL,
  PRIMARY KEY  (`id_admin`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_admin_trace` VALUES ('1','0000-00-00 00:00:00','catalog1','192.168.25.103','server');
INSERT INTO `%PREFIX%_admin_trace` VALUES ('2','2010-01-11 13:02:55','catalog1','192.168.25.100','server');
