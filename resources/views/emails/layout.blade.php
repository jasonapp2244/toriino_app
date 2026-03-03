<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title>{{ $emailTitle ?? config('app.name') }}</title>
  <!--[if mso]>
  <noscript>
    <xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
  </noscript>
  <![endif]-->
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
      background-color: #f4f4f4;
      color: #333333;
      -webkit-font-smoothing: antialiased;
    }
    a { color: #e73121; text-decoration: none; }
    a:hover { text-decoration: underline; }
    .email-wrapper {
      width: 100%;
      background-color: #f4f4f4;
      padding: 40px 16px;
    }
    .email-container {
      max-width: 600px;
      margin: 0 auto;
      background-color: #ffffff;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 16px rgba(0,0,0,0.10);
    }

    /* ── Header ── */
    .email-header {
      background-color: #161212;
      padding: 32px 40px;
      text-align: center;
      border-bottom: 4px solid #e73121;
    }
    .email-header .brand-name {
      font-size: 30px;
      font-weight: 800;
      letter-spacing: 2px;
      color: #ffffff;
      text-transform: uppercase;
    }
    .email-header .brand-name span {
      color: #e73121;
    }
    .email-header .tagline {
      font-size: 12px;
      color: #aaaaaa;
      letter-spacing: 1px;
      margin-top: 4px;
      text-transform: uppercase;
    }

    /* ── Body ── */
    .email-body {
      padding: 40px 40px 32px;
      background-color: #ffffff;
    }
    .email-body h1 {
      font-size: 22px;
      font-weight: 700;
      color: #161212;
      margin-bottom: 12px;
    }
    .email-body p {
      font-size: 15px;
      line-height: 1.7;
      color: #555555;
      margin-bottom: 16px;
    }
    .email-body p strong {
      color: #161212;
    }

    /* ── OTP Box ── */
    .otp-box {
      display: block;
      background-color: #fff8f7;
      border: 2px solid #e73121;
      border-radius: 8px;
      text-align: center;
      padding: 24px 16px;
      margin: 28px 0;
    }
    .otp-box .otp-label {
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 2px;
      text-transform: uppercase;
      color: #e73121;
      margin-bottom: 10px;
    }
    .otp-box .otp-code {
      font-size: 44px;
      font-weight: 800;
      letter-spacing: 12px;
      color: #161212;
      font-family: 'Courier New', Courier, monospace;
    }
    .otp-box .otp-expiry {
      font-size: 12px;
      color: #888888;
      margin-top: 10px;
    }

    /* ── CTA Button ── */
    .btn-primary {
      display: inline-block;
      background-color: #e73121;
      color: #ffffff !important;
      font-size: 15px;
      font-weight: 600;
      padding: 14px 36px;
      border-radius: 6px;
      text-align: center;
      letter-spacing: 0.5px;
      text-decoration: none !important;
    }
    .btn-center {
      text-align: center;
      margin: 28px 0;
    }

    /* ── Divider ── */
    .email-divider {
      height: 1px;
      background-color: #f0f0f0;
      margin: 28px 0;
    }

    /* ── Warning / Note Box ── */
    .note-box {
      background-color: #fafafa;
      border-left: 4px solid #e73121;
      border-radius: 0 6px 6px 0;
      padding: 14px 18px;
      margin: 20px 0;
    }
    .note-box p {
      font-size: 13px;
      color: #666666;
      margin: 0;
    }

    /* ── Footer ── */
    .email-footer {
      background-color: #161212;
      padding: 28px 40px;
      text-align: center;
      border-top: 4px solid #e73121;
    }
    .email-footer p {
      font-size: 12px;
      color: #888888;
      margin: 0 0 6px;
      line-height: 1.6;
    }
    .email-footer .footer-links a {
      color: #aaaaaa;
      font-size: 12px;
      margin: 0 8px;
    }
    .email-footer .footer-links a:hover {
      color: #e73121;
    }
    .email-footer .copyright {
      font-size: 11px;
      color: #555555;
      margin-top: 12px;
    }

    /* ── Responsive ── */
    @media only screen and (max-width: 600px) {
      .email-body  { padding: 28px 24px 24px; }
      .email-header { padding: 24px 20px; }
      .email-footer { padding: 20px 24px; }
      .otp-box .otp-code { font-size: 32px; letter-spacing: 8px; }
    }
  </style>
</head>
<body>
  <div class="email-wrapper">
    <div class="email-container">

      {{-- ── Header ── --}}
      <div class="email-header">
        <div class="brand-name">Tori<span>ino</span></div>
        <div class="tagline">Learn · Grow · Succeed</div>
      </div>

      {{-- ── Body (slot) ── --}}
      <div class="email-body">
        @yield('content')
      </div>

      {{-- ── Footer ── --}}
      <div class="email-footer">
        <div class="footer-links">
          <a href="#">Help Center</a>
          <a href="#">Privacy Policy</a>
          <a href="#">Terms of Service</a>
        </div>
        <p class="copyright">
          &copy; {{ date('Y') }} Turiino. All rights reserved.<br>
          If you did not request this email, you can safely ignore it.
        </p>
      </div>

    </div>
  </div>
</body>
</html>
