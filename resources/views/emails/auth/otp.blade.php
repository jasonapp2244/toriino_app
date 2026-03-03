@extends('emails.layout')

@section('content')

  <h1>
    @if($isResend)
      Here is your new OTP code
    @else
      Verify your email address
    @endif
  </h1>

  <p>Hello, <strong>{{ $userName }}</strong></p>

  @if($isResend)
    <p>
      You requested a new verification code for your <strong>Turiino</strong> account.
      Use the code below to complete your verification.
    </p>
  @else
    <p>
      Welcome to <strong>Turiino</strong> — your learning journey starts here!
      To activate your account, please verify your email address using the
      one-time code below.
    </p>
  @endif

  {{-- ── OTP Code Box ── --}}
  <div class="otp-box">
    <div class="otp-label">Your Verification Code</div>
    <div class="otp-code">{{ $otpCode }}</div>
    <div class="otp-expiry">⏱ This code expires in <strong>10 minutes</strong></div>
  </div>

  <div class="note-box">
    <p>
      🔒 <strong>Never share this code</strong> with anyone — including Turiino staff.
      If you did not create an account, please ignore this email or
      <a href="mailto:support@turiino.com">contact our support team</a>.
    </p>
  </div>

  <div class="email-divider"></div>

  <p style="font-size:13px; color:#999999;">
    Sent to: <strong style="color:#333;">{{ $userEmail }}</strong><br>
    If you have trouble, copy and paste your code manually into the app.
  </p>

@endsection
