<?php

class CatalogCommons
{

    /**
     * Префикс путей к шаблонам административного интерфейса
     *
     * @var string
     */
    private static $templates_admin_prefix = 'modules/catalog/templates_admin/';

    /**
     * Префикс путей к шаблонам frontend
     *
     * @var string
     */
    private static $templates_user_prefix = 'modules/catalog/templates_user/';



    public static function clean_old_baskets($moduleid)
    {
        global $kernel;
        $prefix = $kernel->pub_prefix_get();
        $kernel->runSQL("DELETE FROM `".$prefix."_catalog_".$moduleid."_basket_orders` WHERE `lastaccess`<'".date("Y-m-d H:i:s",strtotime("-21 days"))."'");
        $q="DELETE items FROM `".$prefix."_catalog_".$moduleid."_basket_items` AS items
            LEFT JOIN `".$prefix."_catalog_".$moduleid."_basket_orders` AS `orders` ON orders.id = items.orderid
            WHERE orders.id IS NULL";
        $kernel->runSQL($q);
    }


    public static function get_child_cats_with_count($moduleid,$parentid,$select_fields="cats.*",$subcats_count=false)
    {
        global $kernel;
        $prfx=$kernel->pub_prefix_get();
        if ($subcats_count)
            $select_fields.=', IFNULL(subcats._count,0) AS _subcats_count';
        $sql = 'SELECT '.$select_fields.', IFNULL(i2c._count,0) AS _items_count FROM `'.$prfx.'_catalog_'.$moduleid.'_cats` AS cats
                LEFT JOIN (SELECT COUNT(item_id)  AS _count, cat_id FROM `'.$prfx.'_catalog_'.$moduleid.'_item2cat` GROUP BY cat_id) AS i2c ON cats.id = i2c.cat_id';
        if ($subcats_count)
            $sql.=' LEFT JOIN (SELECT COUNT(id)  AS _count, parent_id FROM `'.$prfx.'_catalog_'.$moduleid.'_cats`  GROUP BY parent_id) AS subcats ON subcats.parent_id=cats.id';
        $sql.=  ' WHERE cats.`parent_id` = '.$parentid.'
                GROUP BY cats.id
                ORDER BY cats.`order`';
        return $kernel->db_get_list($sql);
    }

    /**
     * Возвращает товарную группу
     *
     * @param integer $id  id-шник группы
     * @return array
     */
    public static function get_group($id)
    {
        global $kernel;
        return $kernel->db_get_record_simple("_catalog_item_groups", "`id`=".$id);
    }

    /**
     * Генерирует случайную строку из латинских букв + цифр
     *
     * @param number $len длина
     * @param boolean $plusNumbers использовать и цифры?
     * @return string
     */
    public static function generate_random_string($len, $plusNumbers=true)
    {
        $arr = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z');
        $numbers = array('0','1','2','3','4','5','6','7','8','9');
        if ($plusNumbers)
            $arr = array_merge($arr,$numbers);
        $str="";
        for ($i=0;$i<$len;$i++)
             $str=$str.$arr[array_rand($arr)];
        return $str;
    }

    /**
     * Возвращает все внутренние фильтры в виде key=>value массива,
     * где key - строковый ID-шник фильтра,
     * value - полное название фильтра
     *
     * @return array
     */
    public static function get_inner_filters_kvarray()
    {
        $filters = self::get_inner_filters();
        $result = array();
        foreach ($filters as $filter)
        {
        	$result[$filter['stringid']] = htmlspecialchars($filter['name']);
        }
        return $result;
    }

    /**
     * Возвращает внутренний фильтр по ID
     *
     * @param number $id
     * @return array
     */
    public static function get_inner_filter($id)
    {
        global $kernel;
        $res    = false;
        $query  = 'SELECT * FROM `'.PREFIX.'_catalog_'.$kernel->pub_module_id_get().'_inner_filters` WHERE `id` ='.$id.' LIMIT 1';
        $result = $kernel->runSQL($query);
        if ($row = mysql_fetch_assoc($result))
            $res = $row;
        mysql_free_result($result);
        return $res;
    }


    /**
     * Возвращает внутренний фильтр по строковому ID
     *
     * @param string $stringid
     * @return array
     */
    public static function get_inner_filter_by_stringid($stringid)
    {
        global $kernel;
        return $kernel->db_get_record_simple('_catalog_'.$kernel->pub_module_id_get().'_inner_filters',' `stringid` ="'.mysql_real_escape_string($stringid).'"');
    }

    /**
     * Возвращает все внутренние фильтры
     *
     * @return array
     */
    public static function get_inner_filters()
    {
        global $kernel;
        return $kernel->db_get_list_simple('_catalog_'.$kernel->pub_module_id_get().'_inner_filters',"true");
    }


    /**
     * Возвращает все переменные модуля
     *
     * @return array
     */
    public static function get_variables()
    {
        global $kernel;
        $items = array();
        $query = 'SELECT * FROM `'.PREFIX.'_catalog_'.$kernel->pub_module_id_get().'_variables`';
        $result = $kernel->runSQL($query);
        while ($row = mysql_fetch_assoc($result))
            $items[$row['name_db']] = $row;
        mysql_free_result($result);
        return $items;
    }

    /**
     * Возвращает переменную модуля по идентификатору
     * @param string $name_db
     * @return array
     */
    public static function get_variable($name_db)
    {
        global $kernel;
        return $kernel->db_get_record_simple('_catalog_'.$kernel->pub_module_id_get().'_variables'," `name_db`='".$name_db."'");
    }

    /**
     * Возвращает все поля заказа (корзины)
     *
     * @return array
     */
    public static function get_order_fields()
    {
        global $kernel;
        return $kernel->db_get_list_simple('_catalog_'.$kernel->pub_module_id_get().'_basket_order_fields'," true ORDER BY `order`");
    }

    /**
     * Возвращает все поля заказа (корзины) в виде
     * key=>value массива, где key - DB-имя поля
     *
     * @return array
     */
    public static function get_order_fields2()
    {
        global $kernel;
        $items = array();
        $query = 'SELECT * FROM `'.PREFIX.'_catalog_'.$kernel->pub_module_id_get().'_basket_order_fields` '.
        		 'ORDER BY `order`';
        $result = $kernel->runSQL($query);
        while ($row = mysql_fetch_assoc($result))
            $items[$row['name_db']] = $row;
        mysql_free_result($result);
        return $items;
    }


    /**
     * Возвращает поле заказа (корзины)
     *
     * @param integer $id id-шник поля
     * @return array
     */
    public static function get_order_field($id)
    {
        global $kernel;
        return $kernel->db_get_record_simple('_catalog_'.$kernel->pub_module_id_get().'_basket_order_fields',"id=".$id);
    }



    /**
     * Проверяет, существует ли поле с указанным БД-именем
     * ($dbname) таблице заказов
     *
     * @param string $dbname имя свойства
     * @return boolean
     */
    public static function is_order_field_exists($dbname)
    {
        $reserved = array('id', 'sessionid', 'lastaccess', 'isprocessed');
        if (in_array($dbname, $reserved))
            return true;
        global $kernel;
        return $kernel->db_get_record_simple('_catalog_'.$kernel->pub_module_id_get().'_basket_order_fields','`name_db` ="'.$dbname.'"');
    }

    /**
     * Возвращает свойства для категорий
     *
     * @return array
     */
    public static function get_cats_props()
    {
        global $kernel;
        $moduleid = $kernel->pub_module_id_get();
        if ($moduleid=='catalog')
            return array();
        return $kernel->db_get_list_simple('_catalog_'.$moduleid.'_cats_props', 'true');
    }

    //catalog_menu_cat_props
    /**
     * Возвращает список свойств категорий в хтмл
     *
     * @return string
     */
    public static function get_cats_props_html()
    {
        $cprops = CatalogCommons::get_cats_props();
        $str = "<b>[#catalog_menu_cat_props#]</b><ul>";
        foreach ($cprops as $cprop)
        {
            $str .= "<li>&nbsp;&nbsp;".htmlspecialchars($cprop['name_full'])." (".$cprop['name_db'].")</li>";
        }
        $str .= "</ul>";
        return $str;
    }

    /**
     * Возвращает html вида
     *   Общие свойства
     *   	Артикул (articul)
     *   	Наименование (name)
     *   	Класс продукции (class)
     *   Группа 1
     *   	Модель (model)
     *   	Производитель (manufacturer)
     *   Группа 2
     *   	Бренд (brand)
     *   	Год выпуска (year)
     * для вывода в админке
     * @param $needid boolean
     * @return string
     */
    public static function get_all_group_props_html($needid = false)
    {
        $groups = CatalogCommons::get_all_group_props_array();
        $str = '<ul>';
        foreach ($groups as $gname=>$gprops)
        {
            $str .= "<li><b>".$gname."</b>";
            $str .= "<ul>";
            if ($needid && $gprops['id']==0)
                $str .= "<li>&nbsp;&nbsp;id (id)</li>";
            foreach ($gprops['props'] as $propname=>$propvars)
            {
                $str .= "<li>&nbsp;&nbsp;".htmlspecialchars($propvars['name_full'])." (".$propname.")</li>";
            }
            $str .= "</ul>";
            $str .= "</li>";
        }
        $str .= "</ul>";
        return $str;
    }



    /**
     * Возвращает массив всех тов. групп с их свойствами (включая общие)
     * вид массива
     *   [Общие свойства]=>
     * 	    array(
     *      [id] =>0,
     * 	    [props]=>array(...)
     * 		)
     *   [Группа 1]=>
     *      array(
     *      [id] =>0,
     * 	    [props]=>array(...)
     *      )
     *   [Группа 2]=>
     *      array(
     *      [id] =>0,
     * 	    [props]=>array(...)
     *      )
     * @return array
     */
    public static function get_all_group_props_array()
    {
        $ret = array();

        //сначала общие свойства
        $ret["[#catalog_common_props#]"]=array("id"=>0, "props"=>CatalogCommons::get_props2(0), "name_full"=>"[#catalog_common_props#]");

        //теперь свойства для всех товарных групп
        $groups = CatalogCommons::get_groups();
        foreach ($groups as $grop_values)
        {
            $ret[$grop_values['name_full']] =  array("id"=>$grop_values['id'], "name_full"=>$grop_values["name_full"], "props"=>CatalogCommons::get_props2($grop_values['id']));
        }
        return $ret;
    }

  /**
     * Возвращает свойства для группы
     *
     * @param integer $gid          id-шник группы
     * @param boolean $need_common  нужны ли общие для всех товаров свойства?
     * @return array
     */
    public static function get_props($gid, $need_common = false)
    {
        global $kernel;
        $cond = '`module_id` = "'.$kernel->pub_module_id_get().'" AND (`group_id`='.$gid;
        if ($need_common)
            $cond .= ' OR `group_id`=0';
        $cond .= ') ORDER BY `order`,`name_full`';   //чтобы сначала шли common-свойства
        return $kernel->db_get_list_simple("_catalog_item_props",$cond);
    }

    /**
     * Возвращает свойства для группы в виде массива с элементами namedb=>array(...свойства...)
     *
     * @param integer $gid id-шник группы
     * @return array
     */
    public static function get_props2($gid)
    {
        global $kernel;
        $items = array();
        $query = 'SELECT * FROM `'.PREFIX.'_catalog_item_props` '.
        		 'WHERE `module_id` = "'.$kernel->pub_module_id_get().'" '.
        		 'AND (`group_id`='.$gid.') ORDER BY `order`, `name_full`';
        $result = $kernel->runSQL($query);
        while ($row = mysql_fetch_assoc($result))
            $items[$row['name_db']] = $row;
        mysql_free_result($result);
        return $items;
    }

    /**
     * Возвращает все категории из БД
     *
     * @param integer $moduleid id-шник модуля
     * @return array
     */
    public static function get_all_categories($moduleid)
    {
    	global $kernel;
        $dbcats = $kernel->db_get_list_simple('_catalog_'.$moduleid.'_cats', "true","id, name,parent_id,_hide_from_waysite");
        $cats = array();
        foreach ($dbcats as $cat)
        {
            $cats[$cat['id']]=$cat;
        }
        return $cats;
    }

	/**
     * Возвращает все товарные группы для текущего модуля из БД
     * @param string $moduleid
     * @param boolean $with_items_count результат с кол-вом товаров в каждой группе?
     * @return array
     */
    public static function get_groups($moduleid=null,$with_items_count=false)
    {
        global $kernel;
        if (!$moduleid)
            $moduleid=$kernel->pub_module_id_get();
        if ($with_items_count)
        $query = 'SELECT groups.*, COUNT(items.id) AS _items_count FROM `'.PREFIX.'_catalog_item_groups` AS groups
            LEFT JOIN `'.PREFIX.'_catalog_'.$moduleid.'_items` AS items ON items.group_id=groups.id
            WHERE groups.`module_id` = "'.$moduleid.'"
            GROUP BY groups.id
            ORDER BY groups.id';
        else
            $query = 'SELECT * FROM `'.PREFIX.'_catalog_item_groups` WHERE `module_id` = "'.$moduleid.'"  ORDER BY `id`';
        $ret=array();
        $groups = $kernel->db_get_list($query);
        foreach($groups as $g)
        {
            $ret[$g['id']]=$g;
        }
        return $ret;
    }


    /**
     * Проверяет, изменился ли md5 для файла
     *
     * @param string $filename полный путь к файлу
     * @param string $md5 MD5 предыдущей версии файла
     * @return boolean
     */
    public static function isTemplateChanged($filename, $md5)
    {
        if (empty($md5) || !file_exists($filename))
            return false;
        if ($md5 == md5_file($filename))
            return false;
        return true;
    }

    /**
     * Пересоздаёт шаблон для отображения common-свойств товара во frontend
     *
     * @return boolean
     */
    /*
    public static function regenerate_frontend_item_common_block($id_module, $group = array(), $force=false)
    {
        global $kernel;

        $fname = CatalogCommons::get_templates_user_prefix().$id_module.'_'.$group['name_db'].'_card.html';

        $msettings = $kernel->pub_module_serial_get($id_module);
        if (!isset($msettings['frontend_items_list_tpl_md5']))
            $msettings['frontend_items_list_tpl_md5']='';

        //Пока без проверок
        //if ($force || !CatalogCommons::isTemplateChanged($fname, $msettings['frontend_items_list_tpl_md5']))
        //{
            $html = '';
            $template = $kernel->pub_template_parse(CatalogCommons::get_templates_admin_prefix().'frontend_templates/blank_item_one.html');
            //$fh    = fopen($fname, "w");
            //if (!$fh)
            //    return false;

            $props = CatalogCommons::get_common_props($id_module, false);
            $lines = '';
            foreach ($props as $prop)
            {
                $line = $template['prop_'.$prop['type']];
                $line = str_replace('%prop_name_full%', $prop['name_full'], $line);
                $line = str_replace('%prop_value%', '%'.$prop['name_db'].'_value%', $line);
                $lines .= $line;
            }

            //$content = str_replace('%props%', $lines, $content);
            $html .= "<!-- @list_item_data -->";
            $html .= $lines;


            //блок для вывода навигации по страницам
            $kernel->pub_file_save($fname);

            //$msettings['frontend_items_list_tpl_md5'] = md5_file($fname);

            //$kernel->pub_module_serial_set($msettings);

            return true;
        //}
        //else
        //    return false;
    }
    */

    /**
     * Возвращает common-свойства (общие для всех товаров)
     *
     * @param $id_module string модуль
     * @param $only_listed boolean возвращать только свойства, которые выводим в списке товаров
     * @return array
     */
    public static function get_common_props($id_module, $only_listed=false)
    {
        global $kernel;
        $items = array();
        $query = 'SELECT * FROM `'.PREFIX.'_catalog_item_props` '.
        ' WHERE `module_id` = "'.$id_module.'" AND `group_id`=0';
        $result = $kernel->runSQL($query);
        while ($row = mysql_fetch_assoc($result))
        {
            if (!$only_listed)
                $items[] = $row;
            elseif ($row['showinlist'] == 1)
                $items[] = $row;
        }

        mysql_free_result($result);
        return $items;
    }

    /**
     * Возвращет префикс путей к шаблонам пользовательского интерфейса
     *
     * @return string
     */
    public static function get_templates_user_prefix()
    {
        return self::$templates_user_prefix;
    }

    /**
     * Возвращет префикс путей к шаблонам административного интерфейса
     *
     * @return string
     */
    public static function get_templates_admin_prefix()
    {
        return self::$templates_admin_prefix;
    }


    public static function is_valid_itemid($id)
    {
        if (!is_numeric($id))
            return false;
        $id=intval($id);
        if ($id<1)
            return false;
        return $id;
    }
}