<?php
class gallery_install extends install_modules
{
	function install($id_module, $reinstall = false)
	{
		global $kernel;
	    $query = 'CREATE TABLE IF NOT EXISTS `'.$kernel->pub_prefix_get().'_gallery` (
             `id` int(10) unsigned NOT NULL auto_increment,
             `module_id` varchar(255) NOT NULL,
             `description` text NOT NULL,
             `title_image` text NOT NULL,
             `image` varchar(255) NOT NULL,
             `post_date` DATE default NULL,
             `cat_id` int(10) unsigned DEFAULT "0",
             PRIMARY KEY  (`id`),
             KEY `module_id` (`module_id`),
             KEY `module_cat_id` (`module_id`,`cat_id`)
         ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1';
        $kernel->runSQL($query);

        $query = 'CREATE TABLE IF NOT EXISTS `'.$kernel->pub_prefix_get().'_gallery_cats` (
             `id` int(10) unsigned NOT NULL auto_increment,
             `module_id` varchar(255) NOT NULL,
             `name` text NOT NULL,
             `description` text DEFAULT NULL,
             PRIMARY KEY  (`id`),
             KEY `module_id` (`module_id`)
         ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1';
        $kernel->runSQL($query);
	}
	
	function uninstall($id_module)
	{
		global $kernel;

        require_once dirname(__FILE__)."/gallery.class.php";

        $irecs = $kernel->db_get_list_simple("_gallery","true");
        foreach ($irecs as $irec)
        {
            gallery::image_delete($irec);
        }

		$query = 'DROP TABLE `'.$kernel->pub_prefix_get().'_gallery`';
		$kernel->runSQL($query);
        $query = 'DROP TABLE `'.$kernel->pub_prefix_get().'_gallery_cats`';
		$kernel->runSQL($query);
	}

	
	function install_children($id_module, $reinstall = false)
	{
		global $kernel;
		$kernel->pub_dir_create_in_images($id_module);
		$kernel->pub_dir_create_in_images($id_module.'/tn');
		$kernel->pub_dir_create_in_images($id_module.'/source');
	}

	function uninstall_children($id_module)
	{
		global $kernel;

        require_once dirname(__FILE__)."/gallery.class.php";

        $irecs = $kernel->db_get_list_simple("_gallery","module_id='".$id_module."'");
        foreach ($irecs as $irec)
        {
            gallery::image_delete($irec);
        }
        $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_gallery_cats` WHERE `module_id`="'.$id_module.'"';
		$kernel->runSQL($query);
		$kernel->pub_dir_recurs_delete('content/images/'.$id_module);
	}
}


$install = new gallery_install();

$install->set_name('[#gallery_base_name#]');
$install->set_id_modul('gallery');
$install->set_admin_interface(2);

// Ширина большой картинки в пикселях
$property = new properties_string();
$property->set_caption('[#gallery_property_img_big_width#]');
$property->set_default('800');
$property->set_id('img_big_width');
$install->add_modul_properties($property);

// Высота большой картинки в пикселях
$property = new properties_string();
$property->set_caption('[#gallery_property_img_big_height#]');
$property->set_default('600');
$property->set_id('img_big_height');
$install->add_modul_properties($property);

// Ширина маленькой картинки в пикселях
$property = new properties_string();
$property->set_caption('[#gallery_property_img_small_width#]');
$property->set_default('150');
$property->set_id('img_small_width');
$install->add_modul_properties($property);

// Высота маленькой картинки в пикселях
$property = new properties_string();
$property->set_caption('[#gallery_property_img_small_height#]');
$property->set_default('100');
$property->set_id('img_small_height');
$install->add_modul_properties($property);

//фаил водяного знака
$property = new properties_file();
$property->set_id('path_to_copyright_file');
$property->set_caption('[#gallery_property_path_to_copyright_file#]');
$property->set_patch('modules/gallery/templates_user/copyright');
$property->set_mask('gif');
$property->set_default('modules/gallery/templates_user/copyright/santa_logo.gif');
$install->add_modul_properties($property);

//расположение водяного знака
$property = new properties_select();
$property->set_id('copyright_position');
$property->set_caption('[#gallery_property_copyright_position#]');
$property->set_data(array("1"=>"[#gallery_property_copyright_position_1#]",
						  "2"=>"[#gallery_property_copyright_position_2#]",
						  "3"=>"[#gallery_property_copyright_position_3#]",
						  "4"=>"[#gallery_property_copyright_position_4#]"));
$property->set_default('4');
$install->add_modul_properties($property);

//прозрачность водяного знака
$property = new properties_select();
$property->set_caption('[#gallery_property_copyright_transparency#]');
$property->set_id('copyright_transparency');
$property->set_data(array("10"=>"10%",
						  "20"=>"20%", 
						  "30"=>"30%", 
						  "40"=>"40%", 
						  "50"=>"50%", 
						  "60"=>"60%", 
						  "70"=>"70%", 
						  "80"=>"80%", 
						  "90"=>"90%", 
						  "100"=>"100%"));
$property->set_default('20');
$install->add_modul_properties($property);

$install->set_name('[#gallery_modul_base_name#]');

$install->set_id_modul('gallery');

$install->set_admin_interface(2);

$install->add_public_metod('pub_create_content', '[#gallery_pub_create_content#]');

$p = new properties_file();
$p->set_id('param_metod_3');
$p->set_caption('[#gallery_module_pub1_propertes3#]');
$p->set_patch('modules/gallery/templates_user');
$p->set_mask('htm,html');
$p->set_default('modules/gallery/templates_user/template_user.html');
$install->add_public_metod_parametrs('pub_create_content',$p);
// Кол-во изображений на страницу
$property = new properties_string();
$property->set_caption('[#gallery_items_per_page#]');
$property->set_default(20);
$property->set_id('items_per_page');
$install->add_public_metod_parametrs('pub_create_content', $property);


$install->add_public_metod('pub_random_photos', '[#gallery_pub_random_photos#]');
$p = new properties_file();
$p->set_id('param_metod_3');
$p->set_caption('[#gallery_module_pub1_propertes3#]');
$p->set_patch('modules/gallery/templates_user');
$p->set_mask('htm,html');
$p->set_default('modules/gallery/templates_user/template_user.html');
$install->add_public_metod_parametrs('pub_random_photos',$p);
// Кол-во изображений на страницу
$property = new properties_string();
$property->set_caption('[#gallery_items_to_show#]');
$property->set_default(5);
$property->set_id('items_to_show');
$install->add_public_metod_parametrs('pub_random_photos', $property);

$install->module_copy[0]['name'] = 'gallery_modul_base_name1';

$install->module_copy[0]['action'][0]['caption']    = 'Галерея по умолчанию';
$install->module_copy[0]['action'][0]['id_metod']   = 'pub_create_content';
?>