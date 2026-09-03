<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset your GreenPool password</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f6f4; color:#172033; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f3f6f4; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px; background-color:#ffffff; border-radius:20px; overflow:hidden;">
                    {{-- ===== Header Banner ===== --}}
                    <tr>
                        <td style="background-color:#287f36; padding:28px 36px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td width="42" height="42" align="center" valign="middle" style="width:42px; height:42px;">
                                        <img src="{{ url('/images/favicon.svg') }}" width="42" height="42" alt="GreenPool" style="display:block; width:42px; height:42px; border:0; border-radius:12px;" />
                                    </td>
                                    <td style="padding-left:12px; color:#ffffff; font-size:24px; font-weight:700; letter-spacing:-0.5px;">GreenPool</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ===== Body Content ===== --}}
                    <tr>
                        <td style="padding:40px 36px 28px;">
                            <p style="margin:0 0 12px; color:#287f36; font-size:13px; font-weight:700; letter-spacing:0.8px; text-transform:uppercase;">Password Reset</p>
                            <h1 style="margin:0; color:#172033; font-size:28px; line-height:36px; font-weight:700;">Reset your password</h1>
                            <p style="margin:20px 0 0; color:#506078; font-size:16px; line-height:25px;">Hi {{ $user->name }},</p>
                            <p style="margin:12px 0 0; color:#506078; font-size:16px; line-height:25px;">We received a request to reset the password for your GreenPool account. Click the button below to choose a new password.</p>

                            {{-- ===== CTA Button ===== --}}
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:28px;">
                                <tr>
                                    <td align="center" style="border-radius:10px; background-color:#287f36;">
                                        <a href="{{ $resetUrl }}" style="display:inline-block; padding:14px 22px; color:#ffffff; font-size:15px; font-weight:700; line-height:20px; text-decoration:none;">Reset Password</a>
                                    </td>
                                </tr>
                            </table>

                            {{-- ===== Expiry Notice ===== --}}
                            <p style="margin:24px 0 0; color:#506078; font-size:14px; line-height:22px;">This password reset link will expire in 60 minutes.</p>

                            {{-- ===== Safety Warning ===== --}}
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:30px; border-top:1px solid #e5ebe6;">
                                <tr>
                                    <td style="padding-top:20px; color:#7a8799; font-size:13px; line-height:20px;">If you did not request a password reset, no further action is required. Your password will remain unchanged.</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- ===== Footer ===== --}}
                    <tr>
                        <td style="padding:22px 36px 30px; background-color:#f8fbf8; color:#7a8799; font-size:12px; line-height:18px;">GreenPool &middot; Share the journey. Reduce the impact.</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
