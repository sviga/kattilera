<?php

class PdfObject
{

	/**
	 * PdfParser*
	 * @var  PdfParser
	 */
	var $pdfparser;
	var $data;
	var $dictionary_str = false;
	var $dictionary_array = false;

	function get_text()
	{
		return "";
	}

	function get_font_refs()
	{
		return false;
	}

	function get_tounicode_ref()
	{
		return false;
	}


	function get_tounicode($default_charset)
	{
		return false;
	}

	function get_data()
	{
		return $this->data;
	}

	// static
	function createobject(&$pdfparser, $key)
	{
		$after_obj = PdfObject::get_afterobj_position($pdfparser, $key);
		if ($after_obj === false)
		{
			return new PdfObject();
		}

		while (preg_match("/\s/", $pdfparser->data{$after_obj}))
			$after_obj++;

		if ($pdfparser->data{$after_obj} == "<" && $pdfparser->data{$after_obj + 1} == "<")
			$pdfobject =  new UgolPdfObject($pdfparser, $key);
		elseif ($pdfparser->data{$after_obj} == "[")
			$pdfobject =  new KvadrPdfObject($pdfparser, $key);
		else
			$pdfobject =  new SpacePdfObject($pdfparser, $key);

		return $pdfobject;
	}

	//static
	function get_afterobj_position(&$pdfparser, $key)
	{
		if (isset($pdfparser->object_data[$key]))
			$datum = $pdfparser->object_data[$key];
		else
			return false;

		$start = $datum['position'];
		$zagol_start = strlen($datum['text']);
		$after_obj = $start + $zagol_start;
		return $after_obj;
	}
}

?>