=== WebberZone Grok Account ===
Contributors: webberzone, ajaydsouza
Tags: ai, grok, xai, ai client, connectors
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Use your SuperGrok or X Premium subscription for Grok text and image generation in the WordPress AI Client, instead of an xAI API key.

== Description ==

WebberZone Grok Account adds a "Grok Account" provider to the WordPress AI Client. Instead of pasting an xAI API key, you sign in with your xAI account and usage counts against your SuperGrok or X Premium plan.

= Features =

* Sign in from Settings → Connectors using a one-time device code (the standard OAuth device flow). No API key, no command-line helper, no public REST endpoint.
* Text generation with the Grok models available to your plan, including chat history, structured JSON output and function calling.
* Image generation with Grok Imagine.
* Tokens are stored encrypted and refreshed automatically.
* No other plugin required: builds on the OpenAI-compatible classes in the WordPress AI Client.

= Not supported =

* Video, speech and embeddings.
* Image editing (sending an image to modify).

= Important: how this works, and the risk =

This plugin signs in with the public OAuth client that Grok CLI-style coding agents use, then calls the regular xAI API with that sign-in. xAI has not published documentation permitting this for third-party apps.

* xAI may change or block this at any time.
* Some subscriptions can sign in but are refused by the API (HTTP 403). The error is shown when you generate content; an xAI API key is the fallback.
* Anyone with administrator access to your site, or any code running on it, can use your Grok plan while you are signed in. Use it on sites you control.

== Installation ==

1. Upload this plugin to `/wp-content/plugins/webberzone-grok-account` and activate it.
2. Go to Settings → Connectors, click "Sign in with xAI" on the Grok Account card, and follow the prompts.
3. If the AI plugin shows a connector approval notice, approve Grok Account for the plugins that should use it.

== Frequently Asked Questions ==

= Which subscriptions work? =

SuperGrok, or X Premium with Grok access. If sign-in works but generation returns HTTP 403, your plan does not include API access through this sign-in.

= Where are my tokens stored? =

In the `wzgka_tokens` option, encrypted with a key derived from your site's authentication salts. Define `AUTH_KEY` and `AUTH_SALT` in wp-config.php; if they are missing, WordPress stores generated salts in the database and the plugin warns you on Settings → Connectors.

Deactivating keeps the connection. Disconnecting or uninstalling deletes the tokens and revokes the sign-in with xAI; on multisite, uninstalling cleans up every site. If xAI does not confirm the revocation, Settings → Connectors asks you to sign out of all sessions in your account settings. Changing the salts makes the stored tokens unreadable but does not revoke them, so disconnect before you change them.

== Changelog ==

= 1.0.4 =

Release date: 26 October 2026

**Added**

* `wzgka_min_timeout` filter for the minimum request timeout.

**Changed**

* Non-reasoning models keep the caller's request timeout, so a plugin can fall back to another provider sooner. Reasoning models, and requests without a timeout including the WordPress AI Client's 30-second default, still get at least 180 seconds.

**Security**

* Disconnecting and uninstalling now revoke the sign-in with xAI, with a warning on Settings → Connectors if xAI does not confirm it.

**Fixed**

* A temporary token refresh error failed AI requests while the current access token was still valid; the refresh now retries after a minute.
* Uninstalling on multisite removed the stored tokens from the main site only.

= 1.0.3 =

Release date: 9 October 2026

**Added**

* Settings → Connectors warning when the authentication salts are stored in the database.

**Security**

* Encrypted the pending sign-in code stored while waiting for approval.

**Fixed**

* A token refresh rejected with HTTP 400 for a reason other than an expired or revoked session disconnected the account.
* Disconnecting caused a fatal error when the WordPress AI Client was unavailable.
* Uninstalling left pending sign-in data in the database.
* Overlapping sign-in checks could poll xAI faster than the requested interval.

= 1.0.2 =

Release date: 3 October 2026

**Changed**

* Removed the `Domain Path` header, which pointed to a missing `languages` folder.

= 1.0.1 =

Release date: 3 October 2026

**Changed**

* Minified the Connectors script, retaining the readable version when `SCRIPT_DEBUG` is enabled.

**Fixed**

* Temporary network and server errors ended sign-in instead of retrying.
* Canceled or replaced sign-ins could reconnect the account when pending responses arrived.
* Concurrent token refreshes could time out unnecessarily.
* The Copy button failed when clipboard access was unavailable.
* Image models advertised unsupported PNG output and custom options.

= 1.0.0 =

Release date: 26 September 2026

**Added**

* Initial release.

= Earlier versions =

For the changelog of earlier versions, please refer to the [releases page on GitHub](https://github.com/WebberZone/webberzone-grok-account/releases).

== Upgrade Notice ==

= 1.0.4 =
Lets plugins set a shorter timeout for non-reasoning models to fall back to another provider sooner. Disconnecting now revokes the sign-in with xAI, uninstalling cleans up every site on multisite, and temporary token errors no longer interrupt AI requests.
