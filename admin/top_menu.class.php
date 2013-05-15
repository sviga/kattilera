<?php

class top_menu
{
    /**
     * Шаблон для построения меню
     *
     * Заполняется конструктоуром и содержит массив блоков,
     * используемых в шаблоне
     * @var array
     * @access private
     */
    var $template = array();


	function top_menu()
    {
        global $kernel;

        $this->template = $kernel->pub_template_parse("admin/templates/default/topmenu.html");
    }

    /*************************************************************************


    *************************************************************************/




    //*************************************************************************
    /**
    * Возвращает меню для дуступа к доступным модулям, в зависимости от прав
	  в верхней части меню мы покажем только базовые модуль
    * @return HTML
    * @param
	*
    */
	function create_menu_moduls()
    {
    	global $kernel;

    	//Узнаем какие модули надо вывести
    	//Будем выводить только те, у которых есть админки
    	$query = "SELECT *
        	      FROM `".$kernel->pub_prefix_get()."_modules`
                  WHERE
                  (parent_id is NULL)
                  AND (type_admin > 0)
                  AND (id != 'kernel')
                  ";

        $result = $kernel->runSQL($query);
        $menus = array();
        while ($row = mysql_fetch_assoc($result))
        {
        	$show = $kernel->priv_admin_access_for_group_get('',$row['id']);
    		if (!$show)
    			continue;
			$menus[$row['id']] = $row['full_name'];
        }

		$menus = $kernel->priv_access_set($menus);
        $out = $this->create_menu_moduls_html($menus);

		return $out;

    }

    //*************************************************************************
    /**
    * Формирует HTML код меню по массиву (используется для меню "модулей")
    * @return HTML
    * @param  array $menus
    */
	function create_menu_moduls_html($menus)
    {
    	global $kernel;

    	if (count($menus) == 0)
			return '';


		$modul = $kernel->pub_section_current_get();


		//Сначала пройдёмся по массиву и сформируем небольшую рыбу, что бы было проще строить меню
		$fish_menu = array();
		foreach ($menus as $key => $val)
		{
			$tmp_str = '';
        	if ($modul == $key)
        	{
        		$tmp_str .= '<td><table background="images/menu_select_c.gif" height="24" border="0" cellspacing="0" cellpadding="0">';
        		$tmp_str .= '<tr>';
        		$tmp_str .= '<td width="7" nowrap="nowrap"><img src="images/menu_select_s.gif" width="7" height="24" border=0></td>';
        		$tmp_str .= '<td  nowrap="nowrap"><span style="color:#ffffff"><b>'.$val.'</b></span></td>';
        		$tmp_str .= '<td width="7" nowrap="nowrap"><img src="images/menu_select_e.gif" width="7" height="24" border=0></td></tr></table></td>';
        	} else
        	{
        		$tmp_str .= '<td><table background="images/menu_norm_c.gif" height="24" border="0" cellspacing="0" cellpadding="0">';
        		$tmp_str .= '<tr>';
        		$tmp_str .= '<td width="7"  nowrap="nowrap"><img src="images/menu_norm_s.gif" width="7" height="24" border=0></td>';
        		$tmp_str .= '<td  nowrap="nowrap"><a target="_top" href="'.SSL_CONNECTION?'https':'http'.'://'.$_SERVER['HTTP_HOST'].'/admin/?section='.$key.'" class="menu_mod">'.$val.'</a></td>';
        		$tmp_str .= '<td width="7"  nowrap="nowrap"><img src="images/menu_norm_e.gif" width="7" height="24" border=0></td></tr></table></td>';
        	}
        	$fish_menu[$key] = $tmp_str;
		}


		//Теперь из рыбы делаем окончательный вид меню. С тенями.
		$out = '';
		$out .= '<table height="24" border="0" cellspacing="0" cellpadding="0"><tr>';
		$out .= '<td width="12"><img src="images/menu_shadow_s.png" width="12" height="24" border=0></td>';
		$out .= join('<td width="3"><img src="images/menu_shadow_c.png" width="3" height="24" border=0></td>',$fish_menu);
		$out .= '<td width="9"><img src="images/menu_shadow_e.png" width="9" height="24" border=0></td>';
		$out .= '</tr></table>';
        $out .= '</tr></table>';

		return $out;

    }





}

?>