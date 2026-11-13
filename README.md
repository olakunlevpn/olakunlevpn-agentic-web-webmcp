# [Olakunlevpn] Agentic Web & WebMCP for XenForo 2.3+

Expose WebMCP tools, llms.txt, ard.json discovery, and clean accessibility for AI browsing agents.

[![XenForo Version](https://img.shields.io/badge/XenForo-2.3.0%2B-blue.svg)](https://xenforo.com)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![XenForo Resource](https://img.shields.io/badge/Download-XenForo%20Resource-orange.svg)](https://xenforo.com/community/resources/olakunlevpn-agentic-web-webmcp.11016/)

## Introduction

AI agents like Google Gemini, Claude, and Chrome's built-in models are browsing the web autonomously. When they land on a standard XenForo forum, they run into three big walls:

- They have to scrape messy HTML tables and paginate through pages just to find one answer.
- Icon buttons (like search triggers, mobile menu toggles, and reaction bars) lack clear text names in the accessibility tree, leaving agents guessing what to click.
- Dynamic widgets and late-loading banner ads shift the page layout after load, causing agents to mis-click or lose their place.

This add-on turns your XenForo community into an AI-ready destination. It registers native WebMCP tools directly in the browser, publishes structured discovery files, clears out accessibility traps, and keeps your page layout steady under Google Chrome's Agentic Browsing audits.

Official resource page and direct download:
[Download on XenForo Community](https://xenforo.com/community/resources/olakunlevpn-agentic-web-webmcp.11016/)

## Features

### 1. WebMCP Browser Integration

WebMCP lets modern AI browsing agents call forum capabilities directly instead of scraping raw HTML. The add-on provides:

- **Direct Tool Registration:** Exposes four core tools via `navigator.modelContext`, `window.WebMCP`, and standard browser events.
- **Forum Keyword Search:** AI agents can run keyword searches with optional node filtering and custom result limits.
- **Thread Reader:** Delivers clean, structured message text and author data by thread ID without scraping signatures, ads, or bloated markup.
- **Board Hierarchy and Recent Activity:** Lets visiting agents pull your public category list and latest discussions in clean JSON.
- **Declarative Search Forms:** Server-renders `toolname` and `tooldescription` attributes on XenForo search forms so agents recognize search inputs immediately.

### 2. Automated AI Discovery

Visiting crawlers need an easy way to map what your forum is about. The add-on handles discovery out of the box:

- **Dynamic llms.txt and llms-full.txt:** Automatically generated Markdown summaries listing your public boards, posting rules, and search endpoints.
- **Agentic Resource Discovery (ard.json):** Serves a valid ARD v1 manifest at `/.well-known/ard.json` and `/ard.json`.
- **Robots and Head Autodiscovery:** Injects `<link rel="llms">` and `<link rel="ard">` in the page head, and appends the Agentmap directive to your robots file.
- **Automatic Caching:** Builds the discovery files in the background on an hourly cron job to keep server load at zero.

### 3. Machine-Eye Accessibility (a11y)

Lighthouse agentic browsing audits test how well an AI agent can navigate your accessibility tree. This add-on cleans it up:

- **Prohibited ARIA Role Cleanup:** Removes invalid roles like `role="tabpanel"` from `<li>` tags and moves them to inner containers where they belong.
- **Heading Tab Normalization:** Fixes heading elements carrying `role="tablist"`, turning them into compliant semantic containers.
- **Accessible Control Names:** Assigns clear `aria-label` text to mobile menu buttons, off-canvas toggles, and icon-only links.
- **Focusable Guard:** Automatically ensures `aria-hidden` elements (like node icon badges) cannot trap keyboard or agent focus.
- **Live Region Notifications:** Adds polite live regions to dynamic blocks so agents catch updates without spamming page reloads.

### 4. Layout Stability and Anti-CLS Shield

Layout shifts break agent interactions and frustrate human members alike:

- **Reserved Ad Heights:** Keeps a placeholder space ready for top and sidebar banner ads before their creatives load.
- **Reserved Widget Heights:** Prevents live statistics blocks from kicking the forum list downward when AJAX threads arrive.
- **Zero Third-Party Coupling:** Works entirely with standard XenForo templates and pure DOM observation, without modifying third-party add-on files.

## Requirements

The add-on requires:

- XenForo 2.3.0 or higher
- PHP 8.0 or higher

## Installation

### Method 1: Install from Archive (Recommended)

1. Download the release archive from the [XenForo Resource Manager](https://xenforo.com/community/resources/olakunlevpn-agentic-web-webmcp.11016/).
2. Log in to your XenForo Admin Control Panel.
3. Navigate to **Add-ons** > **Install/upgrade from archive**.
4. Upload the downloaded `.zip` file and click **Install**.

### Method 2: Manual Upload

1. Download and extract the release zip file.
2. Upload the contents of the `upload` directory into the root of your XenForo installation:
   - Upload `upload/src/addons/Olakunlevpn/AgenticWeb` to `src/addons/Olakunlevpn/AgenticWeb`
   - Upload `upload/js/olakunlevpn/agentic_web` to `js/olakunlevpn/agentic_web`
3. In your Admin Control Panel, go to **Add-ons** and click **Install** next to `[Olakunlevpn] Agentic Web & WebMCP`.
4. Alternatively, install via the command line:

```bash
php cmd.php xf-addon:install Olakunlevpn/AgenticWeb
```

## Configuration

Navigate to **Options** > **[Olakunlevpn] Agentic Web & WebMCP** in your Admin Control Panel to configure:

- **Enable Agentic Web & WebMCP:** Master switch for all capabilities.
- **Enable WebMCP Browser Tools:** Toggle browser tool registration for AI agents.
- **Active WebMCP Tools:** Choose which tools (`searchForum`, `getThread`, `getNodeList`, `getRecentDiscussions`) are exposed.
- **WebMCP Rate Limit:** Maximum requests per minute per IP address (defaults to 60).
- **Enable llms.txt Discovery:** Toggle dynamic `/llms.txt` and `/llms-full.txt` endpoints.
- **Include Forum Rules in llms.txt:** Append your board rules to the markdown summaries.
- **Agent Discovery Manifest (ard.json):** Toggle `/.well-known/ard.json` and `<link rel="ard">`.
- **Sample Search Queries for AI:** Example search queries to help AI search engines map user intent.
- **Stabilize Page Layout:** Inject anti-CLS space reservations for ads and live widgets.
- **Reserved Ad Height (px):** Default placeholder height for banner ad slots (default: 90px).
- **Reserved Live Statistics Height (px):** Default placeholder height for dynamic statistics (default: 280px).
- **Enhance Accessibility Labels:** Automatically inject accessible names on icon buttons and controls.
- **Live Content Updates:** Attach polite live regions to dynamic forum widgets.

## Verification

To verify that your forum passes Google Chrome's Agentic Browsing audits:

1. Open [Google PageSpeed Insights](https://pagespeed.web.dev/) or Chrome DevTools Lighthouse.
2. Enter your forum URL.
3. Inspect the **Agentic Browsing Audits** section:
   - Registered WebMCP tools: Passed
   - Forms missing declarative WebMCP: Passed
   - WebMCP schema validity: Passed
   - llms.txt: Passed
   - ard.json: Passed
   - Accessibility for agents: Passed
   - Layout stability: Passed

## Repository Structure

```
upload/
├── js/
│   └── olakunlevpn/
│       └── agentic_web/
│           ├── webmcp.js
│           └── webmcp.min.js
└── src/
    └── addons/
        └── Olakunlevpn/
            └── AgenticWeb/
                ├── addon.json
                ├── hashes.json
                ├── icon.png
                ├── Setup.php
                ├── Cron/
                ├── Pub/
                ├── Service/
                ├── _data/
                └── _output/
LICENSE
README.md
```

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.

## Support

- XenForo Resource: [Olakunlevpn Agentic Web & WebMCP](https://xenforo.com/community/resources/olakunlevpn-agentic-web-webmcp.11016/)
- Support Desk: [https://maylancer.org/support](https://maylancer.org/support)
- Product Information: [https://maylancer.org/products](https://maylancer.org/products)
