В секции <HEAD> ставим это:

		<link href="/design/facebox/facebox.css" media="screen" rel="stylesheet" type="text/css" />
		<script src="/design/facebox/jquery.js" type="text/javascript"></script>
		<script src="/design/facebox/facebox.js" type="text/javascript"></script>
		<script type="text/javascript">
		    jQuery(document).ready(function($) {
		      $('a[rel*=facebox]').facebox()
		    })
		</script>


После этого вызываем любую картинку, слой или внешний ресурс с параметром rel="facebox"

<a href="http://www.santafox.ru" rel="facebox">Пример</a>