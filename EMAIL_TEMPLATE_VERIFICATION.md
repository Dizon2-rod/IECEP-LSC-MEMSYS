# Email Template Verification

## Current Status

The email template in `sendSchoolAccountCredentials()` method **DOES CONTAIN** both the email and password variables.

### Verified Locations:

**Line 191** - Email (Username):
```php
<p style='margin:4px 0 0 0;color:#0B1D4A;font-weight:600;font-size:16px'>{$to}</p>
```

**Line 198** - Temporary Password:
```php
<p style='margin:4px 0 0 0;color:#0B1D4A;font-weight:600;font-size:16px;font-family:monospace;background:#f8fafc;padding:8px 12px;border-radius:4px;display:inline-block'>{$password}</p>
```

### Method Signature (Line 116):
```php
public function sendSchoolAccountCredentials(string $to, string $institutionName, string $password): bool
```

### Called From (affiliation_action.php Line 202-206):
```php
$emailSent = $emailService->sendSchoolAccountCredentials(
    $appData['email'],           // $to parameter
    $appData['institution_name'], // $institutionName parameter
    $tempPassword                 // $password parameter (RAW, not hashed)
);
```

## Why Email and Password Should Appear

1. ✅ **Variables are defined** in method parameters
2. ✅ **Variables are used** in HTML template (lines 191, 198)
3. ✅ **Variables are passed** from approval script with correct values
4. ✅ **Subject line** is correct: "IECEP-LSC Affiliation Approved – Your Portal Account Details"

## Possible Issues

If the email and password are NOT showing in the received email, check:

1. **Email client rendering**: Some email clients strip certain HTML/CSS
2. **Variable interpolation**: PHP string interpolation requires double quotes `"` not single quotes `'`
3. **Email actually sent**: Check if `$result = $mail->send()` returns true
4. **Check spam folder**: Email might be filtered

## Testing Steps

1. Approve a test affiliation application
2. Check the error logs for: `School account credentials email send result to [email]: SUCCESS`
3. Check the recipient's inbox (and spam folder)
4. Verify the email contains:
   - Subject: "IECEP-LSC Affiliation Approved – Your Portal Account Details"
   - Email address under "Email (Username):"
   - Password under "Temporary Password:"

## Template Structure

The email body is a multi-line string starting at line 146:
```php
$mail->Body = "
    <div style='font-family:Inter,sans-serif;...'>
        ...
        <p>{$to}</p>           <!-- Line 191 -->
        ...
        <p>{$password}</p>     <!-- Line 198 -->
        ...
    </div>";
```

All variables should interpolate correctly because the string uses double quotes.
