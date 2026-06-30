/**
 * Frontend logic for account registration
 * Integrates jQuery AJAX and Bootstrap toast notifications
 */

$(document).ready(function () {
    const $registerForm = $('#registrationForm');
    const $submitBtn = $('#submitRegisterBtn');
    const $spinner = $('#submitSpinner');
    const $toastElement = $('#statusToast');
    const $toastMessage = $('#toastMessage');

    // Initialize Bootstrap Toast instance
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
     * Validate full name input
     */
    function validateName(name) {
        return name.trim().length >= 3;
    }

    /**
     * Validate email format
     */
    function validateEmailAddress(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    /**
     * Validate password strength (min 8 chars, 1 letter, 1 number)
     */
    function validatePasswordStrength(password) {
        if (password.length < 8) return false;
        const hasLetter = /[a-zA-Z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        return hasLetter && hasNumber;
    }

    // Trigger validation styling on input blur
    $('.form-control').on('blur input', function () {
        const $input = $(this);
        const id = $input.attr('id');
        let isValid = true;

        if (id === 'fullName') {
            isValid = validateName($input.val());
        } else if (id === 'emailAddress') {
            isValid = validateEmailAddress($input.val());
        } else if (id === 'password') {
            isValid = validatePasswordStrength($input.val());
        } else if (id === 'confirmPassword') {
            isValid = $input.val() === $('#password').val() && $input.val().length > 0;
        }

        if (isValid) {
            $input.removeClass('is-invalid').addClass('is-valid');
        } else {
            $input.removeClass('is-valid').addClass('is-invalid');
        }
    });

    // Handle button click for registration
    $submitBtn.on('click', function (e) {
        e.preventDefault();

        const fullName = $('#fullName').val();
        const email = $('#emailAddress').val();
        const password = $('#password').val();
        const confirmPassword = $('#confirmPassword').val();

        // Perform final check
        let isFormValid = true;

        if (!validateName(fullName)) {
            $('#fullName').addClass('is-invalid');
            isFormValid = false;
        }
        if (!validateEmailAddress(email)) {
            $('#emailAddress').addClass('is-invalid');
            isFormValid = false;
        }
        if (!validatePasswordStrength(password)) {
            $('#password').addClass('is-invalid');
            isFormValid = false;
        }
        if (password !== confirmPassword || confirmPassword.length === 0) {
            $('#confirmPassword').addClass('is-invalid');
            isFormValid = false;
        }

        if (!isFormValid) {
            showNotification('Please correct the validation errors in the form.', false);
            return;
        }

        // Disable inputs and buttons during execution
        $submitBtn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $registerForm.find('input').prop('disabled', true);

        // Submit via jQuery AJAX
        $.ajax({
            url: 'php/register.php',
            type: 'POST',
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            data: JSON.stringify({
                fullName: fullName,
                email: email,
                password: password,
                confirmPassword: confirmPassword
            }),
            success: function (response) {
                showNotification(response.message, true);
                
                // Redirect user after short delay
                setTimeout(function () {
                    window.location.href = 'login.html';
                }, 2000);
            },
            error: function (xhr) {
                let errorMessage = 'An unexpected server error occurred.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                showNotification(errorMessage, false);
                
                // Re-enable form fields
                $submitBtn.prop('disabled', false);
                $spinner.addClass('d-none');
                $registerForm.find('input').prop('disabled', false);
            }
        });
    });
});
