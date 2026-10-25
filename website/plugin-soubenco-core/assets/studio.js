/* Soubenco Product Studio */
(function ($) {
	'use strict';
	var money = function (n) { return '৳' + Math.round(n).toLocaleString('en-US'); };
	var colorMap = { black:'#16151a', midnight:'#1c1f2b', graphite:'#3b3d43', gray:'#8a8d93', grey:'#8a8d93', silver:'#c9ccd1', white:'#f6f6f6', starlight:'#ece4d6', cream:'#efe6d2', gold:'#d4af6a', rose:'#e8b7b0', pink:'#f2a7c3', red:'#d6333a', orange:'#f08a24', yellow:'#f3d14a', green:'#3aa76d', mint:'#a8e6cf', olive:'#7a7d3a', blue:'#2f6fe4', navy:'#1f2f5c', sky:'#8cc6f2', teal:'#1e9c9c', purple:'#7b3fe4', violet:'#6014ea', lavender:'#c9b6f2', titanium:'#a39e94', brown:'#7a5232', bronze:'#a46a3a', copper:'#b8733b', beige:'#d9c7a8' };
	function guessHex(name) { name = (name || '').toLowerCase(); for (var k in colorMap) if (name.indexOf(k) > -1) return colorMap[k]; return null; }

	$(function () {
		var $form = $('#sbs-form');
		if (!$form.length) return;

		/* Photos */
		var $gal = $('#sbs-gallery'), $imgs = $('#sbs-images');
		function syncImages() {
			$imgs.val($gal.find('.sbs-gal-item').map(function () { return $(this).data('id'); }).get().join(','));
			$gal.find('.sbs-gal-item').removeClass('is-main').first().addClass('is-main');
		}
		$gal.sortable({ items: '.sbs-gal-item', tolerance: 'pointer', update: syncImages });
		$gal.on('click', '.sbs-gal-x', function () { $(this).closest('.sbs-gal-item').remove(); syncImages(); });
		var frame;
		$('#sbs-gal-add').on('click', function () {
			if (!frame) {
				frame = wp.media({ title: 'Add product photos', button: { text: 'Use these photos' }, library: { type: 'image' }, multiple: 'add' });
				frame.on('select', function () {
					frame.state().get('selection').each(function (a) {
						var d = a.toJSON();
						if ($gal.find('[data-id="' + d.id + '"]').length) return;
						var src = (d.sizes && (d.sizes.thumbnail || d.sizes.medium) || d).url;
						$('<div class="sbs-gal-item" data-id="' + d.id + '"><img src="' + src + '" alt=""><button type="button" class="sbs-gal-x" aria-label="Remove">×</button></div>').insertBefore('#sbs-gal-add');
					});
					syncImages();
				});
			}
			frame.open();
		});
		syncImages();

		/* Category */
		$('#sbs-category').on('change', function () { $('.sbs-newcat').prop('hidden', this.value !== 'new'); });

		/* Repeaters */
		var tpl = {
			specs: '<div class="sbs-rep-row"><input name="spec_label[]" placeholder="e.g. Battery"><input name="spec_value[]" placeholder="e.g. 5000 mAh"><button type="button" class="sbs-rep-x">×</button></div>',
			colors: '<div class="sbs-rep-row sbs-color-row"><input type="color" name="color_hex[]" value="#cccccc"><input name="color_name[]" placeholder="e.g. Titanium Black"><button type="button" class="sbs-rep-x">×</button></div>'
		};
		$('[data-rep-add]').on('click', function () {
			var k = $(this).data('rep-add');
			$('#sbs-' + k).append(tpl[k]).find('input').last().focus();
		});
		$form.on('click', '.sbs-rep-x', function () { $(this).closest('.sbs-rep-row').remove(); });
		$form.on('change', 'input[name="color_name[]"]', function () {
			var $hex = $(this).siblings('input[type=color]'), g = guessHex(this.value);
			if (g && $hex.val() === '#cccccc') $hex.val(g);
		});

		/* Pricing */
		function dtype() { return $('input[name=dtype]:checked').val() || 'none'; }
		function refreshPrice() {
			var t = dtype();
			$('.sbs-dfield').each(function () {
				var d = $(this).data('d');
				$(this).prop('hidden', !(d === t || (d === 'any' && t !== 'none')));
			});
			var reg = parseFloat($('#sbs-regular').val()) || 0, now = reg, pct = 0;
			if (t === 'percent') { pct = parseFloat($('#sbs-dpct').val()) || 0; now = Math.round(reg * (1 - pct / 100)); }
			if (t === 'price') { var p = parseFloat($('#sbs-dprice').val()) || 0; if (p > 0 && p < reg) { now = p; pct = Math.round((reg - p) / reg * 100); } }
			var app = parseFloat($('#sbs-app').val()) || 0;
			var fest = $('#sbs-festive option:selected');
			var html = '';
			if (reg) {
				html += '<div class="sbs-prev-row"><span>Website</span><strong>' + money(now) + '</strong>' + (now < reg ? '<del>' + money(reg) + '</del><em>-' + pct + '%</em>' : '') + '</div>';
				if (app) {
					html += app < now
						? '<div class="sbs-prev-row sbs-prev-app"><span>In the app</span><strong>' + money(app) + '</strong><del>' + money(now) + '</del></div>'
						: '<div class="sbs-prev-warn">App price must be lower than the website price (' + money(now) + '), otherwise it is ignored.</div>';
				}
				if (now < reg && fest.val()) html += '<div class="sbs-prev-fest">' + fest.text() + ' badge will show</div>';
			}
			$('#sbs-preview').html(html).prop('hidden', !html);
		}
		$form.on('input change', '#sbs-regular,#sbs-dpct,#sbs-dprice,#sbs-app,#sbs-festive,input[name=dtype]', refreshPrice);
		$('#sbs-dpct').on('input', function () {
			var reg = parseFloat($('#sbs-regular').val()) || 0, pct = parseFloat(this.value) || 0;
			if (reg && pct) $('#sbs-dprice').val(Math.round(reg * (1 - pct / 100)));
		});
		refreshPrice();

		/* Simple checks before saving */
		$form.on('submit', function (e) {
			var missing = [];
			if (!$('input[name=name]').val().trim()) missing.push('product name');
			if (!$('#sbs-regular').val()) missing.push('price');
			if (!$('#sbs-category').val()) missing.push('category');
			if ($('#sbs-category').val() === 'new' && !$('input[name=new_category]').val().trim()) missing.push('new category name');
			if (!$('#sbs-images').val()) missing.push('at least one photo');
			var $msg = $('#sbs-form-msg');
			if (missing.length && (e.originalEvent && e.originalEvent.submitter ? e.originalEvent.submitter.value : 'publish') === 'publish') {
				e.preventDefault();
				if (!$msg.length) $msg = $('<div id="sbs-form-msg" class="sbs-ai-status is-error" role="alert"></div>').prependTo($form);
				$msg.text('Before publishing, please add: ' + missing.join(', ') + '.');
				$('html,body').animate({ scrollTop: $form.offset().top - 60 }, 200);
			} else if ($msg.length) { $msg.remove(); }
		});
	});
})(jQuery);
