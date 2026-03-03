@extends('emails.layout')

@section('content')

  <h1>Reset your password</h1>

  <p>Hello, <strong>{{ $userName }}</strong></p>

  <p>
    We received a request to reset the password for your <strong>Turiino</strong> account
    associated with <strong>{{ $userEmail }}</strong>.
  </p>

  <p>
    Use the verification code below to reset your password. This code is valid for
    <strong>10 minutes</strong> and can only be used once.
  </p>

  {{-- ── OTP Code Box ── --}}
  <div class="otp-box">
    <div class="otp-label">Password Reset Code</div>
    <div class="otp-code">{{ $otpCode }}</div>
    <div class="otp-expiry">⏱ Expires in <strong>10 minutes</strong></div>
  </div>

  <div class="note-box">
    <p>
      🔒 <strong>Did not request a password reset?</strong><br>
      Your password has <em>not</em> been changed. You can safely ignore this email.
      If you are concerned about your account security, please
      <a href="mailto:support@turiino.com">contact our support team</a> immediately.
    </p>
  </div>

  <div class="email-divider"></div>

  <p>After verifying this code in the app, you will be prompted to choose a new password.</p>

  <p style="font-size:13px; color:#999999; margin-top:16px;">
    Sent to: <strong style="color:#333;">{{ $userEmail }}</strong><br>
    Requested at: {{ $requestedAt }}
  </p>

@endsection
