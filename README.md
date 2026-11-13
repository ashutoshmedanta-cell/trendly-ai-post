# Trendly AI Post

An open-source WordPress editorial assistant for topic research and AI-assisted article drafting.

**Current version:** 0.2.0 — early development. This is not yet a production-ready autonomous publishing platform.

## Features in 0.2.0

- **Topic discovery:** Search Google News RSS for recent headlines in a topic or niche. Results link to original publishers and are cached for 30 minutes.
- **AI article drafting:** Generate a structured article through an OpenAI-compatible chat-completions endpoint.
- **Editorial controls:** Choose a writing tone, target length and focus keyword.
- **SEO fields:** Saves suggested SEO title, meta description and focus keyword as post metadata (\`_trendly_seo_title\`, \`_trendly_meta_description\`, \`_trendly_focus_keyword\`). These are not automatically connected to Yoast or Rank Math.
- **Optional featured image:** Search Pexels and import the first landscape result into the WordPress media library, with photographer/source credit stored in post metadata.
- **Editorial calendar:** Review drafts and future-scheduled posts.
- **Human approval:** Generated articles are drafts by default. A future date entered by the editor schedules the post through WordPress; nothing is published immediately by generation.

## Requirements

- WordPress 6.2+
- PHP 7.4+
- An API key for an OpenAI-compatible chat-completions provider
- Optional Pexels API key for featured image search

## Install

1. Download this repository as a ZIP.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP and activate **Trendly AI Post**.
4. Open **Trendly AI Post → Settings**.
5. Enter your AI endpoint, model and API key. Add a Pexels API key only if you want image search.
6. Use **Discover Topics** to research headlines, then **Generate Article** to create a draft.
7. Review the draft, check facts and source material, and publish or schedule it yourself.

For manual installation, copy the repository contents into \`wp-content/plugins/trendly-ai-post/\`.

## Provider configuration

The default endpoint is \`https://api.openai.com/v1/chat/completions\`; the default model is \`gpt-4o-mini\`. Change both to match your provider's compatible API. The plugin currently expects a response with \`choices[0].message.content\` containing JSON.

API keys are stored in the WordPress options table. Use HTTPS, limit administrator access, keep backups private and never commit secrets. For high-security deployments, consider a future environment-variable or secret-manager integration.

## Image credits

Pexels images are imported into the site's media library. The photographer name and Pexels page URL are saved as \`_trendly_image_credit\` post metadata. Review Pexels' current API terms and the image licence before using an image commercially. The plugin does not guarantee that a returned image is suitable for every article.

## Important limitations

- Google News RSS is a discovery aid, not a fact-checking service or a guarantee of comprehensive trend coverage.
- SEO metadata is stored in Trendly-specific post meta; SEO plugins may not read it automatically.
- Pexels search currently selects the first result; there is no image-selection gallery yet.
- The editorial calendar is a list of drafts and scheduled posts, not a drag-and-drop calendar.
- Provider-specific features such as Anthropic's native Messages API require an OpenAI-compatible endpoint or a future provider adapter.
- AI-generated content may be wrong, biased or incomplete. Verify factual claims, statistics, quotations, health/legal/financial guidance, image rights and source attributions before publication.

## Development

- Follow WordPress PHP coding standards.
- Keep credentials and personal data out of commits and issues.
- Use nonces and capability checks for admin actions.
- Sanitize input and escape output.
- Run the GitHub Actions PHP syntax workflow after changes.

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Roadmap

- [ ] Native provider adapters
- [ ] User-selectable image results and image alt-text review
- [ ] Direct integration with popular SEO plugins
- [ ] Automated tests using the WordPress test suite
- [ ] Better feed source filters and configurable regions
- [ ] Accessibility and translation review

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
