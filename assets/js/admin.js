/* WooKit Configurator – admin */
(function ($) {
	'use strict';

	function reindex($repeater) {
		$repeater.find('.wkc-rows > .wkc-row').each(function (i) {
			$(this).find('[name]').each(function () {
				this.name = this.name.replace(/\[(categories|slots)\]\[[^\]]*\]/, '[$1][' + i + ']');
			});
		});
	}

	$(function () {
		// Repeater.
		$('.wkc-repeater').each(function () {
			var $rep = $(this);
			$rep.find('.wkc-rows').sortable({
				handle: '.wkc-row-handle',
				axis: 'y',
				update: function () { reindex($rep); }
			});
		});

		$(document).on('click', '.wkc-row-add', function () {
			var $rep = $(this).closest('.wkc-repeater');
			var tpl = $('#' + $rep.data('template')).html();
			var idx = $rep.find('.wkc-rows > .wkc-row').length;
			$rep.find('.wkc-rows').append(tpl.replace(/__INDEX__/g, idx));
			reindex($rep);
		});

		$(document).on('click', '.wkc-row-remove', function () {
			if (!window.confirm(wkcAdmin.confirmDel)) { return; }
			var $rep = $(this).closest('.wkc-repeater');
			$(this).closest('.wkc-row').remove();
			reindex($rep);
		});

		// Media picker.
		$(document).on('click', '.wkc-image-select', function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('.wkc-image-picker');
			var frame = wp.media({
				title: wkcAdmin.chooseImage,
				button: { text: wkcAdmin.useImage },
				library: { type: 'image' },
				multiple: false
			});
			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
				$wrap.find('.wkc-image-id').val(att.id);
				$wrap.find('.wkc-image-preview').html('<img src="' + url + '" alt="">');
				$wrap.addClass('has-image');
			});
			frame.open();
		});

		$(document).on('click', '.wkc-image-remove', function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('.wkc-image-picker');
			$wrap.find('.wkc-image-id').val('');
			$wrap.find('.wkc-image-preview').empty();
			$wrap.removeClass('has-image');
		});

		// Tabs.
		$(document).on('click', '.wkc-tab-nav a', function (e) {
			e.preventDefault();
			var $tabs = $(this).closest('.wkc-tabs');
			$tabs.find('.wkc-tab-nav a').removeClass('is-active');
			$tabs.find('.wkc-tab').removeClass('is-active');
			$(this).addClass('is-active');
			$tabs.find($(this).attr('href')).addClass('is-active');
		});

		// Color picker.
		$('.wkc-color').wpColorPicker();
	});
})(jQuery);
