/**
 * Avatar Picker Interactive Logic
 * All code comments are strictly in English.
 */

document.addEventListener('DOMContentLoaded', function() {
    const avatarItems   = document.querySelectorAll('.sunnycom-picker-item');
    const hiddenInput   = document.getElementById('sunnycom_selected_avatar');
    const toggleTrigger = document.getElementById('sunnycom_avatar_toggle_trigger');
    const gridWrapper   = document.getElementById('sunnycom_avatar_picker_grid_wrapper');
    const previewImg    = document.getElementById('sunnycom_current_avatar_img');
    const errorMsg      = document.getElementById('sunnycom_avatar_error_msg');
    const commentForm   = document.getElementById('commentform');

    if (!hiddenInput) {
        return;
    }

    /**
     * Helper to get cookie value
     */
    function getAvatarCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    /**
     * Helper to set cookie for 365 days
     */
    function setAvatarCookie(name, value, days) {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
    }

    // 1. Restore state on page load if user has previously selected an avatar
    const savedAvatar = getAvatarCookie('sunnycom_user_avatar') || hiddenInput.value;
    
    if (savedAvatar && gridWrapper && toggleTrigger) {
        hiddenInput.value = savedAvatar;
        gridWrapper.classList.add('sunnycom-hidden');
        toggleTrigger.classList.remove('sunnycom-hidden');

        avatarItems.forEach(function(el) {
            el.classList.remove('active');
            if (el.getAttribute('data-avatar') === savedAvatar) {
                el.classList.add('active');
                const imgInside = el.querySelector('img');
                if (imgInside && previewImg) {
                    previewImg.src = imgInside.src;
                }
            }
        });
    }

    // 2. Toggle grid display when preview avatar container is clicked
    if (toggleTrigger && gridWrapper) {
        toggleTrigger.addEventListener('click', function(e) {
            e.preventDefault();
            gridWrapper.classList.toggle('sunnycom-hidden');
        });
    }

    // 3. Avatar selection interaction
    if (avatarItems.length) {
        avatarItems.forEach(function(item) {
            item.addEventListener('click', function() {
                // Hide error message if user clicked an avatar
                if (errorMsg) {
                    errorMsg.classList.add('sunnycom-hidden');
                }

                avatarItems.forEach(function(el) {
                    el.classList.remove('active');
                });

                this.classList.add('active');

                const selectedAvatar = this.getAttribute('data-avatar');

                if (selectedAvatar) {
                    hiddenInput.value = selectedAvatar;
                    setAvatarCookie('sunnycom_user_avatar', selectedAvatar, 365);

                    if (previewImg) {
                        const imgInsideItem = this.querySelector('img');
                        if (imgInsideItem) {
                            previewImg.src = imgInsideItem.src;
                        }
                    }

                    if (toggleTrigger && gridWrapper) {
                        gridWrapper.classList.add('sunnycom-hidden');
                        toggleTrigger.classList.remove('sunnycom-hidden');
                    }
                }
            });
        });
    }

    // 4. Validate avatar selection before submit
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            // Check if hidden input has a value
            if (!hiddenInput.value || hiddenInput.value.trim() === '') {
                e.preventDefault(); // Stop form submission if no avatar selected
                
                if (errorMsg) {
                    errorMsg.classList.remove('sunnycom-hidden');
                }

                if (gridWrapper) {
                    gridWrapper.classList.remove('sunnycom-hidden');
                }

                const mainWrapper = document.getElementById('sunnycom_avatar_picker_main_wrapper');
                if (mainWrapper) {
                    mainWrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                return false;
            }
        });
    }
});