<script>
let currentTwoFactorState = null;

document.addEventListener('DOMContentLoaded', () => {
    fetchProfile();
    fetchCountries();
    fetchSettings();

    document.getElementById('profile-photo-trigger-btn').addEventListener('click', () => {
        document.getElementById('profile-photo-input').click();
    });
    document.getElementById('profile-photo-input').addEventListener('change', uploadProfilePhoto);
    document.getElementById('profile-form').addEventListener('submit', submitProfileForm);
    document.getElementById('password-form').addEventListener('submit', submitPasswordForm);
    document.getElementById('two-factor-toggle-btn').addEventListener('click', toggleTwoFactor);
    document.getElementById('two-factor-otp-confirm-btn').addEventListener('click', confirmTwoFactorDeactivation);
    document.getElementById('deactivate-account-btn').addEventListener('click', deactivateAccount);
});

function authToken() {
    const token = localStorage.getItem('auth_token');
    if (!token) {
        window.location.href = "{{ route('wallet.view.generate') }}";
        return null;
    }
    return token;
}

async function fetchProfile() {
    const token = authToken();
    if (!token) return;

    try {
        const response = await fetch("{{ url('api/v1/profile') }}", {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        const result = await response.json();

        if (response.status === 403 && result.data && result.data.redirect_to) {
            window.location.href = "{{ route('wallet.view.generate') }}";
            return;
        }

        if (!result.success || !result.data) return;
        const user = result.data.user;
        const profile = user.profile || {};

        document.getElementById('profile-email').value = user.email || '';
        document.getElementById('profile-first-name').value = profile.first_name || '';
        document.getElementById('profile-last-name').value = profile.last_name || '';
        document.getElementById('profile-gender').value = profile.gender || '';
        document.getElementById('profile-phone').value = profile.phone || '';
        document.getElementById('profile-dob').value = profile.dob || '';
        document.getElementById('profile-state').value = profile.state || '';
        document.getElementById('profile-address').value = profile.address || '';
        document.getElementById('profile-zip').value = profile.zip || '';

        // Country select may still be loading; set once options exist.
        const countrySelect = document.getElementById('profile-country');
        if (user.country_id) {
            countrySelect.dataset.pending = user.country_id;
            if (countrySelect.querySelector(`option[value="${user.country_id}"]`)) {
                countrySelect.value = user.country_id;
            }
        }

        if (user.photo && user.photo.url) {
            document.getElementById('profile-photo-preview').src = user.photo.url;
        }
    } catch (error) {
        console.error('Profile fetch failure:', error);
    }
}

async function fetchCountries() {
    const token = authToken();
    if (!token) return;

    try {
        const response = await fetch("{{ url('api/v1/countries') }}", {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        const result = await response.json();
        if (!result.success || !result.data) return;

        const countrySelect = document.getElementById('profile-country');
        (result.data.countries || []).forEach(country => {
            countrySelect.innerHTML += `<option value="${country.id}">${country.name}</option>`;
        });

        // Apply the pending selection now that options exist.
        if (countrySelect.dataset.pending) {
            countrySelect.value = countrySelect.dataset.pending;
        }
    } catch (error) {
        console.error('Countries fetch failure:', error);
    }
}

async function fetchSettings() {
    const token = authToken();
    if (!token) return;

    try {
        const response = await fetch("{{ url('api/v1/settings') }}", {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        const result = await response.json();
        if (!result.success || !result.data) return;

        renderTwoFactorState(!!result.data.two_factor);
    } catch (error) {
        console.error('Settings fetch failure:', error);
    }
}

function renderTwoFactorState(enabled) {
    currentTwoFactorState = enabled;

    const statusText = document.getElementById('two-factor-status-text');
    const toggleBtn = document.getElementById('two-factor-toggle-btn');

    statusText.innerText = enabled ? 'Currently enabled.' : 'Currently disabled.';
    toggleBtn.disabled = false;
    toggleBtn.innerText = enabled ? 'Disable' : 'Enable';
    toggleBtn.className = enabled
        ? 'btn btn-outline-danger btn-sm font-weight-bold'
        : 'btn btn-outline-dark btn-sm font-weight-bold';

    document.getElementById('two-factor-otp-wrapper').style.display = 'none';
}

async function uploadProfilePhoto(event) {
    const token = authToken();
    if (!token) return;

    const fileInput = event.target;
    const alertBox = document.getElementById('profile-photo-alert');
    alertBox.style.display = 'none';

    if (!fileInput.files.length) return;

    const formData = new FormData();
    formData.append('profile_photo', fileInput.files[0]);

    try {
        const response = await fetch("{{ url('api/v1/profile/photo') }}", {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            alertBox.textContent = result.message || 'Unable to upload photo right now.';
            alertBox.style.display = 'block';
            return;
        }

        document.getElementById('profile-photo-preview').src = result.data.url;
    } catch (error) {
        console.error('Photo upload failure:', error);
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        fileInput.value = '';
    }
}

async function submitProfileForm(event) {
    event.preventDefault();

    const token = authToken();
    if (!token) return;

    const alertBox = document.getElementById('profile-form-alert');
    const successBox = document.getElementById('profile-form-success');
    const submitBtn = document.getElementById('profile-form-submit-btn');

    alertBox.style.display = 'none';
    successBox.style.display = 'none';

    const payload = {
        email: document.getElementById('profile-email').value,
        country_id: document.getElementById('profile-country').value || null,
        first_name: document.getElementById('profile-first-name').value || null,
        last_name: document.getElementById('profile-last-name').value || null,
        gender: document.getElementById('profile-gender').value || null,
        phone: document.getElementById('profile-phone').value || null,
        state: document.getElementById('profile-state').value || null,
        address: document.getElementById('profile-address').value || null,
        zip: document.getElementById('profile-zip').value || null,
        dob: document.getElementById('profile-dob').value || null
    };

    submitBtn.disabled = true;
    submitBtn.innerText = 'Saving...';

    try {
        const response = await fetch("{{ url('api/v1/profile') }}", {
            method: 'PUT',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (!result.success) {
            alertBox.textContent = result.message || 'Unable to save changes right now.';
            alertBox.style.display = 'block';
            return;
        }

        successBox.textContent = 'Profile updated.';
        successBox.style.display = 'block';
    } catch (error) {
        console.error('Profile update failure:', error);
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerText = 'Save Changes';
    }
}

async function submitPasswordForm(event) {
    event.preventDefault();

    const token = authToken();
    if (!token) return;

    const alertBox = document.getElementById('password-form-alert');
    const successBox = document.getElementById('password-form-success');
    const submitBtn = document.getElementById('password-form-submit-btn');

    alertBox.style.display = 'none';
    successBox.style.display = 'none';

    const oldPassword = document.getElementById('password-old').value;
    const newPassword = document.getElementById('password-new').value;
    const confirmPassword = document.getElementById('password-confirm').value;

    if (newPassword !== confirmPassword) {
        alertBox.textContent = 'New password and confirmation do not match.';
        alertBox.style.display = 'block';
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerText = 'Updating...';

    try {
        const response = await fetch("{{ url('api/v1/password') }}", {
            method: 'PUT',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({
                old_password: oldPassword,
                password: newPassword,
                password_confirmation: confirmPassword
            })
        });

        const result = await response.json();

        if (!result.success) {
            alertBox.textContent = result.message || 'Unable to update password right now.';
            alertBox.style.display = 'block';
            return;
        }

        successBox.textContent = 'Password updated.';
        successBox.style.display = 'block';
        document.getElementById('password-form').reset();
    } catch (error) {
        console.error('Password update failure:', error);
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerText = 'Update Password';
    }
}

async function toggleTwoFactor() {
    const token = authToken();
    if (!token || currentTwoFactorState === null) return;

    const alertBox = document.getElementById('two-factor-alert');
    const successBox = document.getElementById('two-factor-success');
    const toggleBtn = document.getElementById('two-factor-toggle-btn');

    alertBox.style.display = 'none';
    successBox.style.display = 'none';
    toggleBtn.disabled = true;

    const endpoint = currentTwoFactorState ? 'two-factor/disable' : 'two-factor/enable';

    try {
        const response = await fetch(`{{ url('api/v1') }}/${endpoint}`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        const result = await response.json();

        if (response.status === 403 && result.data && result.data.redirect_to) {
            alertBox.textContent = 'Please finish setting up your profile before changing security settings.';
            alertBox.style.display = 'block';
            return;
        }

        if (!result.success) {
            alertBox.textContent = result.message || 'Unable to update two-factor settings right now.';
            alertBox.style.display = 'block';
            return;
        }

        if (currentTwoFactorState) {
            // Disabling requires the emailed code before it actually turns off.
            successBox.textContent = result.message || 'A confirmation code has been sent to your email.';
            successBox.style.display = 'block';
            document.getElementById('two-factor-otp-wrapper').style.display = 'block';
        } else {
            renderTwoFactorState(true);
            successBox.textContent = 'Two-factor authentication enabled.';
            successBox.style.display = 'block';
        }
    } catch (error) {
        console.error('Two-factor toggle failure:', error);
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        toggleBtn.disabled = false;
    }
}

async function confirmTwoFactorDeactivation() {
    const token = authToken();
    if (!token) return;

    const code = document.getElementById('two-factor-otp-code').value;
    const alertBox = document.getElementById('two-factor-alert');
    const successBox = document.getElementById('two-factor-success');
    const confirmBtn = document.getElementById('two-factor-otp-confirm-btn');

    alertBox.style.display = 'none';
    successBox.style.display = 'none';

    if (!code) {
        alertBox.textContent = 'Please enter the confirmation code sent to your email.';
        alertBox.style.display = 'block';
        return;
    }

    confirmBtn.disabled = true;
    confirmBtn.innerText = 'Confirming...';

    try {
        const response = await fetch("{{ url('api/v1/two-factor/confirm') }}", {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({ code })
        });

        const result = await response.json();

        if (!result.success) {
            alertBox.textContent = result.message || 'Invalid or expired confirmation code.';
            alertBox.style.display = 'block';
            return;
        }

        renderTwoFactorState(false);
        successBox.textContent = 'Two-factor authentication disabled.';
        successBox.style.display = 'block';
        document.getElementById('two-factor-otp-code').value = '';
    } catch (error) {
        console.error('Two-factor confirmation failure:', error);
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        confirmBtn.disabled = false;
        confirmBtn.innerText = 'Confirm';
    }
}

async function deactivateAccount() {
    const token = authToken();
    if (!token) return;

    if (!confirm('Are you sure you want to deactivate your account? This cannot be undone from here.')) {
        return;
    }

    const alertBox = document.getElementById('deactivate-alert');
    const deactivateBtn = document.getElementById('deactivate-account-btn');
    alertBox.style.display = 'none';
    deactivateBtn.disabled = true;
    deactivateBtn.innerText = 'Deactivating...';

    try {
        const response = await fetch("{{ url('api/v1/profile') }}", {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        const result = await response.json();

        if (!result.success) {
            alertBox.textContent = result.message || 'Unable to deactivate account right now.';
            alertBox.style.display = 'block';
            deactivateBtn.disabled = false;
            deactivateBtn.innerText = 'Deactivate Account';
            return;
        }

        localStorage.removeItem('auth_token');
        window.location.href = "{{ route('wallet.view.generate') }}";
    } catch (error) {
        console.error('Account deactivation failure:', error);
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
        deactivateBtn.disabled = false;
        deactivateBtn.innerText = 'Deactivate Account';
    }
}
</script>