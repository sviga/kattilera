<?php
 require_once('php-captcha.inc.php');
 $root = $_SERVER['DOCUMENT_ROOT'];
 $root_path = preg_replace("/(\/)*$/im", "", $root).'/modules/comments/ttf/';
 $aFonts = array($root_path.'VeraBd.ttf', $root_path.'VeraIt.ttf', $root_path.'Vera.ttf');
 $oVisualCaptcha = new PhpCaptcha($aFonts, 120, 41);
 $oVisualCaptcha->SetCharSet('0-9');
 $oVisualCaptcha->Create();
?>
