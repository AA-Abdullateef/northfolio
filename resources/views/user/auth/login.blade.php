@extends('layouts.setup')
@section('title', 'Sign In')

@section('content')
<div class="setup-card">

    <!-- Login form -->
    <div id="login-view">
        <h2>Welcome back</h2>
        <p>Sign in with your email or username and password.</p>

        <div id="login-alert" class="notice notice-error" style="display:none;"></div>

        <div class="form-group">
            <label class="form-label">Email or Username</label>
            <input type="text" id="login-user" class="form-control" placeholder="you@example.com" autofocus autocomplete="username">
        </div>

        <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" id="login-password" class="form-control" placeholder="••••••••" autocomplete="current-password">
        </div>

        <button type="button" class="btn-login" id="login-submit-btn">Sign In</button>

        <p class="entry-alt">
            <a href="{{ route('user.forgot-password') }}">Forgot password?</a>
        </p>
        <p class="entry-alt">
            No account? <a href="{{ route('wallet.view.generate') }}">Create a wallet</a>
        </p>
    </div>

    <!-- OTP verification (shown when requires_email_verification is true) -->
    <div id="otp-view" style="display:none;">
        <h2>Verify Your Email</h2>
        <p>Enter the code we sent to <strong id="otp-email-label"></strong>.</p>

        <div id="otp-alert" class="notice notice-error" style="display:none;"></div>
        <div id="otp-success" class="notice notice-ok" style="display:none;"></div>

        <div class="form-group">
            <label class="form-label">OTP Code</label>
            <input type="text" id="otp-code" class="form-control" placeholder="Enter code" autocomplete="one-time-code">
        </div>

        <button type="button" class="btn-login" id="otp-submit-btn">Verify</button>

        <p class="entry-alt">
            <a href="#" id="resend-otp-btn">Resend code</a>
        </p>
        <p class="entry-alt">
            <a href="#" id="back-to-login-btn">&larr; Back</a>
        </p>
    </div>

</div>

<script>
let pendingEmail = null;

document.getElementById('login-submit-btn').addEventListener('click', submitLogin);
document.getElementById('login-password').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') submitLogin();
});
document.getElementById('otp-submit-btn').addEventListener('click', submitOtp);
document.getElementById('resend-otp-btn').addEventListener('click', resendOtp);
document.getElementById('back-to-login-btn').addEventListener('click', (e) => {
    e.preventDefault();
    document.getElementById('otp-view').style.display = 'none';
    document.getElementById('login-view').style.display = 'block';
});

async function submitLogin() {
    const alertBox = document.getElementById('login-alert');
    const submitBtn = document.getElementById('login-submit-btn');
    const user = document.getElementById('login-user').value.trim();
    const password = document.getElementById('login-password').value;

    alertBox.style.display = 'none';

    if (!user || !password) {
        alertBox.textContent = 'Please enter your email/username and password.';
        alertBox.style.display = 'block';
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Signing in...';

    try {
        const response = await fetch("{{ url('api/v1/login') }}", {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ user, password })
        });

        const result = await response.json();

        if (!result.success) {
            alertBox.textContent = result.message || 'Invalid credentials.';
            alertBox.style.display = 'block';
            return;
        }

        localStorage.setItem('auth_token', result.data.access_token);

        if (result.data.requires_email_verification) {
            pendingEmail = result.data.user.email;
            document.getElementById('otp-email-label').textContent = pendingEmail;
            document.getElementById('login-view').style.display = 'none';
            document.getElementById('otp-view').style.display = 'block';
            return;
        }

        window.location.href = "{{ route('dashboard') }}";
    } catch (error) {
        console.error('Login failure:', error);
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Sign In';
    }
}

async function submitOtp() {
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

        // Fresh verified token — replace the pre-verification one.
        localStorage.setItem('auth_token', result.data.access_token);
        window.location.href = "{{ route('dashboard') }}";
    } catch (error) {
        console.error('OTP verification failure:', error);
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Verify';
    }
}

async function resendOtp(e) {
    e.preventDefault();
    if (!pendingEmail) return;

    const successBox = document.getElementById('otp-success');
    const alertBox = document.getElementById('otp-alert');
    alertBox.style.display = 'none';

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