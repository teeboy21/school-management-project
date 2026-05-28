<?php
/**
 * Email helper using Resend.com API.
 * Include after config.php. School email settings are stored in the school_info table.
 */

function email_notifications_enabled($conn) {
    return get_school_info($conn, 'email_notifications_enabled', 'off') === 'on';
}

function send_email($conn, $to, $subject, $html) {
    if (!email_notifications_enabled($conn)) return false;

   // $api_key = get_school_info($conn, 'resend_api_key', '');
    $api_key ='re_1234567890abcdef'; // Placeholder API key for testing. Replace with actual key from school_info.
   // $from_email = get_school_info($conn, 'resend_from_email', 'noreply@yourdomain.com');
    $from_email = 'onboarding@resend.dev';

    if (empty($api_key)) return false;

    $payload = json_encode([
        'from' => $from_email,
        'to' => [$to],
        'subject' => $subject,
        'html' => $html
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        log_audit($conn, $_SESSION['user_id'] ?? 0, 'email_sent', 'notification', null, null, ['to' => $to, 'subject' => $subject]);
        return true;
    }

    log_error($conn, new Exception("Email failed to $to: $error | HTTP $http_code | $response"), $_SESSION['user_id'] ?? 0);
    return false;
}

/* ========== LAYOUT SHELL ========== */

function email_shell($conn, $banner_color, $icon, $title, $body_html) {
    $school = htmlspecialchars(get_school_info($conn, 'school_name'));
    $motto = htmlspecialchars(get_school_info($conn, 'school_motto', ''));
    return '
    <div style="background:#f4f6f9;padding:30px 10px;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif">
        <div style="max-width:560px;margin:auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)">
            <div style="background:' . $banner_color . ';padding:32px 36px 28px;text-align:center">
                <div style="font-size:40px;margin-bottom:8px">' . $icon . '</div>
                <h1 style="color:#fff;margin:0;font-size:22px;font-weight:700;letter-spacing:-.3px">' . $title . '</h1>
            </div>
            <div style="padding:32px 36px;color:#334155;font-size:15px;line-height:1.7">
                ' . $body_html . '
            </div>
            <div style="background:#f8fafc;padding:20px 36px;border-top:1px solid #e2e8f0;text-align:center;font-size:13px;color:#94a3b8">
                <div style="font-weight:600;color:#475569;margin-bottom:2px">' . $school . '</div>' .
                ($motto ? '<div style="font-style:italic;margin-bottom:6px;color:#64748b">' . $motto . '</div>' : '') . '
                <div style="margin-top:4px">&copy; ' . date('Y') . ' ' . $school . '. All rights reserved.</div>
            </div>
        </div>
    </div>';
}

/* ========== TEMPLATES ========== */

function email_student_registration_complete($conn, $email, $fullname) {
    $subject = 'Registration Complete – Pending Approval';
    $body = email_shell($conn, '#2563eb', '📋', 'Registration Received', '
        <p style="margin:0 0 16px">Dear <strong>' . htmlspecialchars($fullname) . '</strong>,</p>
        <p style="margin:0 0 12px">Thank you for completing your student registration. We&rsquo;ve received your details and they are now awaiting review.</p>
        <div style="background:#eff6ff;border-left:4px solid #2563eb;padding:14px 18px;border-radius:8px;margin:18px 0;font-size:14px;color:#1e40af">
            <strong>⏳ What&rsquo;s next?</strong><br>
            The school administration will review your profile. You&rsquo;ll receive another email once your account has been approved.
        </div>
        <p style="margin:16px 0 0;color:#64748b;font-size:14px">We&rsquo;re excited to have you on board!</p>
    ');
    return send_email($conn, $email, $subject, $body);
}

function email_student_approved($conn, $email, $fullname) {
    $school = htmlspecialchars(get_school_info($conn, 'school_name'));
    $subject = 'Welcome to ' . $school . ' – Account Approved';
    $body = email_shell($conn, '#16a34a', '✅', 'Account Approved', '
        <p style="margin:0 0 16px">Dear <strong>' . htmlspecialchars($fullname) . '</strong>,</p>
        <p style="margin:0 0 12px">Great news! Your student account has been approved. You can now log in and access all student features in the portal.</p>
        <div style="text-align:center;margin:24px 0">
            <a href="https://' . htmlspecialchars(get_school_info($conn, 'school_website', 'yourschool.edu')) . '/login.html" style="display:inline-block;background:#16a34a;color:#fff;text-decoration:none;padding:12px 32px;border-radius:8px;font-weight:600;font-size:15px">Log In to Your Account</a>
        </div>
        <p style="margin:12px 0 0;color:#64748b;font-size:14px">If you have any questions, feel free to reach out to the school office.</p>
    ');
    return send_email($conn, $email, $subject, $body);
}

function email_employee_approved($conn, $email, $fullname) {
    $subject = 'Employee Account Approved';
    $body = email_shell($conn, '#16a34a', '✅', 'Account Approved', '
        <p style="margin:0 0 16px">Dear <strong>' . htmlspecialchars($fullname) . '</strong>,</p>
        <p style="margin:0 0 12px">Your employee account has been approved. You can now log in and access your staff dashboard.</p>
        <div style="text-align:center;margin:24px 0">
            <a href="https://' . htmlspecialchars(get_school_info($conn, 'school_website', 'yourschool.edu')) . '/login.html" style="display:inline-block;background:#16a34a;color:#fff;text-decoration:none;padding:12px 32px;border-radius:8px;font-weight:600;font-size:15px">Log In to Your Account</a>
        </div>
        <p style="margin:12px 0 0;color:#64748b;font-size:14px">Welcome to the team!</p>
    ');
    return send_email($conn, $email, $subject, $body);
}

function email_account_blocked($conn, $email, $fullname, $reason) {
    $subject = 'Account Blocked';
    $body = email_shell($conn, '#dc2626', '🔒', 'Account Blocked', '
        <p style="margin:0 0 16px">Dear <strong>' . htmlspecialchars($fullname) . '</strong>,</p>
        <p style="margin:0 0 12px">Your account has been blocked.</p>
        <div style="background:#fef2f2;border-left:4px solid #dc2626;padding:14px 18px;border-radius:8px;margin:18px 0;font-size:14px;color:#991b1b">
            <strong>Reason:</strong> ' . htmlspecialchars($reason) . '
        </div>
        <p style="margin:16px 0 0;color:#64748b;font-size:14px">If you believe this was a mistake, please contact the school administration or IT department.</p>
    ');
    return send_email($conn, $email, $subject, $body);
}

function email_account_unblocked($conn, $email, $fullname) {
    $subject = 'Account Unblocked';
    $body = email_shell($conn, '#f59e0b', '🔓', 'Account Unblocked', '
        <p style="margin:0 0 16px">Dear <strong>' . htmlspecialchars($fullname) . '</strong>,</p>
        <p style="margin:0 0 12px">Your account has been unblocked by an administrator. You can now log in again.</p>
        <div style="text-align:center;margin:24px 0">
            <a href="https://' . htmlspecialchars(get_school_info($conn, 'school_website', 'yourschool.edu')) . '/login.html" style="display:inline-block;background:#f59e0b;color:#fff;text-decoration:none;padding:12px 32px;border-radius:8px;font-weight:600;font-size:15px">Log In Now</a>
        </div>
        <p style="margin:12px 0 0;color:#64748b;font-size:14px">If you believe this was a mistake or have any questions, please contact the IT department.</p>
    ');
    return send_email($conn, $email, $subject, $body);
}

function email_salary_paid($conn, $email, $fullname, $amount, $month) {
    $subject = 'Salary Paid – ' . $month;
    $body = email_shell($conn, '#059669', '💰', 'Salary Payment Confirmed', '
        <p style="margin:0 0 16px">Dear <strong>' . htmlspecialchars($fullname) . '</strong>,</p>
        <p style="margin:0 0 12px">Your salary for <strong>' . htmlspecialchars($month) . '</strong> has been processed and paid.</p>
        <div style="background:#f0fdf4;border-radius:12px;padding:20px;text-align:center;margin:18px 0">
            <div style="font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:1px">Net Amount</div>
            <div style="font-size:36px;font-weight:700;color:#059669;margin:4px 0">$' . number_format($amount, 2) . '</div>
        </div>
        <p style="margin:16px 0 0;color:#64748b;font-size:14px">Please check your bank account or payroll portal for the transaction details.</p>
    ');
    return send_email($conn, $email, $subject, $body);
}

function email_fees_paid($conn, $email, $fullname, $amount, $academic_year) {
    $subject = 'Fee Payment Confirmed – ' . $academic_year;
    $body = email_shell($conn, '#2563eb', '📄', 'Payment Received', '
        <p style="margin:0 0 16px">Dear <strong>' . htmlspecialchars($fullname) . '</strong>,</p>
        <p style="margin:0 0 12px">Your school fee payment has been received and recorded.</p>
        <div style="background:#f8fafc;border-radius:12px;padding:18px 20px;margin:18px 0;border:1px solid #e2e8f0">
            <table style="width:100%;font-size:14px;border-collapse:collapse">
                <tr><td style="padding:4px 0;color:#64748b">Academic Year</td><td style="padding:4px 0;text-align:right;font-weight:600">' . htmlspecialchars($academic_year) . '</td></tr>
                <tr><td style="padding:4px 0;color:#64748b">Amount Paid</td><td style="padding:4px 0;text-align:right;font-weight:700;font-size:18px;color:#2563eb">$' . number_format($amount, 2) . '</td></tr>
            </table>
        </div>
        <p style="margin:12px 0 0;color:#64748b;font-size:14px">Thank you for your payment. You can view your fee statement in the student portal.</p>
    ');
    return send_email($conn, $email, $subject, $body);
}

function email_subjects_approved($conn, $email, $fullname, $fee_total = null, $fee_due_date = null) {
    $subject = 'Subject Selections Approved';
    $fee_html = '';
    if ($fee_total !== null) {
        $fee_html = '
        <div style="background:#f5f3ff;border:1px solid #e0d4fc;border-radius:12px;padding:18px 20px;margin:18px 0">
            <div style="font-size:13px;color:#6d28d9;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Fee Summary</div>
            <table style="width:100%;font-size:14px;border-collapse:collapse">
                <tr><td style="padding:4px 0;color:#475569">Total Amount Due</td><td style="padding:4px 0;text-align:right;font-weight:700;font-size:18px;color:#6d28d9">$' . number_format($fee_total, 2) . '</td></tr>' .
                ($fee_due_date ? '<tr><td style="padding:4px 0;color:#475569">Due Date</td><td style="padding:4px 0;text-align:right;font-weight:600">' . htmlspecialchars($fee_due_date) . '</td></tr>' : '') . '
            </table>
        </div>';
    }
    $body = email_shell($conn, '#8b5cf6', '📚', 'Subjects Approved', '
        <p style="margin:0 0 16px">Dear <strong>' . htmlspecialchars($fullname) . '</strong>,</p>
        <p style="margin:0 0 12px">Your subject selections have been reviewed and approved. You can view your final subject list in the student portal.</p>
        ' . $fee_html . '
        <div style="background:#f5f3ff;border-left:4px solid #8b5cf6;padding:14px 18px;border-radius:8px;margin:18px 0;font-size:14px;color:#6d28d9">
            <strong>📌 Next step:</strong> Please complete your fee payment before the due date to confirm your enrollment.
        </div>
        <p style="margin:16px 0 0;color:#64748b;font-size:14px">Wishing you a great academic year ahead!</p>
    ');
    return send_email($conn, $email, $subject, $body);
}
