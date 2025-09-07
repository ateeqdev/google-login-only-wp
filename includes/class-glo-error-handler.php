<?php

class GLO_ErrorHandler
{
    private static $error_messages = null;

    /**
     * Initializes and returns the array of error messages.
     *
     * @return array
     */
    public static function getErrorMessages()
    {
        if (self::$error_messages === null) {
            self::$error_messages = [
                'not_allowed'           => __('Your Google account is not authorized to access this site. Please contact an administrator to request access.', 'google-login-only'),
                'token_exchange_failed' => __('Authentication failed: Could not connect to Google servers. Please try again in a moment.', 'google-login-only'),
                'token_missing'         => __('Authentication failed: No access token received from Google. Please try again.', 'google-login-only'),
                'user_creation_failed'  => __('Authentication successful, but account creation failed. Please contact an administrator for assistance.', 'google-login-only'),
                'invalid_credential'    => __('Invalid authentication data received from Google. Please try signing in again.', 'google-login-only'),
                'userinfo_failed'       => __('Could not retrieve your user information from Google. Please check your Google account permissions and try again.', 'google-login-only'),
                'email_missing'         => __('No email address was provided by Google. Please ensure your Google account has a verified email address.', 'google-login-only'),
                'invalid_state'         => __('Authentication session expired or invalid. Please try logging in again.', 'google-login-only'),
            ];
        }
        return self::$error_messages;
    }

    /**
     * Gets a specific error message by its code.
     *
     * @param string $error_code The code for the desired error message.
     * @return string The translated error message.
     */
    public static function getMessage($error_code)
    {
        $messages = self::getErrorMessages();
        return $messages[$error_code] ?? __('An unknown authentication error occurred. Please try again.', 'google-login-only');
    }
}
