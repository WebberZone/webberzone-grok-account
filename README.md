# WebberZone Grok Account

[![License](https://img.shields.io/badge/license-GPL_v2%2B-orange.svg?style=flat-square)](https://opensource.org/licenses/GPL-2.0)
[![Coding Standards](https://img.shields.io/github/actions/workflow/status/WebberZone/webberzone-grok-account/cs.yml?branch=master&label=coding%20standards&style=flat-square)](https://github.com/WebberZone/webberzone-grok-account/actions/workflows/cs.yml)
[![PHP Compatibility](https://img.shields.io/github/actions/workflow/status/WebberZone/webberzone-grok-account/phpcompat.yml?branch=master&label=php%207.4-8.6&style=flat-square)](https://github.com/WebberZone/webberzone-grok-account/actions/workflows/phpcompat.yml)

_Requires:_ WordPress 7.0, PHP 7.4
_License:_ [GPL-2.0+](http://www.gnu.org/licenses/gpl-2.0.html)

---

> [!WARNING]
> This plugin signs in with the public OAuth client used by Grok CLI-style coding agents and calls the regular xAI API with that sign-in. xAI has not published documentation permitting this for third-party apps, and may change or block it. Some subscriptions can sign in but are refused by the API (HTTP 403). Anyone with admin access to the site can use your Grok plan while you are signed in.

## Overview

_WebberZone Grok Account_ adds a **Grok Account** provider to the WordPress AI Client. You sign in with your xAI account (SuperGrok or X Premium) using a one-time device code, and Grok text and image generation run against your plan instead of an xAI API key.

- _Sign in from Settings → Connectors:_ replaces core's API-key field with a "Sign in with xAI" card and modal. Settings → Grok Account offers the same flow.
- _Device flow:_ standard OAuth 2.0 device authorization grant (RFC 8628) against `auth.x.ai`.
- _Text generation:_ `api.x.ai/v1/chat/completions`, via the AI Client's OpenAI-compatible base classes. No other plugin required.
- _Image generation:_ Grok Imagine via `api.x.ai/v1/images/generations`.
- _Tokens:_ encrypted with libsodium using a key derived from the site's auth salt, refreshed automatically under a lock.
- _No build step:_ hand-written ES module for the Connectors card.

Shares its sign-in, token storage and Connectors card design with [WebberZone ChatGPT Account](https://github.com/WebberZone/webberzone-chatgpt-account).

Not supported: video, speech, embeddings and image editing.

## Filters

| Filter | Purpose |
| --- | --- |
| `wzgka_fallback_models` | Models offered when the live model list can't be fetched (ID => `text` or `image`). |

## Contributing

- Fork the repository and create your branch from `master`.
- Run `composer test` (phpcs, PHP compatibility, phpstan) before opening a pull request.
- `composer zip` builds the installable zip into `build/`.

## For Users

See [readme.txt](./readme.txt) for installation and usage instructions.

## Changelog

See [releases](https://github.com/WebberZone/webberzone-grok-account/releases).

## License

GPL v2 or later.
