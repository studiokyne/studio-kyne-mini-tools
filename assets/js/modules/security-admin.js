(function () {
	'use strict';

	function updateVisibility() {
		document.querySelectorAll('[data-security-toggle]').forEach(function (toggle) {
			document.querySelectorAll('[data-depends-on="' + toggle.dataset.securityToggle + '"]').forEach(function (dep) {
				dep.style.display = toggle.checked ? '' : 'none';
			});
		});
	}

	function init() {
		document.querySelectorAll('[data-security-toggle]').forEach(function (toggle) {
			toggle.addEventListener('change', updateVisibility);
		});
		updateVisibility();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
