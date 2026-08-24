document.addEventListener('DOMContentLoaded', function () {
    if (typeof sunnygdpr_data === 'undefined') {
        return;
    }

    const COOKIE_NAME = sunnygdpr_data.cookie_name || 'sunnygdpr_consent';
    const acceptBtn   = document.getElementById('sunnygdpr-accept-btn');
    const declineBtn  = document.getElementById('sunnygdpr-decline-btn');

    function setCookie(name, value, days) {
        let expires = '';
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = `; expires=${date.toUTCString()}`;
        }
        document.cookie = `${name}=${value || ''}${expires}; path=/; SameSite=Lax`;
    }

    // Клик по кнопке "Принять" — ставим куку и перезагружаем страницу
    if (acceptBtn) {
        acceptBtn.addEventListener('click', function () {
            setCookie(COOKIE_NAME, 'accepted', 365);
            window.location.reload();
        });
    }

    // Клик по кнопке "Отклонить" — вылетает на страницу отказа
    if (declineBtn) {
        declineBtn.addEventListener('click', function () {
            window.location.href = sunnygdpr_data.declined_url;
        });
    }
});