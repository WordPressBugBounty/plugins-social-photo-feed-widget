(function() {
	let setNoticeMargins = function() {
		let bottom = 30;
		document.querySelectorAll('.trustindex-notice').forEach((notice) => {
			notice.style.bottom = bottom + 'px';
			bottom += notice.clientHeight + 20;
		});
	};

	setTimeout(() => {
		document.querySelectorAll('.trustindex-notice').forEach((notice) => {
			notice.style.removeProperty('left');
			notice.style.removeProperty('opacity');
		});
		setNoticeMargins();
	}, 1000);

	document.querySelectorAll('.trustindex-notice').forEach((notice) => {
		notice.addEventListener('click', (event) => {
			if (event.target.matches('.trustindex-notice-dismiss')) {
				let url = event.target.dataset.closeUrl;
				// [url, params] = url.split('?');
				fetch(url, {
					method: 'POST',
					// headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					// body: new URLSearchParams(params),
				}).then(() => {
					notice.animate({'opacity': ['1', '0']}, 300);
					setTimeout(() => {
						notice.remove();
						setNoticeMargins();
					}, 280);
				});
			} else {
				window.location = notice.dataset.redirectUrl;
			}
		});
	});
})();