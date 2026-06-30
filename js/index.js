/**
 * Frontend logic for dynamic landing page navigation
 */

$(document).ready(function () {
    const token = localStorage.getItem('auth_token');
    const $navContainer = $('#authNavContainer');

    $navContainer.empty();

    if (token) {
        // Authenticated users navigation UI
        $navContainer.append(`
            <a href="profile.html" class="btn btn-glass-primary">
                <i class="fa-solid fa-gauge me-2"></i>Go to Dashboard
            </a>
            <button id="indexLogoutBtn" class="btn btn-glass-secondary">
                <i class="fa-solid fa-right-from-bracket me-2"></i>Logout
            </button>
        `);

        // Handle logout trigger
        $('#indexLogoutBtn').on('click', function (e) {
            e.preventDefault();
            
            // Invalidate session on server side
            $.ajax({
                url: 'php/logout.php',
                type: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`
                },
                complete: function () {
                    // Clean local storage and refresh page
                    localStorage.removeItem('auth_token');
                    window.location.reload();
                }
            });
        });
    } else {
        // Anonymous visitors navigation UI
        $navContainer.append(`
            <a href="register.html" class="btn btn-glass-primary">
                <i class="fa-solid fa-user-plus me-2"></i>Create Account
            </a>
            <a href="login.html" class="btn btn-glass-secondary">
                <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
            </a>
        `);
    }
});
