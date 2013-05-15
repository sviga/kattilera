DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_items`;
CREATE TABLE `%PREFIX%_catalog_catalog2_items` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `group_id` int(10) unsigned NOT NULL,
  `ext_id` int(10) unsigned NOT NULL,
  `available` tinyint(1) unsigned default '0',
  `name1` varchar(255) default NULL,
  `image1` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  KEY `group_id` (`group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('1','2','1','1','Название №1','content/files/catalog2/122343298_22333fb8e3_o_1258563359.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('2','2','2','1','Название №2','content/files/catalog2/321464099_a7cfcb95cf_b_1258563397.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('3','2','3','1','Название №3','content/files/catalog2/66523124_b468cf4978_o_1258563428.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('4','2','4','1','Нantarex LCD 70 SQ TV silver','content/files/catalog2/ZHK_televizor_Nantarex_LCD_70_SQ_TV_silver_1258720414.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('5','2','5','1','Hantarex LCD 52','content/files/catalog2/ZHK_televizor_lcd_Hantarex_LCD_52_1258720438.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('6','2','6','1','LG 42LG6000','content/files/catalog2/ZHK_televizor_LG_42LG6000_1258720453.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('7','2','7','1','LG M 22.9 D','content/files/catalog2/ZHK_televizor_LG_M_229_D_1258720467.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('8','2','8','1','Panasonic TX-R32LX85','content/files/catalog2/ZHK_televizory_Panasonic_TXR32LX85_1258720486.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('9','2','9','1','Panasonic TX-R37LZ80K','content/files/catalog2/ZHK_televizor_37_PANASONIC_TXR37LZ80K_1258720515.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('10','2','10','1','LG 50PG100R','content/files/catalog2/Plazmennyj_televizor_50_LG_50PG100R_1258720595.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('11','2','11','1','LG 50PS8000','content/files/catalog2/Plazmennyj_televizor__LG_50PS8000_1258720636.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('12','2','12','1','Panasonic TH-42PW6EX','content/files/catalog2/Televizory_plazmennye_PANASONIC_TH42PW6EX_1258720690.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('13','2','13','1','Panasonic TH-42PV60R','content/files/catalog2/Televizor_plazmennyj_Panasonic_TH42PV60R_1258720753.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('14','2','14','1','Sony FWD-42PX2','content/files/catalog2/Plazmennyj_televizor_Sony_FWD42PX2_1258720771.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('15','2','15','1','SONY KE-42MR1','content/files/catalog2/Plazmennyj_televizor_SONY_KE42MR1_1258720787.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('16','2','16','1','Logitech Harmony 895','content/files/catalog2/Pult_DU_Logitech_Harmony_895_1258720881.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('17','2','17','1','HARMONY 785','content/files/catalog2/Pulty_DU_Logitech_HARMONY_785_1258720899.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('18','2','18','1','Marantz RC9001','content/files/catalog2/Pult_DU_Marantz_RC9001_1258720919.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('19','2','19','1','Marantz RC9500','content/files/catalog2/Pulty_universalnye_Marantz_RC9500_1258720935.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('20','2','20','1','Philips TSU9800','content/files/catalog2/Pulty_DU_Philips_TSU9800_1258720951.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('21','2','21','1','Philips TSU9400.','content/files/catalog2/Pulty_DU_Philips_TSU9400_1258720975.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('22','2','22','1','Nevo SL','content/files/catalog2/Pult_Nevo_SL_1258721000.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('23','2','23','1','Nevo S70','content/files/catalog2/Pult_DU_Nevo_S70_1258721014.jpg');
INSERT INTO `%PREFIX%_catalog_catalog2_items` VALUES ('24','2','24','1','NEVO Nevo Q50','content/files/catalog2/Pulty_DU_NEVO_Nevo_Q50_1258721029.jpg');
