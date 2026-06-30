/**
 * Frontend logic for authentication
 * Integrates jQuery AJAX and Browser LocalStorage session handling
 */

$(document).ready(function () {
    // If user has a session token in LocalStorage, auto-redirect to Profile
    if (localStorage.getItem('auth_token')) {
        window.location.href = 'profile.html';
        return;
    }

    const $loginForm = $('#loginForm');
    const $submitBtn = $('#submitLoginBtn');
    const $spinner = $('#submitSpinner');
    const $toastElement = $('#statusToast');
    const $toastMessage = $('#toastMessage');

    // Initialize Bootstrap Toast
    const toast = new bootstrap.Toast($toastElement[0], {
        delay: 4000
    });

    /**
     * Show custom notification toast
     */
    function showNotification(message, isSuccess = true) {
        $toastMessage.text(message);
        $toastElement.removeClass('toast-success toast-error');
        if (isSuccess) {
            $toastElement.addClass('toast-success');
        } else {
            $toastElement.addClass('toast-error');
        }
        toast.show();
    }

    /**
     * Validate email format
     */
    function validateEmailAddress(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    // Trigger validation styling on input blur/input
    $('.form-control').on('blur input', function () {
        const $input = $(this);
        const id = $input.attr('id');
        let isValid = true;

        if (id === 'emailAddress') {
            isValid = validateEmailAddress($input.val());
        } else if (id === 'password') {
            isValid = $input.val().trim().length > 0;
        }

        if (isValid) {
            $input.removeClass('is-invalid').addClass('is-valid');
        } else {
            $input.removeClass('is-valid').addClass('is-invalid');
        }
    });

    // Handle login submission
    $submitBtn.on('click', function (e) {
        e.preventDefault();

        const email = $('#emailAddress').val();
        const password = $('#password').val();

        // Perform final check
        let isFormValid = true;

        if (!validateEmailAddress(email)) {
            $('#emailAddress').addClass('is-invalid');
            isFormValid = false;
        }
        if (password.trim().length === 0) {
            $('#password').addClass('is-invalid');
            isFormValid = false;
        }

        if (!isFormValid) {
            showNotification('Please enter correct credentials details.', false);
            return;
        }

        // Disable input elements during AJAX submit
        $submitBtn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $loginForm.find('input').prop('disabled', true);

        // Submit via jQuery AJAX
        $.ajax({
            url: 'php/login.php',
            type: 'POST',
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            data: JSON.stringify({
                email: email,
                password: password
            }),
            success: function (response) {
                showNotification(response.message, true);
                
                // Store Session Token inside LocalStorage instead of PHP Session
                if (response.data && response.data.token) {
                    localStorage.setItem('auth_token', response.data.token);
                }

                // Redirect to Profile Page
                setTimeout(function () {
                    window.location.href = 'profile.html';
                }, 1500);
            },
            error: function (xhr) {
                let errorMessage = 'Authentication failed. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                showNotification(errorMessage, false);

                // Reset and re-enable inputs
                $submitBtn.prop('disabled', false);
                $spinner.addClass('d-none');
                $loginForm.find('input').prop('disabled', false);
                $('#password').val(''); // Clear failed password field
                $('#password').removeClass('is-valid is-invalid');
            }
        });
    });
});
