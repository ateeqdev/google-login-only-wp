=== Easy Secure Login ===
Contributors: hardtoskip
Donate link: https://hardtoskip.com/
Tags: google login, google one tap, oauth, authentication, security
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.1.1
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Replaces the default WordPress login with secure Google Sign-In and Google One Tap authentication. Features enhanced security, user management, and seamless login experience.

== Description ==

**Easy Secure Login** is a security-focused authentication plugin that eliminates WordPress passwords and replaces them with **Google-only authentication**. It provides both **Google Sign-In** and **Google One Tap**, offering users a fast, secure, and frictionless login experience.

This plugin was born out of necessity after a brute-force attack, designed to ensure that **only verified Google accounts can access your site**. It integrates robust **security features**, automatic user management, and a user-friendly setup wizard.

### Key Features
- **Google-only authentication** (no passwords, no brute-force attacks).
- **Google One Tap** support for instant, seamless login.
- **User management**: whitelist users by email and assign roles.
- Optional **new user sign-ups** with role assignment.
- **Google profile picture integration** in WordPress profiles and avatars.
- Built-in **security features**:
  - Disable XML-RPC
  - Disable file editing in wp-admin
  - Hide WordPress version
  - Restrict REST API access to logged-in users
  - Block direct access to sensitive files
- Clean and modern UI with a **step-by-step setup wizard**.
- Works with latest WordPress versions.

This plugin ensures **maximum login security** while improving the **user experience**.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via **Plugins → Add New** in WordPress.
2. Activate the plugin through the **Plugins** menu.
3. Go to **Easy Secure Login** in the WordPress admin sidebar.
4. Follow the **setup wizard**:
   - Create a Google Cloud project and configure OAuth credentials.
   - Add redirect URIs and JavaScript origins (provided in the wizard).
   - Enter your **Google Client ID** and **Client Secret**.
   - Configure **authorized users** or enable public sign-ups.
   - Enable optional **Google One Tap**.
   - Review and enable **security enhancements**.
5. Test login at your WordPress login page.

That’s it! WordPress password login is now disabled, and users authenticate securely via Google.

== Frequently Asked Questions ==

= Does this completely replace WordPress password login? =
Yes. Password login, password reset, and registration forms are disabled. Only Google Sign-In is allowed, protecting against brute-force attacks.

= Can I allow only specific users? =
Yes. You can whitelist users by Google email address and assign roles. Only those users will be able to log in.

= What if I want to allow new users to register? =
You can enable **Allow New Sign-Ups**. Any Google user can then sign in and will be assigned your chosen default role (subscriber recommended).

= Does it work with Google One Tap? =
Yes. Google One Tap is automatically enabled on the login page. You can also enable it on the homepage or other public pages.

= What happens to existing WordPress users? =
They can log in using the Google account associated with their WordPress email.

= Is this plugin compatible with other login plugins? =
Since it **replaces WordPress authentication**, it may conflict with other login-related plugins.

= How secure is this? =
Very secure. Authentication happens entirely through Google OAuth, with state tokens, CSRF protection, and token verification. Additionally, extra security hardening is included.

== Changelog ==

= 2.1.1 =
Initial Release