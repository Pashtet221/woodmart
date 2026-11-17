/* global woodmartSpotlight */
(function() {
	'use strict';

	document.addEventListener('click', function(e) {
		var close = e.target.closest('.xts-spotlight-close');

		if (!close) {
			return;
		}

		var item = close.closest('.xts-spotlight');

		if (!item) {
			return;
		}

		item.classList.add('xts-hiding');

		setTimeout(function() {
			item.remove();
		}, 200);

		if ('undefined' === typeof woodmartSpotlight) {
			return;
		}

		var data = new FormData();

		data.append('action', woodmartSpotlight.action);
		data.append('security', woodmartSpotlight.security);
		data.append('id', item.dataset.id);

		fetch(woodmartSpotlight.ajaxUrl, {
			method     : 'POST',
			credentials: 'same-origin',
			body       : data
		}).catch(function() {});
	});
})();
