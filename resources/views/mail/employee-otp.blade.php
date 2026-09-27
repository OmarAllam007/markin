<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Your login code · {{ config('app.name') }}</title>
    <style>
        @media only screen and (max-width: 540px) {
            .email-shell { padding: 0 !important; }
            .email-card { border-right: 0 !important; border-left: 0 !important; border-radius: 0 !important; }
            .content-cell { padding-right: 24px !important; padding-left: 24px !important; }
            .code-cell { width: 54px !important; height: 64px !important; font-size: 28px !important; }
            .code-gap { width: 6px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#eef1ee; color:#17231f; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; -webkit-font-smoothing:antialiased;">
<div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent; line-height:1px;">
    {{ $code }} is your {{ config('app.name') }} login code. It expires in 5 minutes.
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; background-color:#eef1ee;">
    <tr>
        <td class="email-shell" align="center" style="padding:48px 16px;">
            <table class="email-card" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:520px; overflow:hidden; background-color:#ffffff; border:1px solid #d9dfdb; border-radius:16px; box-shadow:0 16px 42px rgba(23,35,31,0.06);">
                <tr><td style="height:6px; background-color:#246a56; font-size:0; line-height:0;">&nbsp;</td></tr>
                <tr>
                    <td class="content-cell" style="padding:30px 38px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td valign="middle">
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                        <tr>
                                            <td width="34" height="34" align="center" valign="middle" style="width:34px; height:34px; border-radius:8px; background-color:#246a56;">
                                                <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                                                    <td valign="bottom" style="padding-right:2px;"><span style="display:block; width:3px; height:7px; border-radius:2px; background-color:#ffffff;">&nbsp;</span></td>
                                                    <td valign="bottom" style="padding-right:2px;"><span style="display:block; width:3px; height:15px; border-radius:2px; background-color:#ffffff;">&nbsp;</span></td>
                                                    <td valign="bottom"><span style="display:block; width:3px; height:10px; border-radius:2px; background-color:#ffffff;">&nbsp;</span></td>
                                                </tr></table>
                                            </td>
                                            <td style="padding-left:10px; font-size:18px; line-height:1; font-weight:700; letter-spacing:-0.5px; color:#17231f;">{{ config('app.name') }}</td>
                                        </tr>
                                    </table>
                                </td>
                                <td align="right" valign="middle"><span style="display:inline-block; padding:6px 9px; border:1px solid #dce3df; border-radius:99px; color:#68736f; font-size:10px; line-height:1; font-weight:700; letter-spacing:1.1px; text-transform:uppercase;">Secure sign-in</span></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="content-cell" style="padding:46px 38px 0;">
                        <p style="margin:0 0 12px; color:#246a56; font-size:11px; line-height:1.4; font-weight:700; letter-spacing:1.5px; text-transform:uppercase;">One-time verification</p>
                        <h1 style="margin:0; color:#17231f; font-size:28px; line-height:1.22; font-weight:650; letter-spacing:-0.8px;">Confirm it’s you</h1>
                        <p style="margin:14px 0 0; color:#68736f; font-size:15px; line-height:1.7;">Hi {{ $employee->english_name }}, enter this code in the {{ config('app.name') }} app to finish signing in.</p>
                    </td>
                </tr>
                <tr>
                    <td class="content-cell" style="padding:30px 38px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f6f3; border:1px solid #dce3df; border-radius:12px;">
                            <tr>
                                <td align="center" style="padding:24px 12px 22px;">
                                    <p style="margin:0 0 14px; color:#7a8581; font-size:10px; line-height:1.2; font-weight:700; letter-spacing:1.4px; text-transform:uppercase;">Your login code</p>
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" aria-label="Login code {{ $code }}">
                                        <tr>
                                            @foreach(str_split($code) as $digit)
                                                <td class="code-cell" width="62" height="70" align="center" valign="middle" style="width:62px; height:70px; border:1px solid #cdd7d2; border-radius:9px; background-color:#ffffff; color:#17231f; font-family:'Courier New',Courier,monospace; font-size:32px; line-height:1; font-weight:700;">{{ $digit }}</td>
                                                @unless($loop->last)
                                                    <td class="code-gap" width="8" style="width:8px; font-size:0; line-height:0;">&nbsp;</td>
                                                @endunless
                                            @endforeach
                                        </tr>
                                    </table>
                                    <p style="margin:14px 0 0; color:#68736f; font-size:12px; line-height:1.5;">Expires in <strong style="color:#263832;">5 minutes</strong> · Single use only</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="content-cell" style="padding:24px 38px 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-left:3px solid #9fc8ba;">
                            <tr><td style="padding:2px 0 2px 14px;">
                                <p style="margin:0 0 3px; color:#263832; font-size:12px; line-height:1.5; font-weight:700;">Keep this code private</p>
                                <p style="margin:0; color:#7a8581; font-size:12px; line-height:1.6;">{{ config('app.name') }} will never ask you to share a verification code by phone or message.</p>
                            </td></tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="content-cell" style="padding:30px 38px 34px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #e3e7e4;">
                            <tr><td style="padding-top:20px;"><p style="margin:0; color:#8a9490; font-size:11px; line-height:1.65;">Didn’t try to sign in? You can ignore this email. No changes will be made to your account.</p></td></tr>
                        </table>
                    </td>
                </tr>
            </table>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:520px;">
                <tr><td align="center" style="padding:18px 24px 0; color:#929b98; font-size:10px; line-height:1.6;">This is an automated security message from {{ config('app.name') }}.<br>Please do not reply to this email.</td></tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
