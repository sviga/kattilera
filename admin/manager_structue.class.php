<?php

/**
 * Управляет структурой сайта, а так же настройками каждой конкретной страницы
 * @name manager_structue
 * @copyright  ArtProm (с) 2002-2013
 * @version 2.0
 */

class manager_structue
{
    /**
     * Формирует меню
     *
     * @param pub_interface $object
     */
    function interface_get_menu($object)
    {
        global $kernel;
        $object->set_menu_block('[#structure_main_header#]');
        //Создаём дерево
        $idx = $kernel->db_get_record_simple('_structure',"id='index'",'caption');
        $tree = new data_tree($idx['caption'], 'index', $this->get_all_nodes('index'));
        $tree->set_work_page_structure();
        $tree->set_action_click_node('view');
        $tree->set_action_move_node('move');
        $tree->set_drag_and_drop(true);
        $tree->set_name_cookie("tree_site_structure");
        $tree->set_tree_ID('structure');

        //Теперь сформируем небольшой дорогу, для того, что бы можно
        //было открыть текущую ноду по её пути
        $res = array();
        foreach ($kernel->pub_waysite_get($kernel->pub_page_current_get()) as $value)
        	$res[] = $value['id'];

        if (empty($res))
            $res='index';
        $tree->set_node_default($res);

        //Создаём контекстное меню
        $tree->contextmenu_action_set('Добавить страницу', 'page_add', '', '', 'ic_create');
		//$tree->contextmenu_delimiter();
        $tree->contextmenu_action_remove('Удалить страницу', 'page_remove','index', '[#structure_alert_del#]');

        $object->set_tree($tree);
    }

    /**
     * Обработка действий
     *
     * @return string
     */
    function start()
    {
    	global $kernel;

        $action = $kernel->pub_section_leftmenu_get();

        $change_template = false;
        $html = '';
        switch ($action)
        {
    		//Перемещаем ноду по структуре
    	    case 'move':
    	        $html = $this->node_move($kernel->pub_httppost_get('node'), $kernel->pub_httppost_get('newParent'), $kernel->pub_httppost_get('index'));
    	        break;

    	    case 'page_add':
    	        $node = $kernel->pub_httppost_get('node');
    	        if ($node)
    	            $kernel->priv_page_current_set($this->node_add($node));
    	        break;

            case 'page_remove':
                $node = $kernel->pub_httppost_get('node');
    	        if (!empty($node) && $node!="index")
    	            $this->node_remove($node);
    	        //Проставим в качестве текущей страницы, родителя
            	$id_parent = $kernel->pub_httppost_get('nodeparent');
            	if (!empty($id_parent))
                    $kernel->priv_page_current_set($id_parent);
    	        break;

            case 'view':
            	$id_page = $kernel->pub_httpget_get('id');
            	if (!empty($id_page))
                {
                    $mapsite=$kernel->pub_mapsite_get();
                    if (!isset($mapsite[$id_page]))//такой страницы нет у нас в структуре
                        return '';
                    $kernel->priv_page_current_set($id_page);
                }
                $manager    = new properties_page($kernel->pub_page_current_get());
                //а теперь провреим, если мы передаём ещё ряд особых параметров
                //значит нам нужно выдать только параметры ввиде массива
                $html       = $manager->show();
                break;

            //Сохраняем информацию только о новом шаблоне, специально нет break; в конце
            case 'save_template':
                $change_template = true;

            case 'save':
                if (!empty($_POST))
                    $html = $this->properties_save($_POST, $change_template);
                else
                {
                    $manager    = new properties_page($kernel->pub_page_current_get());
                    $html       = $manager->show();
                }
                break;
        }
        return $html;
    }


    function get_all_nodes($node_id = 'index')
    {
    	global $kernel;

        $sql = 'SELECT pages.id,pages.caption, (subpages.id IS NOT NULL) AS hasChildren
      				FROM `'.$kernel->pub_prefix_get().'_structure` AS pages
      				LEFT JOIN `'.$kernel->pub_prefix_get().'_structure` `subpages` ON subpages.parent_id=pages.id
      				WHERE pages.`parent_id` = "'.$node_id.'"
      				GROUP BY `pages`.`id`
      				ORDER BY `pages`.`order_number` ASC';

		$query = $kernel->runSQL($sql);

        $data = array();
        while ($row = mysql_fetch_assoc($query))
        {
            $array = array(
                'data'  => htmlentities($row['caption'], ENT_QUOTES, 'utf-8'),
                'attr'=>array('id'=>$row['id']),
            );

            if (!$row['hasChildren'])
                $array['attr']['rel']='default';
            else
            {
                $array['attr']['rel']='folder';
                $array['children'] = $this->get_all_nodes($row['id']);
            }

            $data[] = $array;
        }
		return $data;
    }


    /**
     * Сохраняет массив с данными страницы в БД и возвращает сообщение
     *
     * @param array $properties
     * @param boolean $only_page_properties
     * @return string
     */
    function properties_save($properties, $only_page_properties = false)
    {
    	global $kernel;

        $old_id_page = $kernel->pub_page_current_get();
    	$manager = new properties_page($kernel->pub_page_current_get());

    	//Сохраняем основные параметры страницы
    	$saved = false;
    	if (!empty($properties['page_properties']))
    	{
        	$manager->save_properties($properties['page_properties']);
        	$saved = true;
    	}
    	//Если не надо, то не будем сохранять свойства модулей к странице и привязки к меткам
        if (!$only_page_properties)
        {
        	//Теперь сохраним свойства модулей к странице
        	if (!empty($properties['properties']))
        	{
                if (!isset($properties['properties_cb']) || !is_array($properties['properties_cb']))
        	    	$properties['properties_cb'] = array();
                $manager->save_properties_addon($properties['properties'], $properties['properties_cb']);
            	$saved = true;
        	}

        	if (!empty($properties['page_modules']))
        	{
        	    if (!isset($properties['page_inheritance']) || !is_array($properties['page_inheritance']))
        	    	$properties['page_inheritance'] = array();
                if (!isset($properties['page_postprocessors']))
                    $properties['page_postprocessors']=array();
        		$manager->save_serialized($properties['page_modules'], $properties['page_inheritance'],$properties['page_postprocessors']);
            	$saved = true;
        	}
        }
    	if ($saved)
            $res = $kernel->pub_json_encode(array("success"=>true,"info"=>"[#kernel_ajax_data_saved_ok#]","oldid"=>$old_id_page));
        else
    	    $res = $kernel->pub_json_encode(array("success"=>false,"info"=>"[#kernel_ajax_data_save_failed#]"));
    	return $res;
    }


    /**
     * Перемещает ноду по структуре
     *
     * @param string $node_current_id
     * @param string $node_parent_new_id
     * @param string $node_current_index
     * @return string
     */
    function node_move($node_current_id, $node_parent_new_id, $node_current_index)
    {
        global $kernel;
        $this->structure_reorder($node_current_id, $node_current_index);

        $query = "UPDATE `".$kernel->pub_prefix_get()."_structure` SET
             `parent_id` = '$node_parent_new_id',
             `order_number` = '$node_current_index'
              WHERE `id`= '$node_current_id' LIMIT 1";
        $kernel->runSQL($query);
        if (mysql_affected_rows() <= 0)
        	$ret = array("success"=>false);
        else
            $ret = array("success"=>true);
        return $kernel->pub_json_encode($ret);
    }

    /**
     * Создаёт новую страницу в структуре
     *
     * @param integer $node_parent_id
     * @return integer
     */
    function node_add($node_parent_id)
    {
        global $kernel;
        $query = "SELECT MAX(`order_number`) + 1 AS `order`
                 FROM `".$kernel->pub_prefix_get()."_structure`
                 WHERE `parent_id` = '".$node_parent_id."' ";
	    $result = mysql_fetch_assoc($kernel->runSQL($query));
        $neworder = intval($result['order'])+1;
        $num=0;
        do
        {
            $node_new_id = $node_parent_id.'_sub'.(++$num);
        }
        while($kernel->db_get_record_simple('_structure',"`id`='".$node_new_id."'",'id'));

        $node_new_text = $kernel->pub_page_textlabel_replace('[#admin_new_struct_page_name#]');
        $query = "INSERT INTO `".$kernel->pub_prefix_get()."_structure`
                  (`id` ,`parent_id` ,`caption` ,`order_number` ,`properties` ,`serialize`)
                  VALUES
                  ('".$node_new_id."', '".$node_parent_id."', '".$node_new_text."', '".$neworder."', NULL , NULL)";
        $kernel->runSQL($query);
    	$this->structure_reorder($node_new_id);
    	return $node_new_id;
    }

    /**
     * Удаляет существующую страницу из структуры
     *
     * @param string $node_id
     */
    function node_remove($node_id)
    {
        global $kernel;
        $children = $this->node_get_children($node_id);
        $children[] = '"'.$node_id.'"';
    	$query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_structure` WHERE `id` IN ('.implode(', ', $children).') LIMIT '.(count($children)).';';
    	$kernel->runSQL($query);
    	$this->structure_reorder($node_id);
    }

    function node_get_children($node_id)
    {
        global $kernel;
        $children = array();
    	$query = "SELECT `id` FROM `".$kernel->pub_prefix_get()."_structure` WHERE `parent_id` = '".$node_id."'";
    	$result = $kernel->runSQL($query);
    	while ($row = mysql_fetch_assoc($result))
    	{
            $children[] = '"'.$row['id'].'"';
            $children = array_merge($children, $this->node_get_children($row['id']));
    	}
        mysql_free_result($result);
    	return $children;
    }

    /**
     * Производит пересортировку страниц в структуре
     *
     * Вызывается после того как страница была перещена по дереву
     * @param string $node_current_id
     * @param string $node_current_index
     * @return void
     */
    function structure_reorder($node_current_id, $node_current_index = null)
    {
        global $kernel;

        $query = "SELECT * FROM `".$kernel->pub_prefix_get()."_structure` WHERE `parent_id` = (SELECT `parent_id` FROM `".$kernel->pub_prefix_get()."_structure` WHERE `id` = '".$node_current_id."') ".((!is_null($node_current_index))?(" AND `id` != '".$node_current_id."'"):(''))." ORDER BY `order_number` ASC";
//        if (!is_null($node_current_index))
//            $query .= " AND `id` != '".$node_current_id."'";
        $result = $kernel->runSQL($query);

        $order = 0;
        while ($row = mysql_fetch_assoc($result))
        {
            if ($node_current_index == $order)
                $order++;
            $query = "UPDATE `".$kernel->pub_prefix_get()."_structure` SET `order_number` = '".$order++."' WHERE `id` = '".$row['id']."' LIMIT 1 ;";
            $kernel->runSQL($query);
        }
        mysql_free_result($result);
    }
}
