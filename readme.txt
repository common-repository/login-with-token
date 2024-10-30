=== Plugin Name ===

Plugin Name:  Login With Token
Donate link: https://www.refushe.org/donate
Description:  Login using generated token sent through SMS to User's phone.
Plugin URI:   https://github.com/tschweizer79/ts-access-with-token
Author:       Tomas Schweizer (tschweizer@gmail.com)
Version:      1.0
Contributors: tschweizer
Text Domain:  login-with-token
Domain Path:  /languages
License:      GPL v2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.txt
Tags:         sms,login,authentication
Tested up to: 6.0.3
Stable tag:   1.0
Requires PHP: 7.3
Requires at least: 6.0.2

The plugin allows you to change the login process for WordPress by sending an SMS with a link and a unique token that logs in automatically.

== Description ==

Sometimes, you need a very simple login system. The background for this plugin is a population with very low literacy.
People that hardly remember passwords or even their own emails, but still can remember the mobile number. 
This was the case for a NGO that helps refugees from other countries in Africa and the literacy level for these refugees are very low, language is a barrier, but still need to access a WordPress plataform used as a E-Learning platform providing all kind of instructional videos. This NGO required users to be logged in, in order to track which videos were watched, how are users following the Curriculum, etc.

The first idea, was using OTP, but even this approach was hard for this population. 

The approach decided was to create a new plugin that will send a link, containing a unique Token, that after clickling will automatically log the user into WordPress, without needing to type email, password or any OTP. Simple: click!

The plugin was design to be used specially by mobile phones accessing WordPress in a mobile browser. But is not limited to mobile phones.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ts-access-with-token` directory, or install the plugin through the WordPress Admin dashboard directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Use the "Login with Token->Settings" screen to configure the plugin.
1. Check for instructions on "Login with Token->Instructions" screen.

== Frequently Asked Questions ==

=How token expiration works?=

There are 3 different behaviors: 
- Never expires: Token will work indefinitelly (or till a new token is generated).
- Expires immediately after usage: Token will work just once and a new token will be required for a new log in attempt.
- x Days: Token will expire automatically after X days based on Token create date (using WP Cron).

=How can I test my Gateway Configurations?=

Use the "Login with Token->Test Gateway" screen. It is very straightforward and provide all information required to debug.

=Can I see who is using?=

If you enable Log Events in Settings page, you will find usage information in "Login with Token->Log" screen.

=What is the difference between this solution and one-time password (OTP) solution?=

A one-time password (OTP) is an automatically generated numeric or alphanumeric string of characters that authenticates a user for a single transaction or login session. Solutions using OTP for logins usually send one OTP via SMS and then ask for the generated OTP in a form. After that, user logs in.

In this solution, the user receives a link (with a token in the URL). The token is uniquem and after clicking the link, the user is autaomatically logged in, without the need to provide a OTP or extra information.

== Screenshots ==

1. Menu in Admin area.
2. Example with Animation, Terms and Conditions check, Country Code being displayed.
3. Example hiding the default animation and Terms and Conditions.
4. Example hiding Country Code.
5. Texts and Labels are totally customizable.

== Changelog ==

= 1.0 =
* First release

= 0.5 =
* Proof of Concept

== Upgrade Notice ==

= 1.0 =
First release. Deploy as any other plugin.