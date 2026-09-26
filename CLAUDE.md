# CLAUDE.md

## Commits and pull requests

- Never add `Co-Authored-By` trailers, Claude session links, "Generated with Claude Code" lines or any other attribution to commit messages, PR titles or PR bodies.
- Author every commit as `Ajay D'Souza <ajaydsouza@users.noreply.github.com>`. If `git config user.name` is anything else, pass `-c user.name="Ajay D'Souza" -c user.email="ajaydsouza@users.noreply.github.com"` to `git commit`.
- Use British English in commit messages.

## Shared code

`OAuth_Client`, `Token_Store`, `Admin`, `Connectors`, `Availability` and `assets/js/connectors.js` are shared with [webberzone-chatgpt-account](https://github.com/WebberZone/webberzone-chatgpt-account). They differ only in namespace, the `wzgka` prefix and the text domain. Make any change to them in both repositories.
