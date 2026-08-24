/**
 * Super Commenter Frontend Script
 *
 * @package SunnyComments
 */

document.addEventListener('DOMContentLoaded', function() {
	var bar           = document.getElementById('sunnycom-super-bar');
	var toggle        = document.getElementById('sunnycom-toggle-virtual-mode');
	var nameInput     = document.getElementById('sunnycom-virtual-name');
	var dateInput     = document.getElementById('sunnycom-custom-date');
	var avatarInput   = document.getElementById('sunnycom-virtual-avatar');
	var avatarPreview = document.getElementById('sunnycom-sc-avatar-preview');
	var selectBtn     = document.getElementById('sunnycom-sc-select-avatar-btn');
	var removeBtn     = document.getElementById('sunnycom-sc-remove-avatar-btn');
	var parentIdInput = document.getElementById('comment_parent');

	if (!bar || !dateInput) return;

	/**
	 * Manage Virtual Mode state and toggle standard avatar picker grid visibility.
	 *
	 * @param {boolean} isVirtual Active state flag.
	 */
	function syncVirtualState(isVirtual) {
		var avatarPickerWrapper = document.getElementById('sunnycom_avatar_picker_main_wrapper');

		if (isVirtual) {
			bar.classList.add('virtual-active');
			if (toggle) toggle.checked = true;

			if (avatarPickerWrapper) {
				avatarPickerWrapper.classList.remove('sunnycom-sc-admin-hidden');
			}
		} else {
			bar.classList.remove('virtual-active');
			if (toggle) toggle.checked = false;

			if (avatarPickerWrapper) {
				avatarPickerWrapper.classList.add('sunnycom-sc-admin-hidden');
			}
		}
	}

	// Restore state from LocalStorage on page load
	var isSavedVirtual = localStorage.getItem('sunnycom_sc_active') === '1';
	syncVirtualState(isSavedVirtual);

	if (nameInput && localStorage.getItem('sunnycom_sc_name')) {
		nameInput.value = localStorage.getItem('sunnycom_sc_name');
	}

	if (avatarInput && localStorage.getItem('sunnycom_sc_avatar')) {
		var savedAvatar = localStorage.getItem('sunnycom_sc_avatar');
		avatarInput.value = savedAvatar;
		avatarPreview.innerHTML = '<img src="' + savedAvatar + '" />';
		removeBtn.style.display = 'inline-block';
	}

	if (localStorage.getItem('sunnycom_sc_date')) {
		dateInput.value = localStorage.getItem('sunnycom_sc_date');
	}

	// Handle Virtual Mode toggle events
	if (toggle) {
		toggle.addEventListener('change', function() {
			var isActive = this.checked;
			syncVirtualState(isActive);
			localStorage.setItem('sunnycom_sc_active', isActive ? '1' : '0');
		});
	}

	if (nameInput) {
		nameInput.addEventListener('input', function() {
			localStorage.setItem('sunnycom_sc_name', this.value);
		});
	}

	dateInput.addEventListener('change', function() {
		localStorage.setItem('sunnycom_sc_date', this.value);
	});

	// WP Media Library Frame initialization for custom avatar upload
	var mediaFrame;
	if (selectBtn) {
		selectBtn.addEventListener('click', function(e) {
			e.preventDefault();
			if (mediaFrame) {
				mediaFrame.open();
				return;
			}
			mediaFrame = wp.media({
				title: 'Select or Upload Virtual Avatar',
				button: { text: 'Use Avatar' },
				multiple: false
			});
			mediaFrame.on('select', function() {
				var attachment = mediaFrame.state().get('selection').first().toJSON();
				avatarInput.value = attachment.url;
				avatarPreview.innerHTML = '<img src="' + attachment.url + '" />';
				removeBtn.style.display = 'inline-block';
				localStorage.setItem('sunnycom_sc_avatar', attachment.url);
			});
			mediaFrame.open();
		});
	}

	if (removeBtn) {
		removeBtn.addEventListener('click', function(e) {
			e.preventDefault();
			avatarInput.value = '';
			avatarPreview.innerHTML = '<span class="sunnycom-avatar-placeholder">&#128100;</span>';
			this.style.display = 'none';
			localStorage.removeItem('sunnycom_sc_avatar');
		});
	}

	// Update minimum allowed date when replying to a child comment
	document.addEventListener('click', function(e) {
		var replyBtn = e.target.closest('.comment-reply-link');
		if (replyBtn) {
			setTimeout(function() {
				var parentId = parentIdInput ? parentIdInput.value : 0;
				if (parentId > 0) {
					var parentComment = document.getElementById('comment-' + parentId);
					if (parentComment) {
						var timeElem = parentComment.querySelector('time[datetime]');
						if (timeElem) {
							var rawDatetime = timeElem.getAttribute('datetime');
							var dateObj = new Date(rawDatetime);

							// Parse ISO date and format it to client local ISO string YYYY-MM-DDTHH:mm
							if (!isNaN(dateObj.getTime())) {
								var year    = dateObj.getFullYear();
								var month   = String(dateObj.getMonth() + 1).padStart(2, '0');
								var day     = String(dateObj.getDate()).padStart(2, '0');
								var hours   = String(dateObj.getHours()).padStart(2, '0');
								var minutes = String(dateObj.getMinutes()).padStart(2, '0');

								var parentDateIso = year + '-' + month + '-' + day + 'T' + hours + ':' + minutes;

								dateInput.setAttribute('min', parentDateIso);
								if (dateInput.value && dateInput.value < parentDateIso) {
									dateInput.value = parentDateIso;
								}
							}
						}
					}
				}
			}, 100);
		}
	});
});