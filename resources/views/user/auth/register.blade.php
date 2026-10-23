@extends('layouts.setup')
@section('title', 'Complete Registration')

@section('content')
<div class="setup-card">

    <!-- Profile setup form -->
    <div id="register-view">
        <h2>Complete Your Profile</h2>
        <p>You're almost there. Set up your account details to start using Northfolio.</p>

        <div id="register-alert" class="notice notice-error" style="display:none;"></div>

        <div class="form-group">
            <label class="form-label">Username</label>
            <input type="text" id="reg-username" class="form-control" placeholder="yourhandle" maxlength="30" autocomplete="username">
        </div>

        <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" id="reg-email" class="form-control" placeholder="you@example.com" autocomplete="email">
        </div>

        <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" id="reg-password" class="form-control" placeholder="Min. 8 characters" autocomplete="new-password">
        </div>

        <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <input type="password" id="reg-password-confirm" class="form-control" placeholder="Repeat password" autocomplete="new-password">
        </div>

        <div class="form-group">
            <label class="form-label">Country</label>
            <select id="reg-country" class="form-control">
                <option value="">Select a country</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Phone <span class="opt">(optional)</span></label>
            <input type="text" id="reg-phone" class="form-control" placeholder="+1 555 0000" autocomplete="tel">
        </div>

        <button type="button" class="btn-login" id="register-submit-btn">Create Account</button>

        <p class="entry-alt">
            Not ready yet?
            <a href="{{ route('dashboard') }}">Skip for now →</a>
        </p>
    </div>

    <!-- OTP verification -->
    <div id="otp-view" style="display:none;">
        <h2>Verify Your Email</h2>
        <p>Enter the code we sent to <strong id="otp-email-label"></strong>.</p>

        <div id="otp-alert" class="notice notice-error" style="display:none;"></div>
        <div id="otp-success" class="notice notice-ok" style="display:none;"></div>

        <div class="form-group">
            <label class="form-label">OTP Code</label>
            <input type="text" id="otp-code" class="form-control" placeholder="Enter code" autocomplete="one-time-code">
        </div>

        <button type="button" class="btn-login" id="otp-submit-btn">Verify &amp; Continue</button>

        <p class="entry-alt">
            <a href="#" id="resend-otp-btn">Resend code</a>
        </p>
    </div>

</div>

<script>
let pendingEmail = null;

document.addEventListener('DOMContentLoaded', () => {
    // Must have a wallet token in sessionStorage to be here.
    const token = sessionStorage.getItem('wallet_setup_token');
    if (!token) {
        window.location.href = "{{ route('wallet.view.generate') }}";
        return;
    }

    fetchCountries(token);
    document.getElementById('register-submit-btn').addEventListener('click', () => submitRegister(token));
    document.getElementById('otp-submit-btn').addEventListener('click', () => submitOtp(token));
    document.getElementById('resend-otp-btn').addEventListener('click', (e) => {
        e.preventDefault();
        resendOtp();
    });
});

async function fetchCountries(token) {
    try {
        const response = await fetch("{{ url('api/v1/countries') }}", {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        const result = await response.json();
        const select = document.getElementById('reg-country');
        (result.data?.countries || []).forEach(c => {
            select.innerHTML += `<option value="${c.id}">${c.name}</option>`;
        });
    } catch (e) {
        console.error('Countries fetch failure:', e);
    }
}

async function submitRegister(walletToken) {
    const alertBox = document.getElementById('register-alert');
    const submitBtn = document.getElementById('register-submit-btn');

    alertBox.style.display = 'none';

    const username = document.getElementById('reg-username').value.trim();
    const email = document.getElementById('reg-email').value.trim();
    const password = document.getElementById('reg-password').value;
    const passwordConfirmation = document.getElementById('reg-password-confirm').value;
    const countryId = document.getElementById('reg-country').value;
    const phone = document.getElementById('reg-phone').value.trim();

    if (!username || !email || !password || !countryId) {
        alertBox.textContent = 'Username, email, password and country are required.';
        alertBox.style.display = 'block';
        return;
    }

    if (password !== passwordConfirmation) {
        alertBox.textContent = 'Passwords do not match.';
        alertBox.style.display = 'block';
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating account...';

    const payload = {
        username,
        email,
        password,
        password_confirmation: passwordConfirmation,
        country_id: countryId,
        phone: phone || null,
    };

    try {
        const response = await fetch("{{ url('api/v1/register-profile') }}", {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + walletToken
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (!result.success) {
            alertBox.textContent = result.message || 'Unable to complete registration right now.';
            alertBox.style.display = 'block';
            return;
        }

        // Swap to the new user token; the wallet token is no longer valid for auth after this.
        const newToken = result.data.access_token;
        sessionStorage.removeItem('wallet_setup_token');
        localStorage.setItem('auth_token', newToken);

        pendingEmail = result.data.user.email;
        document.getElementById('otp-email-label').textContent = pendingEmail;
        document.getElementById('register-view').style.display = 'none';
        document.getElementById('otp-view').style.display = 'block';
    } catch (error) {
        console.error('Registration failure:', error);
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Create Account';
    }
}

async function submitOtp(token) {
    const alertBox = document.getElementById('otp-alert');
    const submitBtn = document.getElementById('otp-submit-btn');
    const code = document.getElementById('otp-code').value.trim();

    alertBox.style.display = 'none';

    if (!code) {
        alertBox.textContent = 'Please enter the code from your email.';
        alertBox.style.display = 'block';
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Verifying...';

    try {
        const response = await fetch("{{ url('api/v1/verify-otp') }}", {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email: pendingEmail, otp: code })
        });

        const result = await response.json();

        if (!result.success) {
            alertBox.textContent = result.message || 'Invalid or expired code.';
            alertBox.style.display = 'block';
            return;
        }

        // Replace the pre-verification token with the freshly-verified one.
        localStorage.setItem('auth_token', result.data.access_token);
        window.location.href = "{{ route('dashboard') }}";
    } catch (error) {
        console.error('OTP verification failure:', error);
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Verify & Continue';
    }
}

async function resendOtp() {
    if (!pendingEmail) return;

    const successBox = document.getElementById('otp-success');
    const alertBox = document.getElementById('otp-alert');
    alertBox.style.display = 'none';
    successBox.style.display = 'none';

    try {
        const response = await fetch("{{ url('api/v1/resend-otp') }}", {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email: pendingEmail })
        });

        const result = await response.json();
        successBox.textContent = result.message || 'A new code has been sent.';
        successBox.style.display = 'block';
    } catch (error) {
        alertBox.textContent = 'Unable to resend. Please try again.';
        alertBox.style.display = 'block';
    }
}
</script>
@endsection