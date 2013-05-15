<?php


class SpacePdfObject extends PdfObject
{
	var $after_obj_position;
	function SpacePdfObject(&$pdfparser, $key)
	{
		$this->pdfparser = &$pdfparser;
		$this->after_obj_position = $this->get_afterobj_position($pdfparser, $key);
		$this->parse();
	}

	function parse()
	{
		$ap = $this->after_obj_position;
		if (preg_match("/(.+?)endobj/s", $this->pdfparser->data, $matches, 0, $ap))
		{
			$this->data = $matches[1];
		}
	}

}



?>