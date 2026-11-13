# Trendly AI Post

An open-source WordPress plugin starter that turns a topic into an editable post draft using an OpenAI-compatible chat completions API.

**Status:** Early development (`0.1.0`). This is a functional starter, not a production-ready autonomous publishing system.

## Current features

- WordPress admin screen for provider configuration.
- Configurable API endpoint, model name, and API key.
- Topic-to-article generation.
- Creates a **draft**, never publishes automatically.
- Capability checks, nonce verification, input sanitization, and output escaping.

## Requirements

- WordPress 6.2+
- PHP 7.4+
- An API key for a compatible chat-completions provider.

## Installation

1. Download or clone this repository.
2. Copy the `trendly-ai-post` directory into `wp-content/plugins/`.
3. Activate **Trendly AI Post** in WordPress Admin → Plugins.
4. Open **Trendly AI Post** in the admin menu.
5. Enter your provider endpoint, model, and API key.
6. Enter a topic and generate a draft.
7. Review and fact-check the draft before publishing.

## Configuration

The default endpoint is `https://api.openai.com/v1/chat/completions` and the default model is `gpt-4o-mini`. Both can be changed in the plugin settings for compatible providers.

The API key is stored in the WordPress options table. Use HTTPS, keep administrator access restricted, and never share database backups or screenshots containing secrets. For production deployments, consider injecting secrets through environment configuration rather than storing them in the database.

## Roadmap

- [ ] Provider adapters and provider-specific response parsing
- [ ] Trending-topic discovery with source links and timestamps
- [ ] SEO title, meta description, and keyword suggestions
- [ ] Editorial templates and configurable tone
- [ ] Image-provider integrations with attribution handling
- [ ] Scheduling with explicit user approval
- [ ] Unit and integration test coverage
- [ ] Internationalization and accessibility review

## Responsible use

AI-generated content can be inaccurate or incomplete. Always verify facts, statistics, medical/legal/financial claims, quotations, copyright, and source attribution. This plugin creates drafts for human review; it does not publish content automatically.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Bug reports and focused pull requests are welcome.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
