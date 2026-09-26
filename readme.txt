=== WebberZone Grok Account ===
Contributors: webberzone, ajaydsouza
Tags: ai, grok, xai, ai client, connectors
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Use your SuperGrok or X Premium subscription for Grok text and image generation in the WordPress AI Client, instead of an xAI API key.

== Description ==

WebberZone Grok Account adds a "Grok Account" provider to the WordPress AI Client. Instead of pasting an xAI API key, you sign in with your xAI account and usage counts against your SuperGrok or X Premium plan.

= Features =

* Sign in from Settings → Connectors or Settings → Grok Account using a one-time device code (the standard OAuth device flow). No API key, no command-line helper, no public REST endpoint.
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

In the `wzgka_tokens` option, encrypted with a key derived from your site's authentication salts. Changing the salts in wp-config.php disconnects the account. Deactivating keeps the connection; uninstalling removes it.

== Changelog ==

= 1.0.0 =
* Initial release.
