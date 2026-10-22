/* WooKit Configurator – frontend */
(function ($) {
	'use strict';

	var P = window.wkcParams || {};

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function formatPrice(value) {
		var c = P.currency || { symbol: '€', format: '%1$s%2$s', decimals: 2, decimal: ',', thousand: '.' };
		var n = Number(value || 0).toFixed(c.decimals);
		var parts = n.split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, c.thousand);
		var num = parts.join(c.decimal);
		return c.format.replace('%1$s', c.symbol).replace('%2$s', num).replace(/ /g, '\u00a0');
	}

	function Kit(el) {
		this.$el = $(el);
		this.data = JSON.parse(this.$el.find('.wkc-data').first().text());
		this.labels = this.data.labels || {};
		this.lineById = {};
		this.productById = {};
		this.state = [];
		var self = this;

		$.each(this.data.lines, function (_, line) {
			self.lineById[line.id] = line;
			$.each(line.products, function (_, p) {
				self.productById[p.id] = { product: p, line: line };
			});
		});

		for (var i = 0; i < this.data.slots; i++) {
			this.state.push({ line: null, product: null });
		}

		this.preselect();
		this.bind();
		this.renderAll();
	}

	Kit.prototype.firstLine = function () {
		for (var i = 0; i < this.data.lines.length; i++) {
			if (this.data.lines[i].products.length) { return this.data.lines[i]; }
		}
		return this.data.lines[0] || null;
	};

	Kit.prototype.isTaken = function (productId, slot) {
		if (this.data.duplicates) { return false; }
		for (var i = 0; i < this.state.length; i++) {
			if (i !== slot && this.state[i].product === productId) { return true; }
		}
		return false;
	};

	Kit.prototype.preselect = function () {
		var mode = this.data.preselect;
		if (mode === 'none') { return; }
		for (var s = 0; s < this.state.length; s++) {
			var line = this.firstLine();
			if (!line) { return; }
			this.state[s].line = line.id;
			if (mode === 'product') {
				for (var j = 0; j < line.products.length; j++) {
					var p = line.products[j];
					if (p.in_stock && !this.isTaken(p.id, s)) { this.state[s].product = p.id; break; }
				}
			}
		}
	};

	Kit.prototype.bind = function () {
		var self = this;
		var $el = this.$el;

		$el.on('click', '.wkc-line', function () {
			var slot = $(this).closest('.wkc-slot').data('slot');
			var id = $(this).data('line');
			if (self.state[slot].line !== id) {
				self.state[slot].line = id;
				self.state[slot].product = null;
			}
			self.renderSlot(slot);
			self.renderSummary();
		});

		$el.on('click', '.wkc-fragrance', function () {
			if ($(this).is('[aria-disabled="true"]')) { return; }
			var slot = $(this).closest('.wkc-slot').data('slot');
			self.state[slot].product = $(this).data('product');
			self.renderAll();
		});

		$el.on('click', '.wkc-item__remove', function () {
			var slot = $(this).data('slot');
			self.state[slot].product = null;
			self.renderAll();
		});

		$el.on('click', '.wkc-item__edit', function (e) {
			e.preventDefault();
			var slot = $(this).data('slot');
			var $slot = $el.find('.wkc-slot[data-slot="' + slot + '"]');
			var top = $slot.offset().top - 120;
			$('html, body').animate({ scrollTop: Math.max(0, top) }, 350);
			$slot.addClass('is-flash');
			setTimeout(function () { $slot.removeClass('is-flash'); }, 1200);
		});

		$el.on('click', '.wkc-carousel__nav', function () {
			var $track = $(this).siblings('.wkc-fragrances');
			var dir = $(this).hasClass('wkc-carousel__next') ? 1 : -1;
			$track[0].scrollBy({ left: dir * $track[0].clientWidth * 0.8, behavior: 'smooth' });
		});

		$el.find('.wkc-fragrances').on('scroll', function () {
			self.updateNav($(this).closest('.wkc-carousel'));
		});
		$(window).on('resize', function () {
			$el.find('.wkc-carousel').each(function () { self.updateNav($(this)); });
		});

		$el.on('click', '.wkc-add', function () { self.addToCart($(this)); });

		// Tastiera per gli elementi non-<button> (evita gli stili dei pulsanti del tema).
		$el.on('keydown', '[role="radio"], [role="button"]', function (e) {
			if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
				e.preventDefault();
				$(this).trigger('click');
			}
		});
	};

	Kit.prototype.updateNav = function ($carousel) {
		var track = $carousel.find('.wkc-fragrances')[0];
		if (!track) { return; }
		var overflow = track.scrollWidth - track.clientWidth > 4;
		$carousel.toggleClass('has-overflow', overflow);
		$carousel.find('.wkc-carousel__prev').prop('hidden', !overflow || track.scrollLeft <= 2);
		$carousel.find('.wkc-carousel__next').prop('hidden', !overflow || track.scrollLeft + track.clientWidth >= track.scrollWidth - 2);
	};

	Kit.prototype.renderAll = function () {
		for (var s = 0; s < this.state.length; s++) { this.renderSlot(s); }
		this.renderSummary();
	};

	Kit.prototype.renderSlot = function (slot) {
		var self = this;
		var st = this.state[slot];
		var $slot = this.$el.find('.wkc-slot[data-slot="' + slot + '"]');

		$slot.find('.wkc-line').each(function () {
			var on = $(this).data('line') === st.line;
			$(this).toggleClass('is-selected', on).attr('aria-checked', on ? 'true' : 'false');
		});

		var $block = $slot.find('.wkc-fragrance-block');
		var line = st.line ? this.lineById[st.line] : null;
		if (!line) { $block.prop('hidden', true); return; }
		$block.prop('hidden', false);

		var html = '';
		$.each(line.products, function (_, p) {
			var taken = self.isTaken(p.id, slot);
			var disabled = !p.in_stock || taken;
			var selected = st.product === p.id;
			html += '<div class="wkc-fragrance' + (selected ? ' is-selected' : '') + (disabled ? ' is-disabled' : '') + '"' +
				' role="radio" tabindex="0" aria-checked="' + (selected ? 'true' : 'false') + '"' +
				(disabled ? ' aria-disabled="true"' : '') +
				' data-product="' + p.id + '">' +
				'<span class="wkc-fragrance__media"><img src="' + esc(p.preview) + '" alt="" loading="lazy"></span>' +
				'<span class="wkc-fragrance__name">' + esc(p.fragrance) + '</span>' +
				(p.notes ? '<span class="wkc-fragrance__notes">' + esc(p.notes) + '</span>' : '') +
				(!p.in_stock ? '<span class="wkc-fragrance__badge">' + esc(self.labels.soldout) + '</span>' : '') +
				'</div>';
		});
		var $track = $block.find('.wkc-fragrances');
		var scroll = $track.scrollLeft();
		$track.html(html).scrollLeft(scroll);
		this.updateNav($block.find('.wkc-carousel'));
	};

	Kit.prototype.isComplete = function () {
		for (var i = 0; i < this.state.length; i++) {
			if (!this.state[i].product) { return false; }
		}
		return !!(this.data.box && this.data.box.in_stock);
	};

	Kit.prototype.renderSummary = function () {
		var self = this;
		var html = '';
		var total = 0;
		var titles = this.$el.find('.wkc-slot__title');

		$.each(this.state, function (slot, st) {
			var ref = st.product ? self.productById[st.product] : null;
			if (ref) {
				total += Number(ref.product.price);
				html += '<li class="wkc-item">' +
					'<span class="wkc-item__thumb"><img src="' + esc(ref.product.thumb) + '" alt=""></span>' +
					'<span class="wkc-item__body">' +
					'<span class="wkc-item__line">' + esc(ref.line.label) + '</span>' +
					'<span class="wkc-item__name">' + esc(ref.product.name) + '</span>' +
					'<a href="#" class="wkc-item__edit" data-slot="' + slot + '">' + esc(self.labels.edit) + '</a>' +
					'</span>' +
					'<span class="wkc-item__price">' + esc(formatPrice(ref.product.price)) + '</span>' +
					'<span role="button" tabindex="0" class="wkc-item__remove" data-slot="' + slot + '" aria-label="' + esc(P.i18n.remove) + '">&times;</span>' +
					'</li>';
			} else {
				var line = st.line ? self.lineById[st.line] : null;
				html += '<li class="wkc-item is-empty">' +
					'<span class="wkc-item__thumb"></span>' +
					'<span class="wkc-item__body">' +
					'<span class="wkc-item__line">' + esc(line ? line.label : $(titles[slot]).text()) + '</span>' +
					'<span class="wkc-item__name">' + esc(self.labels.empty) + '</span>' +
					'<a href="#" class="wkc-item__edit" data-slot="' + slot + '">' + esc(self.labels.edit) + '</a>' +
					'</span>' +
					'<span class="wkc-item__price"></span>' +
					'</li>';
			}
		});

		var box = this.data.box;
		if (box) {
			total += Number(box.price);
			html += '<li class="wkc-item is-box">' +
				'<span class="wkc-item__thumb"><img src="' + esc(box.thumb) + '" alt=""></span>' +
				'<span class="wkc-item__body">' +
				(box.label ? '<span class="wkc-item__line">' + esc(box.label) + '</span>' : '') +
				'<span class="wkc-item__name">' + esc(box.name) + '</span>' +
				(!box.in_stock ? '<span class="wkc-item__soldout">' + esc(self.labels.soldout) + '</span>' : '') +
				'</span>' +
				'<span class="wkc-item__price">' + esc(formatPrice(box.price)) + '</span>' +
				'</li>';
		}

		this.$el.find('.wkc-summary__list').html(html);
		this.$el.find('.wkc-summary__amount').text(formatPrice(total));
		this.$el.find('.wkc-add').prop('disabled', !this.isComplete());
	};

	Kit.prototype.notice = function (msg, type, link) {
		var $n = this.$el.find('.wkc-notice');
		var html = esc(msg);
		if (link) { html += ' <a href="' + esc(link) + '">' + esc(this.labels.viewCart) + '</a>'; }
		$n.removeClass('is-error is-success').addClass('is-' + type).html(html).prop('hidden', false);
	};

	Kit.prototype.addToCart = function ($btn) {
		var self = this;
		if (!this.isComplete()) { this.notice(P.i18n.missing, 'error'); return; }

		var products = $.map(this.state, function (st) { return st.product; });
		$btn.prop('disabled', true).addClass('is-loading');
		this.$el.find('.wkc-notice').prop('hidden', true);

		$.ajax({
			url: P.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: { kit_id: this.data.kitId, lang: this.data.lang || '', products: products }
		}).done(function (res) {
			if (res && res.success) {
				var d = res.data || {};
				if (d.fragments) {
					$.each(d.fragments, function (key, value) { $(key).replaceWith(value); });
				}
				$(document.body).trigger('added_to_cart', [d.fragments || {}, d.cart_hash || '', $btn]);
				$(document.body).trigger('wc_fragment_refresh');
				if (self.data.redirect) { window.location.href = self.data.redirect; return; }
				self.notice(self.labels.added, 'success', P.cartUrl);
			} else {
				self.notice((res && res.data && res.data.message) ? res.data.message : P.i18n.error, 'error');
			}
		}).fail(function () {
			self.notice(P.i18n.error, 'error');
		}).always(function () {
			$btn.removeClass('is-loading').prop('disabled', !self.isComplete());
		});
	};

	$(function () {
		$('[data-wkc]').each(function () {
			if (!this.wkcKit) { this.wkcKit = new Kit(this); }
		});
	});

	// Elementor (anteprima editor / popup).
	$(window).on('elementor/frontend/init', function () {
		if (window.elementorFrontend && window.elementorFrontend.hooks) {
			window.elementorFrontend.hooks.addAction('frontend/element_ready/shortcode.default', function ($scope) {
				$scope.find('[data-wkc]').each(function () {
					if (!this.wkcKit) { this.wkcKit = new Kit(this); }
				});
			});
		}
	});
})(jQuery);
