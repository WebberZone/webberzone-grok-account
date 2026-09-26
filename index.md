---
title: WebberZone Grok Account
description: Use your SuperGrok or X Premium subscription for Grok text and image generation in the WordPress AI Client. Sign in with a device code, no xAI API key needed.
permalink: /
---

<div class="hero">
  <div class="eyebrow">Free &middot; Open Source &middot; No API Key</div>
  <h1>Use your <em>Grok</em> plan in the WordPress AI Client</h1>
  <p class="lead">WebberZone Grok Account adds a <strong>Grok Account</strong> provider to the WordPress AI Client. Sign in with your xAI account from Settings&nbsp;→&nbsp;Connectors, and Grok text and image generation run against your SuperGrok or X Premium plan instead of a separately billed xAI API key.</p>
  <div class="hero-ctas">
    <a href="#installation" class="btn-primary">Installation</a>
    <a href="https://github.com/WebberZone/webberzone-grok-account/releases/latest" target="_blank" class="btn-outline">Download Latest Release</a>
    <a href="https://github.com/WebberZone/webberzone-grok-account" target="_blank" class="btn-outline">View on GitHub</a>
  </div>
</div>

<div class="home-section">
  <div class="eyebrow">Overview</div>
  <h2 class="section-title" style="margin-bottom:8px;">Sign in once, use it everywhere</h2>
  <p style="color:var(--wz-warm-grey); max-width:64ch;">The provider plugs into the AI Client that ships with WordPress 7.0, so any feature built on it, including the WordPress AI plugin, can use your Grok plan. It needs no other plugin: it builds on the AI Client's own OpenAI-compatible classes and calls the regular xAI API.</p>

  <div class="feature-grid">
    <div class="feature-card">
      <h3>Device-code sign-in</h3>
      <p>Click "Sign in with xAI" on the Connectors screen, approve the one-time code at xAI, and you're connected. Standard OAuth device flow, no API key, no public endpoint. Tokens are stored encrypted and refreshed automatically.</p>
    </div>
    <div class="feature-card">
      <h3>Text generation</h3>
      <p>The Grok models available to your plan, newest first, with chat history, structured JSON output and function calling.</p>
    </div>
    <div class="feature-card">
      <h3>Image generation</h3>
      <p>Grok Imagine, including the higher-quality and typography-aware variants your plan offers.</p>
    </div>
  </div>
</div>

<div class="home-section" style="padding-top:0;">
  <div class="eyebrow">Read this first</div>
  <h2 class="section-title" style="margin-bottom:8px;">How it works, and the risk</h2>
  <div class="callout">
    <h3>Not an officially documented integration</h3>
    <p>This plugin signs in with the public OAuth client used by Grok CLI-style coding agents, then calls the regular xAI API with that sign-in. xAI hasn't published documentation permitting this for third-party apps.</p>
    <ul>
      <li>xAI may change or block this at any time.</li>
      <li>Some subscriptions can sign in but are refused by the API (HTTP 403). The error is shown when you generate content; an xAI API key is the fallback.</li>
      <li>Anyone with administrator access to your site, or any code running on it, can use your Grok plan while you are signed in. Use it on sites you control.</li>
    </ul>
  </div>
</div>

<div class="home-section" id="installation" style="padding-top:0;">
  <div class="eyebrow">Get started</div>
  <h2 class="section-title" style="margin-bottom:8px;">Installation</h2>

  <ol class="step-list">
    <li>
      <h3>Install the plugin</h3>
      <p>Download the <a href="https://github.com/WebberZone/webberzone-grok-account/releases/latest" target="_blank">latest release</a>, upload it under Plugins → Add New → Upload Plugin, and activate it.</p>
    </li>
    <li>
      <h3>Sign in</h3>
      <p>Go to <strong>Settings → Connectors</strong>, click <strong>Sign in with xAI</strong> on the Grok Account card, open the link, and approve the code with your xAI account.</p>
    </li>
    <li>
      <h3>Approve it for your AI features</h3>
      <p>If the AI plugin shows a connector approval notice, approve Grok Account for the plugins that should use it.</p>
    </li>
  </ol>
</div>

<div class="home-section" style="padding-top:0;">
  <div class="eyebrow">Limits</div>
  <h2 class="section-title" style="margin-bottom:8px;">What it doesn't do</h2>
  <p style="color:var(--wz-warm-grey); max-width:64ch;">Video, speech and embeddings aren't supported, and neither is image editing. Grok 4 reasoning models don't accept stop sequences or presence and frequency penalties, so those options aren't offered.</p>
</div>

<div class="home-section" style="padding-top:0;">
  <div class="eyebrow">Requirements</div>
  <h2 class="section-title" style="margin-bottom:8px;">What you need</h2>
  <p style="color:var(--wz-warm-grey); max-width:64ch;">WordPress 7.0+, PHP 7.4+, and an xAI account with SuperGrok or X Premium. Also see <a href="https://webberzone.github.io/webberzone-chatgpt-account/">WebberZone ChatGPT Account</a> for ChatGPT plans, and the <a href="https://github.com/WebberZone/webberzone-grok-account/releases" target="_blank">releases</a> page for the changelog.</p>
</div>
