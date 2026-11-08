# Contributing

## Pull Requests

Before submitting a [pull request](https://github.com/domainmod/domainmod/pulls), please contact the maintainers of the repository to discuss the change. As much as we appreciate you wanting to contribute, we don't want you to waste your time working on a change or new feature that won't get implemented (such as a feature that's already in progress).

Every pull request must say in its description how AI was used, as a three-character rating plus a sentence or two of detail. DomainMOD publishes its own [AI Code Rating](ACR.md), which depends on how much of the merged code AI wrote, so a pull request without one stays open and isn't merged until it's added.

The rating uses the [AI Code Rating](https://aicoderating.com/spec/) scales. The spec rates whole projects, so for a pull request apply its scales to your own work:

1. Your own expertise (A–E), not the DomainMOD maintainer's.
2. The share of the code lines your pull request adds or changes that AI wrote (0–4), not counting documentation, comments, or deleted lines. A pull request with no added or changed code uses `0`.
3. How carefully you checked the AI-written code (a–e). With no AI-written code, this is `a`.

The second character must be accurate, since it feeds DomainMOD's own rating; the other two help us decide how closely to review. For example, `B0a` is from an experienced developer (B) whose pull request has no AI-written code, and `C3b` is from a capable developer (C) where AI wrote most of the changed code and they read all of it. Pick the first character for yourself, separately from the other two, and don't copy DomainMOD's own rating. Please don't include an `ACR.md` file; the project keeps its own.

## Questions, Problems & Feature Requests

Please use the [DomainMOD Support Portal](https://support.domainmod.org) for questions, problems using DomainMOD, and feature requests (including new registrar integrations). Feature requests live only in the Support Portal, so other users can vote on them.

## Bug Reports

The Support Portal is the right place for any problem, bugs included. If you're a developer and can point to the code, you can submit a GitHub [issue](https://github.com/domainmod/domainmod/issues) instead. Please report each bug in one place only, not both.

A GitHub issue should be a bug you can reproduce, with the DomainMOD and PHP versions you're using, the steps to reproduce it, and any error messages (with the file and line number, if there is one). Remove any domains, usernames, API keys, and other private details first. Before submitting, please search the existing issues to make sure it hasn't already been reported.

## Security Issues

Please don't report security vulnerabilities in a public issue or in the Support Portal. See [SECURITY.md](SECURITY.md) for how to report them privately.

## Translations

If you're looking to contribute to DomainMOD by doing some translation work, please note that the language files are located in their own repository.

The official DomainMOD translation files can be found here: https://github.com/domainmod/translations
