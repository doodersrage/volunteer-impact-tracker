(function () {
	var i18n = window.commonscribeAdmin || {};

	document.querySelectorAll('.commonscribe-copy-link').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var url = btn.getAttribute('data-url') || '';
			var done = function () {
				var original = btn.textContent;
				btn.textContent = i18n.copied || 'Copied!';
				window.setTimeout(function () {
					btn.textContent = original;
				}, 1500);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(done).catch(function () {
					window.prompt(i18n.copyPrompt || 'Copy this certificate link:', url);
				});
			} else {
				window.prompt(i18n.copyPrompt || 'Copy this certificate link:', url);
			}
		});
	});

	var selectAll = document.getElementById('commonscribe-select-all');
	if (selectAll) {
		selectAll.addEventListener('change', function () {
			var checked = selectAll.checked;
			document.querySelectorAll('.commonscribe-bulk-id').forEach(function (box) {
				box.checked = checked;
			});
		});
	}

	document.querySelectorAll('.commonscribe-confirm-delete').forEach(function (link) {
		link.addEventListener('click', function (event) {
			if (!window.confirm(i18n.confirmDelete || 'Delete this entry?')) {
				event.preventDefault();
			}
		});
	});
})();
