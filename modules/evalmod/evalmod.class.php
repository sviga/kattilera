<?php

class evalmod {

    public function pub_eval_code()
    {
        global $kernel;

        $code = $this->phpcode_get();

        if (!empty($code))
	 {
	 	ob_start();
              $eval_return = eval($code);

		$printed = ob_get_contents();
		ob_end_clean();
		return $printed.$eval_return;
	 }
        else
            return '';
    }


    /**
     * Функция формирования меню модуля
     *
     * @param pub_interface $menu
     */
    public function interface_get_menu($menu)
    {
        $menu->set_menu_block('[#evalmod_label_menu_block#]');
        $menu->set_menu('[#evalmod_label_menu_1#]', 'code_manage');
        $menu->set_menu_default('code_manage');
    }

    /**
     * Функция отображения административного интерфейса
     *
     * @return string
     */
    public function start_admin()
    {
        global $kernel;
        $kernel->pub_dir_create_in_files($kernel->pub_module_id_get());
        $content = '';
//        $this->pub_eval_file_set();
        switch ($kernel->pub_section_leftmenu_get())
        {
            // Запрос нод для категорий (JSON)
            case 'code_manage':
                $templates = $kernel->pub_template_parse('modules/evalmod/templates_admin/templates.html');
                $content = $templates['form'];

                $code = $this->phpcode_get();
                $code = htmlspecialchars($code);

                $content = str_replace('%code%', $code, $content);
                $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('code_save'), $content);
                break;


            case 'code_save':
                $code = $kernel->pub_httppost_get('code', false);
                $code = trim($code);
                if (!empty($code))
                    $this->phpcode_set($code);

                $kernel->pub_redirect_refresh_reload('code_manage');

                break;
        }

        return $content;
    }

    /**
     * Возвращает код PHP полученный из базы mySql
     *
     * @return unknown
     */
    private function phpcode_get()
    {
        global $kernel;

        $id_modul = $kernel->pub_module_id_get();
        $str = '';
        if (!empty($id_modul))
        {
            $sql = "SELECT `id_modul`, `text_php`
                    FROM `".$kernel->pub_prefix_get()."_evalmod`
                    WHERE `id_modul` = '".$id_modul."'
                    LIMIT 0,1";

            $result = $kernel->runSQL($sql);
            if ($result)
            {
                $row = mysql_fetch_array($result);
                $str = $row['text_php'];
            }

        }
        return $str;
    }

    /**
     * Записывает код из формы в базу данных
     *
     * @param string $str_code Записываемый код
     * @return boolean
     */
    private function phpcode_set($str_code)
    {
        global $kernel;

        $id_modul = $kernel->pub_module_id_get();
        $result = false;
        if (!empty($id_modul))
        {
            $query = 'REPLACE INTO `'.$kernel->pub_prefix_get().'_evalmod`
                      VALUES
                      ("'.$id_modul.'", "'.mysql_real_escape_string($str_code).'")
                      ';

            $result = $kernel->runSQL($query);
            if ($result)
                $result = true;
        }
        return $result;
    }
    /**
     * Выполненние PHP файла  из загруженных ранее
     *
     * @param string $f_name :Имя файла из загруженных в папку модуля
     * @return string 		 :результат на "чистый" вывод файла.
     * 					  Все, что должно отображаться, <br> должно быть отправлено в стандартный вывод
     */


    public function pub_eval_file_set($f_name="test.php")
    {
        global $kernel;
        global $temp_counter;
		ob_start();

		include($f_name);

		$printed = ob_get_contents();
		ob_end_clean();
		return ($printed);
    }


}

