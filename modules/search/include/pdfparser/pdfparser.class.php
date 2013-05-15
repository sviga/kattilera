<?php

class PdfParser /*extends WebContentParser */
{
	var $default_charset = "windows-1251";
	var $data;
	var $object_data = array();
	var $tounucodes;
	var $charset_differences;
	var $text = "";
	var $encrypted = false;

	function PdfParser($contents)
	{
		$this->data = $contents;
	}


/*	function get_words_and_its_tags()
	{
		$text = $this->text2words($this->get_text());
		return array('text' => $text, 'words' => $this->text2words($text), 'tags' => array());
	}
	*/
	function parse()
	{
		$this->check_if_encrypted();
		if ($this->encrypted)
			return;

		$this->search_obj_starts();
		$this->search_pages();
	}


	function get_text()
	{
		return $this->text;
	}

	function check_if_encrypted()
	{
		if (preg_match("'trailer.+?/Encrypt's", $this->data))
			$this->encrypted = true;
	}

	function search_obj_starts()
	{
		if (preg_match_all("/(\d+?)\s+?(\d+?)\sobj/si", $this->data, $matches, PREG_OFFSET_CAPTURE | PREG_PATTERN_ORDER))
		{

			foreach ($matches[0] as $key => $arr)
			{
				$this->object_data[$matches[1][$key][0]." ".$matches[2][$key][0]] = array('text'=>$arr[0], 'position'=>$arr[1]);
			}
		}
	}



	function search_pages()
	{

		foreach ($this->object_data as $object_key => $object_start)
		{
			$pdfobject = PdfObject::createobject($this, $object_key);

			if (!is_array($pdfobject->dictionary_array))
				continue;

			if (isset($pdfobject->dictionary_array["/Type"]) && $pdfobject->dictionary_array["/Type"] == "/Page")
			{

				if (!$this->fill_tounicodes($pdfobject->dictionary_array))
				{
					$parent = $this->get_dictionary($pdfobject->dictionary_array['/Parent']);
					if (!$this->fill_tounicodes($parent) && isset($parent["/Parent"]))
					{
						$parent = $this->get_dictionary($parent['/Parent']);
						$this->fill_tounicodes($parent);
					}
				}

				if (!isset($pdfobject->dictionary_array["/Contents"]))
					continue;

				$contents_array = $this->links_to_array($pdfobject->dictionary_array["/Contents"]);
				foreach ($contents_array as $key => $contents_obj_key)
				{
					$pdfobject = PdfObject::createobject($this, $contents_obj_key);
					$this->text .= $pdfobject->get_text()." ";
				}
			}

		}
		$this->text = preg_replace("/(\S)-(\r\n|\n)/", "\\1", $this->text);

	}


	function links_to_array($str)
	{
		if (preg_match_all("/((\d+?)\s+?(\d+?))\s+?R/i", $str, $matches))
			return $matches[1];
		else
			return array();
	}


	function fill_tounicodes($dictionary_array)
	{
		if (!isset($dictionary_array["/Resources"]))
			return false;

		$resources = $this->get_dictionary($dictionary_array["/Resources"]);
		if (!isset($resources["/Font"]))
			return false;

		$fonts = $this->get_dictionary($resources["/Font"]);


		foreach ($fonts as $font_key => $font_dictionary)
		{
			$font_dict = $this->get_dictionary($font_dictionary);

			if (isset($font_dict["/ToUnicode"]))
			{
				$links = $this->links_to_array($font_dict['/ToUnicode']);
				$link = $links[0];


				$pdfobject = PdfObject::createobject($this, $link);
				/* @var $dpfobject UgolPdfObject */
				$to_unicode = $pdfobject->get_tounicode($this->default_charset);

				if (is_array($to_unicode))
					$this->tounucodes[$font_key] = $to_unicode;
			}

			if (isset($font_dict["/Encoding"]))
			{
				$links = $this->links_to_array($font_dict['/Encoding']);
				if (is_array($links) && count($links) > 0)
				{
					$link = $links[0];
					$pdfobject = PdfObject::createobject($this, $link);
					$charset_difference = $pdfobject->get_charset_difference();
					$this->charset_differences[$font_key] = $charset_difference;
				}
			}
		}
		return true;
	}







	function get_dictionary($dictionary_or_link)
	{
		if (!is_array($dictionary_or_link))
		{
			$links = $this->links_to_array($dictionary_or_link);
			$pdfobject = PdfObject::createobject($this, $links[0]);
			$dictionary_or_link = $pdfobject->dictionary_array;
		}
		return $dictionary_or_link;
	}

}

/*
require_once("type1encoding/win-1251.inc.php");
require_once("pdfobject.class.php");
require_once("ugolpdfobject.class.php");
require_once("spacepdfobject.class.php");
require_once("kvadrpdfobject.class.php");
require_once("dictionaryparser.class.php");


$filename = "http://kes.ap/data/content/content_files/investors/corplib/rus050501slobodiner.pdf";
//$filename = "http://kes.ap/data/content/content_files/investors/corplib/rus050101slobodin.pdf";

$contents = file_get_contents($filename);

$pdfParser = new PdfParser($contents);
$pdfParser->parse();
print_r($pdfParser->get_text());

*/
//$PdfParser = new PdfParser("D:\\projects\\searchmodule\\classes\\pdfparser\\tests\\rus050215chikurov.pdf");

//$PdfParser = new PdfParser("D:\\projects\\searchmodule\\classes\\pdfparser\\tests\\rus050309slobodin.pdf");
//$PdfParser = new PdfParser("D:\\docs\\phpi9_2004.pdf");
//set_time_limit(0);
//$PdfParser = new PdfParser("D:\\docs\\PDFReference16.pdf");


// не работает декомпресс!!
//$PdfParser = new PdfParser("D:\\projects\\searchmodule\\classes\\pdfparser\\tests\\kesinvest.pdf");
//$PdfParser = new PdfParser("D:\\projects\\searchmodule\\classes\\pdfparser\\tests\\eng050617company.pdf");


// не работает кодировка!!
//$PdfParser = new PdfParser("D:\\projects\\searchmodule\\classes\\pdfparser\\tests\\trints041129.pdf");
//$PdfParser = new PdfParser("D:\\projects\\searchmodule\\classes\\pdfparser\\tests\\slobodin041230.pdf");


//$PdfParser = new PdfParser("D:\\projects\\searchmodule\\classes\\pdfparser\\tests\\rus050127chikurov.pdf");
//$PdfParser = new PdfParser("D:\\projects\\searchmodule\\classes\\pdfparser\\tests\\rus041228slobodin.pdf");
//$PdfParser->parse();
//print $PdfParser->text;



/*

set_time_limit(0);
$fp = fopen('resultat.txt', "w");
$dirname = "D:\\projects\\searchmodule\\classes\\pdfparser\\tests";
$dir = opendir($dirname);

while ($filename = readdir($dir))
{
	$full = $dirname."\\".$filename;
	if ($filename != '.' && $filename != '..' && is_file($full))
	{
		fwrite($fp, "*****************************************************\n");
		fwrite($fp, "$full\n");
		fwrite($fp, "*****************************************************\n");

		$PdfParser = new PdfParser(file_get_contents($full));
		$PdfParser->parse();
		if ($PdfParser->encrypted)
			fwrite($fp, "Encrypted!");
		else
			fwrite($fp, $PdfParser->get_text());
		fwrite($fp, "\n\n\n");

	}
}

fclose($fp);
*/



?>