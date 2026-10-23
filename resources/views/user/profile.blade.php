@extends('layouts.user')
@section('title', 'Profile')
@section('page-title', 'Profile & Settings')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-12 col-lg-8">

            <!-- Profile Photo -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent py-3 border-0">
                    <span class="card-title font-weight-bold text-dark">Profile Photo</span>
                </div>
                <div class="card-body pt-0 d-flex align-items-center" style="gap: 16px;">
                    <img id="profile-photo-preview" src="https://ui-avatars.com/api/?name=User&background=eee&color=999" alt="" style="width:64px;height:64px;border-radius:50%;object-fit:cover;">
                    <div>
                        <div id="profile-photo-alert" class="alert alert-danger py-1 px-2 mb-2" style="font-size:12px; display:none;"></div>
                        <input type="file" id="profile-photo-input" accept="image/jpeg,image/png,image/jpg" class="d-none">
                        <button type="button" class="btn btn-outline-dark btn-sm font-weight-bold" id="profile-photo-trigger-btn">
                            Change Photo
                        </button>
                        <small class="d-block text-muted mt-1">JPG or PNG, up to 1MB.</small>
                    </div>
                </div>
            </div>

            <!-- Personal Info -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent py-3 border-0 d-flex justify-content-between align-items-center">
                    <span class="card-title font-weight-bold text-dark">Personal Information</span>
                </div>
                <div class="card-body pt-0">

                    <div id="profile-form-alert" class="alert alert-danger py-2 px-3" style="font-size:13px; display:none;"></div>
                    <div id="profile-form-success" class="alert alert-success py-2 px-3" style="font-size:13px; display:none;"></div>

                    <form id="profile-form">
                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Email</label>
                                <input type="email" id="profile-email" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Country</label>
                                <select id="profile-country" class="form-control form-control-sm">
                                    <option value="">Select a country</option>
                                </select>
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">First Name</label>
                                <input type="text" id="profile-first-name" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Last Name</label>
                                <input type="text" id="profile-last-name" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Gender</label>
                                <select id="profile-gender" class="form-control form-control-sm">
                                    <option value="">Select gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Phone</label>
                                <input type="text" id="profile-phone" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Date of Birth</label>
                                <input type="date" id="profile-dob" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">State</label>
                                <input type="text" id="profile-state" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Address</label>
                                <input type="text" id="profile-address" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Zip Code</label>
                                <input type="text" id="profile-zip" class="form-control form-control-sm">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm font-weight-bold" id="profile-form-submit-btn">
                            Save Changes
                        </button>
                    </form>
                </div>
            </div>

            <!-- Security: 2FA -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent py-3 border-0">
                    <span class="card-title font-weight-bold text-dark">Two-Factor Authentication</span>
                </div>
                <div class="card-body pt-0">
                    <div id="two-factor-alert" class="alert alert-danger py-2 px-3" style="font-size:13px; display:none;"></div>
                    <div id="two-factor-success" class="alert alert-success py-2 px-3" style="font-size:13px; display:none;"></div>

                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-0 font-weight-bold text-dark" style="font-size:14px;">Email confirmation on withdrawals & transfers</p>
                            <p class="mb-0 text-muted small" id="two-factor-status-text">Checking status...</p>
                        </div>
                        <button type="button" class="btn btn-outline-dark btn-sm font-weight-bold" id="two-factor-toggle-btn" disabled>
                            —
                        </button>
                    </div>

                    <!-- OTP confirmation shown only when disabling 2FA -->
                    <div id="two-factor-otp-wrapper" class="mt-3" style="display:none;">
                        <label class="small font-weight-bold text-muted mb-1">Confirmation Code</label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="two-factor-otp-code" class="form-control form-control-sm" placeholder="Enter the code emailed to you">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-primary btn-sm font-weight-bold" id="two-factor-otp-confirm-btn">
                                    Confirm
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security: Password -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent py-3 border-0">
                    <span class="card-title font-weight-bold text-dark">Change Password</span>
                </div>
                <div class="card-body pt-0">

                    <div id="password-form-alert" class="alert alert-danger py-2 px-3" style="font-size:13px; display:none;"></div>
                    <div id="password-form-success" class="alert alert-success py-2 px-3" style="font-size:13px; display:none;"></div>

                    <form id="password-form">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Current Password</label>
                            <input type="password" id="password-old" class="form-control form-control-sm" required>
                        </div>
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted mb-1">New Password</label>
                            <input type="password" id="password-new" class="form-control form-control-sm" required minlength="8">
                        </div>
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Confirm New Password</label>
                            <input type="password" id="password-confirm" class="form-control form-control-sm" required minlength="8">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm font-weight-bold" id="password-form-submit-btn">
                            Update Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="card shadow-sm border-0 mb-4 border-danger">
                <div class="card-header bg-transparent py-3 border-0">
                    <span class="card-title font-weight-bold text-danger">Danger Zone</span>
                </div>
                <div class="card-body pt-0">
                    <p class="text-muted small mb-3">Deactivating your account will disable access. This cannot be undone from here.</p>
                    <div id="deactivate-alert" class="alert alert-danger py-2 px-3" style="font-size:13px; display:none;"></div>
                    <button type="button" class="btn btn-outline-danger btn-sm font-weight-bold" id="deactivate-account-btn">
                        Deactivate Account
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

@include('user.profile_modal')

@endsection