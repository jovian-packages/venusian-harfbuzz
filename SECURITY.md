# Security Policy

## Supported versions

Venusian is pre-1.0. No 0.x release receives security fixes or advisories; fixes land in the
next release line. Security support starts with 1.0.

| Version | Security fixes |
|---------|----------------|
| < 1.0   | No             |

## Reporting a vulnerability

Please don't open a public issue for a security problem.

Report it privately through GitHub: the **Report a vulnerability** button on this repository's
**Security** tab. If that isn't available, email **info@projectsaturnstudios.com**.

Include what you found, the affected version, and steps to reproduce. Reports are read and
weighed for the release line in development; before 1.0 there is no response-time commitment.

## Security model

- Font files are parsed by HarfBuzz, in C, through ext-harfbuzz. This package parses no font bytes itself. A file HarfBuzz cannot read as a font is refused before anything is shaped with it.
- A font file is opened read-only, from the path a `FontSource` names. Paths come from the application or its font catalog, never from the text being shaped.
- Text is passed to HarfBuzz as UTF-8 bytes with its length. Nothing in the text selects a file or a face.
- Nothing is written. One HarfBuzz font a source is held for the shaper's life.
