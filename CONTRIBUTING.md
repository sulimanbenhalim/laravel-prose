# Contributing

Contributions are **welcome** and will be fully **credited**.

We accept contributions via Pull Requests on [GitHub](https://github.com/sulimanbenhalim/eloquent-explainer).

## Pull Requests

- **Follow PSR-12 Coding Standard** - The easiest way to apply the conventions is to install [Laravel Pint](https://laravel.com/docs/pint).

- **Add tests!** - Your patch won't be accepted if it doesn't have tests.

- **Document any change in behaviour** - Make sure the `README.md` and any other relevant documentation are kept up-to-date.

- **Consider our release cycle** - We try to follow [SemVer v2.0.0](http://semver.org/). Randomly breaking public APIs is not an option.

- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.

- **Send coherent history** - Make sure each individual commit in your pull request is meaningful. If you had to make multiple intermediate commits while developing, please [squash them](http://www.git-scm.com/book/en/v2/Git-Tools-Rewriting-History#Changing-Multiple-Commit-Messages) before submitting.

## Development

### Setup

```bash
git clone https://github.com/sulimanbenhalim/eloquent-explainer.git
cd eloquent-explainer
composer install
```

### Testing

```bash
composer test
```

### Code Style

```bash
composer format
```

### Static Analysis

```bash
composer analyse
```

## Adding New Query Support

When adding support for new Eloquent query methods:

1. **Add translator logic** in the appropriate translator class
2. **Add configuration** if new settings are needed
3. **Add comprehensive tests** covering edge cases
4. **Update documentation** with examples
5. **Update the supported methods table** in README.md

## Translation Rules

When implementing new translation rules:

- **Be deterministic** - Same query should always produce same description
- **Use natural language** - Descriptions should read like plain English
- **Handle edge cases** - Null values, empty arrays, malformed data
- **Respect configuration** - Honor user settings for field aliases, connectors, etc.
- **Fail gracefully** - Use fallback text for unsupported constructs

## Testing Guidelines

- **Unit tests** for individual translator components
- **Feature tests** for end-to-end query descriptions
- **Edge case tests** for error conditions and malformed input
- **Configuration tests** to ensure settings work correctly

**Happy coding**!