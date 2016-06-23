(function($, undefined) {

$.nette.ext('spinner', {
	init: function () {
		this.spinner = this.createSpinner();
		this.spinner.appendTo('body');
	},
	start: function () {
		this.spinner.show(this.speed);
	},
	complete: function () {
		this.spinner.hide(this.speed);
	}
}, {
	createSpinner: function () {
		return $('<div>', {
			id: 'ajax-spinner',
			css: {
				display: 'none'
			},
			html: '<i class="fa fa-cog fa-2x fa-spin"></i>'
		});
	},
	spinner: null,
	speed: undefined
});

})(jQuery);
