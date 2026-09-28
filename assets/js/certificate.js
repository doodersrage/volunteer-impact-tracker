document.addEventListener('DOMContentLoaded', function () {
	var button = document.querySelector('.commonscribe-print-btn');
	if (!button) {
		return;
	}
	button.addEventListener('click', function () {
		window.print();
	});
});
