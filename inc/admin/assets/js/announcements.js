/* global woodmartAnnouncements */
(function() {
	'use strict';

	function init() {
		var button  = document.querySelector('.xts-announcements-btn');
		var panel   = document.getElementById('xts-announcements');
		var overlay = document.querySelector('.xts-announcements-overlay');

		if (!button || !panel || !overlay) {
			return;
		}

		var closeButton = panel.querySelector('.xts-announcements-close');

		function isOpen() {
			return 'true' === button.getAttribute('aria-expanded');
		}

		function markSeen() {
			if ('0' === button.dataset.unread || 'undefined' === typeof woodmartAnnouncements) {
				return;
			}

			var data = new FormData();

			data.append('action', woodmartAnnouncements.action);
			data.append('security', woodmartAnnouncements.security);

			fetch(woodmartAnnouncements.ajaxUrl, {
				method     : 'POST',
				credentials: 'same-origin',
				body       : data
			}).catch(function() {});

			button.dataset.unread = '0';

			button.querySelectorAll('.xts-announcements-count, .xts-announcements-pill').forEach(function(el) {
				el.remove();
			});
			button.classList.remove('xts-has-pill');
		}

		function open() {
			panel.hidden   = false;
			overlay.hidden = false;
			button.setAttribute('aria-expanded', 'true');
			document.documentElement.classList.add('xts-announcements-opened');

			// Next frame so the transition runs after display change.
			requestAnimationFrame(function() {
				panel.classList.add('xts-opened');
				overlay.classList.add('xts-opened');
			});

			(closeButton || panel).focus();
			markSeen();
		}

		function close() {
			if (!isOpen()) {
				return;
			}

			panel.classList.remove('xts-opened');
			overlay.classList.remove('xts-opened');
			button.setAttribute('aria-expanded', 'false');
			document.documentElement.classList.remove('xts-announcements-opened');

			setTimeout(function() {
				if (!isOpen()) {
					panel.hidden   = true;
					overlay.hidden = true;
				}
			}, 250);

			button.focus();
		}

		button.addEventListener('click', function() {
			if (isOpen()) {
				close();
			} else {
				open();
			}
		});

		overlay.addEventListener('click', close);

		if (closeButton) {
			closeButton.addEventListener('click', close);
		}

		document.addEventListener('keydown', function(e) {
			if (!isOpen()) {
				return;
			}

			if ('Escape' === e.key) {
				e.preventDefault();
				close();
				return;
			}

			// Keep focus inside the panel.
			if ('Tab' === e.key) {
				var focusable = panel.querySelectorAll('a[href], button:not([disabled])');

				if (!focusable.length) {
					return;
				}

				var first = focusable[0];
				var last  = focusable[focusable.length - 1];

				if (e.shiftKey && (document.activeElement === first || document.activeElement === panel)) {
					e.preventDefault();
					last.focus();
				} else if (!e.shiftKey && document.activeElement === last) {
					e.preventDefault();
					first.focus();
				}
			}
		});
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
