(function($) {
	$.fn.inputDefualts = function(options) {
		var defaults = {
			text: this.attr('title')
		}, opts = $.extend(defaults, options);
		this.val(opts['text']);
		this.focus(function() {
			if($(this).val() == opts['text']) {
				$(this).val('');
			}
		});
		this.blur(function() {
			if($(this).val() == '') {
				$(this).val(opts['text']);
			}
		});
	};
})(jQuery);

$(function() {
	$(window).scroll(createFooter)
			 .resize(createFooter);

	createTips();
	createNifty();
	createFooter();
	createFooter();
	createOverlay();
});

createOverlay = function() {
	var pics = $('#photo a')
		overlay = '#gallery';

	if (pics.length && $(overlay).length) {
		pics.overlay({
			target: overlay,
			expose: '#f1f1f1'
		}).gallery({
			speed: 800,
			template: '<strong>${title}</strong> <span>Изображение ${index} из ${total}</span>'
		})
	}
};

createTips = function() {
	var inputs = $(':input[title], textarea[title]');

	if (inputs.length) {
		inputs.each(function() {
			$(this).inputDefualts()
		});
	}
};

createNifty = function() {
	Nifty('#header form', 'right transparent');
	Nifty('#search form', 'right transparent');
	Nifty('#menu', 'transparent');
	Nifty('#feedback', 'transparent');
};

createFooter = function() {
	var footer = $('#footer'),
		container = $('div.container');

	if (footer.length && container.length) {
		if ($(document.body).height() > $(window).height()) {
			//alert(container.height() - footer.height());
			footer.css({
				position: 'absolute',
				right: '0',
				top: container.height() + 'px'
			});
		}
	}
};