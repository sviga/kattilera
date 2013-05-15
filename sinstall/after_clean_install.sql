TRUNCATE `%PREFIX%_action`;

TRUNCATE `%PREFIX%_admin`;

TRUNCATE `%PREFIX%_admin_cross_group`;

TRUNCATE `%PREFIX%_admin_group`;

TRUNCATE `%PREFIX%_admin_group_access`;

TRUNCATE `%PREFIX%_admin_trace`;

TRUNCATE `%PREFIX%_search1_docs`;

TRUNCATE `%PREFIX%_search1_index`;

TRUNCATE `%PREFIX%_search1_words`;

TRUNCATE `%PREFIX%_stat_domain`;

TRUNCATE `%PREFIX%_stat_host`;

TRUNCATE `%PREFIX%_stat_index`;

TRUNCATE `%PREFIX%_stat_referer`;

TRUNCATE `%PREFIX%_stat_uri`;

TRUNCATE `%PREFIX%_stat_word`;

TRUNCATE `%PREFIX%_structure`;

TRUNCATE `%PREFIX%_user`;

TRUNCATE `%PREFIX%_user_cross_group`;

TRUNCATE `%PREFIX%_user_fields`;

TRUNCATE `%PREFIX%_user_fields_value`;

TRUNCATE `%PREFIX%_metods`;

TRUNCATE `%PREFIX%_modules`;

TRUNCATE `%PREFIX%_structure`;

TRUNCATE `%PREFIX%_backup`;

TRUNCATE `%PREFIX%_backup_ignoredexts`;

TRUNCATE `%PREFIX%_backup_ignoredpaths`;

TRUNCATE `%PREFIX%_backup_ignoredtables`;

TRUNCATE `%PREFIX%_backup_rules`;

DROP TABLE IF EXISTS `%PREFIX%_newsi`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog1_basket_items`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog1_basket_order_fields`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog1_basket_orders`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog1_cats`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog1_cats_props`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog1_inner_filters`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog1_item2cat`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog1_items`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog1_items_links`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_basket_items`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_basket_order_fields`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_basket_orders`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_cats`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_cats_props`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_inner_filters`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_item2cat`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_items`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_items_links`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_item_groups`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_item_props`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_items_catalog1_default`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_items_catalog1_panels`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_items_catalog1_photos`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_items_catalog1_zhk_televizory`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_items_catalog2_default`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_items_catalog2_gallery`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog1_variables`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_catalog2_variables`;

DROP TABLE IF EXISTS `%PREFIX%_catalog_visible_gprops`;

DROP TABLE IF EXISTS `%PREFIX%_comments`;

DROP TABLE IF EXISTS `%PREFIX%_faq1_content`;

DROP TABLE IF EXISTS `%PREFIX%_faq1_partitions`;

INSERT INTO `%PREFIX%_modules` VALUES ('kernel',NULL,'[#structure_label_kernelaction#]','0','a:0:{}','a:0:{}','a:2:{s:11:\"kernel_stat\";s:30:\"[#top_menu_items4_main_admin#]\";s:14:\"kernel_content\";s:31:\"[#structure_label_editcontent#]\";}','a:0:{}','a:0:{}');

INSERT INTO `%PREFIX%_modules` VALUES ('structure', 'kernel', '[#top_menu_items1_main_admin#]', '2', 'a:0:{}', 'a:0:{}', 'a:1:{s:15:\"structure_index\";s:31:\"Главная страница\";}', 'a:0:{}', 'a:0:{}');

INSERT INTO `%PREFIX%_structure` VALUES ('index', null, 'Главная страница', null, 'a:10:{s:7:\"caption\";s:31:\"Главная страница\";s:11:\"title_other\";s:1:\"1\";s:10:\"name_title\";s:31:\"Главная страница\";s:8:\"template\";s:21:\"design/template1.html\";s:7:\"visible\";s:4:\"true\";s:16:\"waysite1_visible\";s:4:\"true\";s:16:\"mapsite1_visible\";s:4:\"true\";s:13:\"menu1_visible\";s:4:\"true\";s:9:\"only_auth\";b:0;s:15:\"link_other_page\";s:0:\"\";}', 'a:6:{s:5:\"title\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}s:3:\"way\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}s:6:\"search\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}s:7:\"content\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}s:4:\"menu\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}s:5:\"lenta\";a:3:{s:6:\"id_mod\";s:0:\"\";s:9:\"id_action\";s:0:\"\";s:3:\"run\";s:0:\"\";}}');

INSERT INTO `%PREFIX%_structure` VALUES ('subpage', 'index', 'Вложенная страница', '0', 'a:5:{s:7:\"caption\";s:19:\"О компании\";s:11:\"title_other\";b:1;s:10:\"name_title\";s:35:\"Вложенная страница\";s:8:\"template\";s:21:\"design/template1.html\";s:9:\"only_auth\";b:0;}', 'a:2:{s:3:\"way\";a:3:{s:6:\"id_mod\";s:8:\"waysite1\";s:9:\"id_action\";s:1:\"9\";s:3:\"run\";a:2:{s:4:\"name\";s:16:\"pub_show_waysite\";s:5:\"param\";s:68:\"a:1:{s:8:\"template\";s:39:\"modules/waysite/templates_user/way.html\";}\";}}s:7:\"content\";a:3:{s:6:\"id_mod\";s:6:\"kernel\";s:9:\"id_action\";s:1:\"1\";s:3:\"run\";a:2:{s:4:\"name\";s:22:\"priv_html_editor_start\";s:5:\"param\";s:6:\"a:0:{}\";}}}');

INSERT INTO `%PREFIX%_action`  (`id`, `id_module`, `caption`, `link_str`, `properties`, `param_array`) VALUES (1, 'kernel', '[#structure_label_editcontent#]', 'priv_html_editor_start', 'a:0:{}', 'a:0:{}'),(2, 'kernel', '[#structure_label_get_title#]', 'priv_page_title_get', 'a:0:{}', 'a:0:{}');

