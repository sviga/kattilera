<?php
/*

	Класс для работы с урлами,
	например для преобразования в абсолютный на основании базы
	1 марта 2004

*/

class UrlParser
{
	var $page_url;
	var $base_url = "";

	/**
	* @return UrlParser
	* @param String $page_url
	* @param String $base_url
	* @desc Конструктор. $page_url - URL страницы; base_url - содержимое тега <base href="xxx"> (необязат.)
	*/
	function UrlParser($page_url, $base_url="")
	{
		$this->base_url = $base_url;
		if (empty($base_url))
			$this->page_url = $page_url;
		else
			$this->page_url = $this->get_common_url($page_url, $base_url);
	}


	/**
	* @return String
	* @param String $relative_url
	* @desc Делает из относительного урла абсолютный
	*/
	function get_absolute_url($relative_url)
	{
		$abs_url = $this->get_common_url($this->page_url, $relative_url);
		return $abs_url;
	}





	/***************** приватные методы **********************/

	function get_common_url($url, $base)
	{
		if (preg_match("/^javascript:/i", $base))
			return $base;

		if (preg_match("|^https?://|", $base))
			return $base;

		if (empty($base))
			return $url;


		if ($base{0} == "?")
		{
			$url = preg_replace("/\\?.*$/", "", $url);
			return $url.$base;
		}

		$url_info = parse_url ($url);
		$base_info = parse_url ($base);

		if (empty($url_info['path']))
			$url_info['path'] = '';
		$url_dir = $this->dir_name($url_info['path']);




		if ($url_dir == '\\' || $url_dir == ".")
			$url_dir = '';

		if (isset($base_info['path']))
			$base_info_path = $base_info['path'];
		else
			$base_info_path = "";


		$base_dir = $this->dir_name($base_info_path);

		if (strlen($base_info_path)>0)
		{
			if ($base_info_path{strlen($base_info_path)-1} != '/')
				$base_filename = basename($base_info_path);
			else
				$base_filename = "";
		}
		else
			$base_filename = "";

		$url_http_host = "{$url_info['scheme']}://{$url_info['host']}" . (empty($url_info['port']) ? '' : ":{$url_info['port']}");

		if ($base{0} == '/')
		{
			$new_url = $url_http_host.$base;
			return $new_url;
		}

		$base_dir .= '/';
		$new_dir = "$url_dir/$base_dir";

		$new_dir = str_replace("/./", "/", $new_dir);

		$points_exists = true;
		while ($points_exists)
		{
			if (preg_match("|/[^/]+?/../|", $new_dir, $matches))
				$new_dir = str_replace($matches[0], "/", $new_dir);
			else
				$points_exists = false;
		}

		// Эту строчку не стирать, сделана для ..
		$new_dir = rtrim($new_dir, '/');
		$new_path = "$new_dir/$base_filename";
		$new_url = $url_http_host.$new_path;
		if (isset($base_info['query']))
			$new_url .= "?".$base_info['query'];
		return $new_url;
	}


	function dir_name($path)
	{
		if (empty($path))
			return ".";
		elseif ($path=="/")
			return "\\";
		elseif (preg_match("|^(.+)/(([^/])+){0,1}$|", $path, $matches))
		{
			 $ret = $matches[1];
			 if ($ret == '/')
			 	$ret = '';
			 return $ret;
		}
		else
			return ".";
	}

}
