/**
 * Frontend controller for Profile Dashboard
 * Coordinates token-based authorization and CRUD requests
 */

$(document).ready(function () {
    const token = localStorage.getItem('auth_token');

    // Redirect to login if token is missing
    if (!token) {
        window.location.href = 'login.html';
        return;
    }

    const $pageLoader = $('#pageLoader');
    const $profileForm = $('#profileForm');
    const $saveBtn = $('#saveProfileBtn');
    const $saveSpinner = $('#saveSpinner');
    const $toastElement = $('#statusToast');
    const $toastMessage = $('#toastMessage');
    const $skillsContainer = $('#skillsContainer');
    const $skillInput = $('#skillInput');

    let skillsList = []; // Holds list of interactive skill tags

    // Initialize Toast
    const toast = new bootstrap.Toast($toastElement[0], { delay: 4000 });

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
     * Compute initials for avatar badge
     */
    function setAvatarInitials(name) {
        if (!name) return '--';
        const parts = name.trim().split(/\s+/);
        if (parts.length >= 2) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return name.slice(0, 2).toUpperCase();
    }

    /**
     * Render skill badges in container
     */
    function renderSkills() {
        // Clear except placeholder
        $skillsContainer.find('.skill-badge').remove();
        
        if (skillsList.length === 0) {
            $('#noSkillsText').removeClass('d-none');
            return;
        }

        $('#noSkillsText').addClass('d-none');
        
        skillsList.forEach((skill, index) => {
            const badgeHTML = `
                <span class="skill-badge" data-index="${index}">
                    ${skill}
                    <i class="fa-solid fa-xmark remove-skill" data-index="${index}"></i>
                </span>
            `;
            $skillsContainer.append(badgeHTML);
        });
    }

    /**
     * Add skill helper
     */
    function addSkill(skillName) {
        const trimmed = skillName.trim();
        if (trimmed && !skillsList.includes(trimmed)) {
            skillsList.push(trimmed);
            renderSkills();
        }
        $skillInput.val('');
    }

    // Skill Tag Event Handlers
    $skillInput.on('keypress', function (e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            addSkill($(this).val());
        }
    });

    $('#addSkillBtn').on('click', function (e) {
        e.preventDefault();
        addSkill($skillInput.val());
    });

    $skillsContainer.on('click', '.remove-skill', function () {
        const index = $(this).data('index');
        skillsList.splice(index, 1);
        renderSkills();
    });

    /**
     * Load profile details from database
     */
    function loadProfile() {
        $.ajax({
            url: 'php/profile.php',
            type: 'GET',
            dataType: 'json',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            success: function (response) {
                if (response.success && response.data) {
                    const user = response.data.user;
                    const profile = response.data.profile;

                    // Fill account fields
                    $('#fullName').val(user.fullName || '');
                    $('#emailAddress').val(user.email || '');
                    $('#profileCardName').text(user.fullName || 'User');
                    $('#profileCardEmail').text(user.email || '');
                    $('#avatarInitials').text(setAvatarInitials(user.fullName));
                    
                    // Fill profile fields (from MongoDB)
                    $('#age').val(profile.age || '');
                    $('#dob').val(profile.dob || '');
                    $('#phone').val(profile.phone || '');
                    $('#address').val(profile.address || '');
                    $('#bio').val(profile.bio || '');

                    // Render Stats
                    $('#profileStatAge').text(profile.age ? `${profile.age} yrs` : 'Not Set');
                    $('#profileStatJoined').text(user.createdAt ? user.createdAt.split(' ')[0] : 'N/A');

                    // Set Skills list
                    skillsList = profile.skills || [];
                    renderSkills();
                }
                
                // Disable loading page overlay
                $pageLoader.removeClass('active');
            },
            error: function (xhr) {
                // Token verification failed or expired, clean storage and redirect
                localStorage.removeItem('auth_token');
                window.location.href = 'login.html';
            }
        });
    }

    // Initial Load execution
    loadProfile();

    /**
     * Save Profile Changes
     */
    $saveBtn.on('click', function (e) {
        e.preventDefault();

        const fullName = $('#fullName').val();
        const age = $('#age').val();
        const dob = $('#dob').val();
        const phone = $('#phone').val();
        const address = $('#address').val();
        const bio = $('#bio').val();

        // Basic validations
        let isValid = true;
        
        if (fullName.trim().length < 3) {
            $('#fullName').addClass('is-invalid');
            isValid = false;
        } else {
            $('#fullName').removeClass('is-invalid');
        }

        if (age && (parseInt(age) < 1 || parseInt(age) > 120)) {
            $('#age').addClass('is-invalid');
            isValid = false;
        } else {
            $('#age').removeClass('is-invalid');
        }

        if (phone && phone.trim().length < 10) {
            $('#phone').addClass('is-invalid');
            isValid = false;
        } else {
            $('#phone').removeClass('is-invalid');
        }

        if (!isValid) {
            showNotification('Please correct verification issues before saving.', false);
            return;
        }

        // Lock form during AJAX operation
        $saveBtn.prop('disabled', true);
        $saveSpinner.removeClass('d-none');
        $profileForm.find('input, textarea, button').prop('disabled', true);

        // Submit updated data via PUT/POST
        $.ajax({
            url: 'php/profile.php',
            type: 'POST', // Handled as POST update
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            data: JSON.stringify({
                fullName: fullName,
                age: age ? parseInt(age) : null,
                dob: dob || null,
                phone: phone,
                address: address,
                bio: bio,
                skills: skillsList
            }),
            success: function (response) {
                showNotification(response.message, true);
                
                // Update side profile information cards
                $('#profileCardName').text(fullName);
                $('#avatarInitials').text(setAvatarInitials(fullName));
                $('#profileStatAge').text(age ? `${age} yrs` : 'Not Set');
                $('#profileStatSync').text(new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }));

                // Re-enable form fields
                $saveBtn.prop('disabled', false);
                $saveSpinner.addClass('d-none');
                $profileForm.find('input, textarea, button').prop('disabled', false);
                $('#emailAddress').prop('disabled', true); // keep locked
            },
            error: function (xhr) {
                let errorMessage = 'Could not update profile details.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                showNotification(errorMessage, false);
                
                // Re-enable fields
                $saveBtn.prop('disabled', false);
                $saveSpinner.addClass('d-none');
                $profileForm.find('input, textarea, button').prop('disabled', false);
                $('#emailAddress').prop('disabled', true); // keep locked
            }
        });
    });

    /**
     * Logout Handler
     */
    $('#logoutBtn').on('click', function (e) {
        e.preventDefault();
        
        $pageLoader.addClass('active').find('p').text('Invalidating active session...');

        $.ajax({
            url: 'php/logout.php',
            type: 'POST',
            dataType: 'json',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            complete: function () {
                // Remove token and redirect regardless of server response
                localStorage.removeItem('auth_token');
                window.location.href = 'login.html';
            }
        });
    });
});
