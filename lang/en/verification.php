<?php

declare(strict_types=1);

return [
    'already_verified' => 'Your email address is already verified.',
    'code_sent' => 'Verification code sent to your email! Check your inbox or Mailpit.',
    'already_verified_subsequent' => 'Email address is already verified.',
    'code_expired' => 'Verification code has expired or was not requested. Please request a new code.',
    'invalid_code' => 'Invalid verification code. Please check your email inbox again.',
    'verified_success' => 'Email address successfully verified!',

    // Notification Mail
    'mail_subject' => 'TikPulse - Email Verification Code: :code',
    'mail_greeting' => 'Hello, :name!',
    'mail_intro' => 'Use the following verification code to verify your TikPulse email address:',
    'mail_expires' => 'This code will expire in 15 minutes.',
    'mail_action_intro' => 'Alternatively, you can verify directly by clicking the button below:',
    'mail_action' => 'Verify Email Address',
    'mail_outro' => 'If you did not create an account or request this verification, no further action is required.',
];
