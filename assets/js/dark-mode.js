document.addEventListener('DOMContentLoaded', () => {
    const themeToggleButton = document.getElementById('theme-toggle');
    const body = document.body;

    if (!themeToggleButton) {
        return;
    }

    // Check for saved theme in localStorage
    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-mode');
        themeToggleButton.innerHTML = '<i class="bi bi-sun"></i>';
    } else {
        themeToggleButton.innerHTML = '<i class="bi bi-moon"></i>';
    }

    themeToggleButton.addEventListener('click', () => {
        body.classList.toggle('dark-mode');

        // Save theme preference to localStorage
        if (body.classList.contains('dark-mode')) {
            localStorage.setItem('theme', 'dark');
            themeToggleButton.innerHTML = '<i class="bi bi-sun"></i>';
        } else {
            localStorage.setItem('theme', 'light');
            themeToggleButton.innerHTML = '<i class="bi bi-moon"></i>';
        }
    });
}); 