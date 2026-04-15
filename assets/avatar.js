// Avatar dropdown toggle
const avatarBtn = document.getElementById('avatarBtn');
const avatarDropdown = document.getElementById('avatarDropdown');

if (avatarBtn && avatarDropdown) {
    avatarBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        avatarDropdown.classList.toggle('show');
    });

    document.addEventListener('click', function () {
        avatarDropdown.classList.remove('show');
    });

    avatarDropdown.addEventListener('click', function (e) {
        e.stopPropagation();
    });
}