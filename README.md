# Comment Translator & Spam Checker

A WordPress plugin that adds an inline AI-powered assessment panel to the comment moderation screen. It detects the language of each comment, translates it to English, and classifies it as spam, legit, or unsure — all without leaving the admin interface.

## Features

- **Language detection** — identifies the language of each comment
- **English translation** — translates non-English comments inline
- **Spam assessment** — classifies comments as spam, legit, or unsure with a one-sentence explanation
- **Inline UI** — results appear directly below each comment in the moderation list
- **No external dependencies** — vanilla JS and inline CSS only

## Requirements

- WordPress 5.0 or later
- PHP 7.4 or later
- An [Anthropic API key](https://console.anthropic.com/)

## Installation

1. Download `comment-translator.php`
2. Upload it to your `wp-content/plugins/` directory
3. Activate the plugin in **Plugins** in the WordPress admin

## Configuration

1. Go to **Settings > Comment Translator**
2. Enter your Anthropic API key
3. Click **Save Changes**

## Usage

1. Navigate to **Comments** (wp-admin/edit-comments.php)
2. Each comment row now has a **Translate & Assess** button
3. Click the button to send the comment text to the Anthropic API
4. The result appears inline with:
   - A blue badge showing the detected language
   - A colored verdict badge: green (legit), red (spam), or yellow (unsure)
   - The English translation of the comment
   - A short explanation for the verdict

## How It Works

When you click "Translate & Assess", the plugin sends the comment text via a WordPress AJAX call to a PHP handler. The handler calls the Anthropic Messages API using the `claude-sonnet-4-20250514` model and returns structured JSON with the language, translation, verdict, and reason. The result is rendered inline using vanilla JavaScript.

All AJAX calls are secured with WordPress nonces and require the `moderate_comments` capability.

## License

GPL-2.0-or-later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.
