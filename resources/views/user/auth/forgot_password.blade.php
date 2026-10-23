@extends('layouts.setup')
@section('title', 'Reset Password')

@section('content')
<div class="setup-card">

    <!-- Step 1: Enter email -->
    <div id="fp-email-view">
        <h2>Forgot Password</h2>
        <p>Enter your account email and we'll send you a reset code.</p>

        <div id="fp-email-alert" class="notice notice-error" style="display:none;"></div>

        <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" id="fp-email" class="form-control" placeholder="you@example.com" autocomplete="email" autofocus>
        </div>

        <button type="button" class="btn-login" id="fp-email-submit-btn">Send Reset Code</button>

        <p class="entry-alt">
            <a href="{{ route('user.login') }}">&larr; Back to sign in</a>
        </p>
    </div>

    <!-- Step 2: OTP verification -->
    <div id="fp-otp-view" style="display:none;">
        <h2>Enter Reset Code</h2>
        <p>We sent a code to <strong id="fp-otp-email-label"></strong>. Enter it below.</p>

        <div id="fp-otp-alert" class="notice notice-error" style="display:none;"></div>
        <div id="fp-otp-success" class="notice notice-ok" style="display:none;"></div>

        <div class="form-group">
            <label class="form-label">Reset Code</label>
            <input type="text" id="fp-otp-code" class="form-control" placeholder="Enter code" autocomplete="one-time-code">
        </div>

        <button type="button" class="btn-login" id="fp-otp-submit-btn">Verify Code</button>

        <p class="entry-alt">
            <a href="#" id="fp-resend-btn">Resend code</a>
        </p>
    </div>

    <!-- Step 3: Set new password -->
    <div id="fp-reset-view" style="display:none;">
        <h2>Set New Password</h2>
        <p>Choose a strong password for your account.</p>

        <div id="fp-reset-alert" class="notice notice-error" style="display:none;"></div>

        <div class="form-group">
            <label class="form-label">New Password</label>
            <input type="password" id="fp-new-password" class="form-control" placeholder="Min. 8 characters" autocomplete="new-password">
        </div>

        <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <input type="password" id="fp-confirm-password" class="form-control" placeholder="Repeat password" autocomplete="new-password">
        </div>

        <button type="button" class="btn-login" id="fp-reset-submit-btn">Reset Password</button>
    </div>

    <!-- Step 4: Success -->
    <div id="fp-success-view" class="center" style="display:none;">
        <h2>Password Reset</h2>
        <p>Your password has been updated. You can now sign in with your new password.</p>
        <a href="{{ route('user.login') }}" class="btn-login">
            Go to Sign In
        </a>
    </div>

</div>

<script>
let resetEmail = null;
let resetToken = null;

document.getElementById('fp-email-submit-btn').addEventListener('click', submitForgotEmail);
document.getElementById('fp-email').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') submitForgotEmail();
});
document.getElementById('fp-otp-submit-btn').addEventListener('click', submitResetOtp);
document.getElementById('fp-resend-btn').addEventListener('click', (e) => {
    e.preventDefault();
    resendResetOtp();
});
document.getElementById('fp-reset-submit-btn').addEventListener('click', submitNewPassword);

async function submitForgotEmail() {
    const alertBox = document.getElementById('fp-email-alert');
    const submitBtn = document.getElementById('fp-email-submit-btn');
    const email = document.getElementById('fp-email').value.trim();

    alertBox.style.display = 'none';

    if (!email) {
        alertBox.textContent = 'Please enter your account email.';
        alertBox.style.display = 'block';
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending...';

    try {
        const response = await fetch("{{ url('api/v1/forgot-password') }}", {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email })
        });

        const result = await response.json();

        if (!result.success) {
            alertBox.textContent = result.message || 'Unable to send reset code. Check the email and try again.';
            alertBox.style.display = 'block';
            return;
        }

        resetEmail = email;
        document.getElementById('fp-otp-email-label').textContent = email;
        document.getElementById('fp-email-view').style.display = 'none';
        document.getElementById('fp-otp-view').style.display = 'block';
    } catch (error) {
        console.error('Forgot password failure:', error);
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send Reset Code';
    }
}

async function resendResetOtp() {
    if (!resetEmail) return;

    const successBox = document.getElementById('fp-otp-success');
    const alertBox = document.getElementById('fp-otp-alert');
    alertBox.style.display = 'none';
    successBox.style.display = 'none';

    try {
        const response = await fetch("{{ url('api/v1/forgot-password') }}", {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email: resetEmail })
        });

        const result = await response.json();
        successBox.textContent = result.message || 'A new code has been sent.';
        successBox.style.display = 'block';
    } catch (error) {
        alertBox.textContent = 'Unable to resend. Please try again.';
        alertBox.style.display = 'block';
    }
}

async function submitResetOtp() {
    const alertBox = document.getElementById('fp-otp-alert');
    const submitBtn = document.getElementById('fp-otp-submit-btn');
    const code = document.getElementById('fp-otp-code').value.trim();

    alertBox.style.display = 'none';

    if (!code) {
        alertBox.textContent = 'Please enter the code from your email.';
        alertBox.style.display = 'block';
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Verifying...';

    try {
        const response = await fetch("{{ url('api/v1/verify-reset-otp') }}", {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email: resetEmail, otp: code })
        });

        const result = await response.json();

        if (!result.success) {
            alertBox.textContent = result.message || 'Invalid or expired code.';
            alertBox.style.display = 'block';
            return;
        }

        // The server returns a short-lived reset_token that we pass along with the new password.
        resetToken = result.data.reset_token;
        document.getElementById('fp-otp-view').style.display = 'none';
        document.getElementById('fp-reset-view').style.display = 'block';
    } catch (error) {
        console.error('OTP verification failure:', error);
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Verify Code';
    }
}

async function submitNewPassword() {
    const alertBox = document.getElementById('fp-reset-alert');
    const submitBtn = document.getElementById('fp-reset-submit-btn');
    const password = document.getElementById('fp-new-password').value;
    const passwordConfirmation = document.getElementById('fp-confirm-password').value;

    alertBox.style.display = 'none';

    if (!password || !passwordConfirmation) {
        alertBox.textContent = 'Please fill in both password fields.';
        alertBox.style.display = 'block';
        return;
    }

    if (password !== passwordConfirmation) {
        alertBox.textContent = 'Passwords do not match.';
        alertBox.style.display = 'block';
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Resetting...';

    try {
        const response = await fetch("{{ url('api/v1/reset-password') }}", {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                email: resetEmail,
                reset_token: resetToken,
                password,
                password_confirmation: passwordConfirmation
            })
        });

        const result = await response.json();

        if (!result.success) {
            alertBox.textContent = result.message || 'Unable to reset password. Please start over.';
            alertBox.style.display = 'block';
            return;
        }

        document.getElementById('fp-reset-view').style.display = 'none';
        document.getElementById('fp-success-view').style.display = 'block';
    } catch (error) {
        console.error('Password reset failure:', error);
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Reset Password';
    }
}
</script>
@endsection