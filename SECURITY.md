# Security Policy

## Reporting a vulnerability

Email **laurowguedes@gmail.com** rather than opening a public issue. You will get
an acknowledgement within a few days.

## What is worth reporting

- a template, theme slug, path or option that makes `landing:install` run a
  command other than the package manager it was given, or write outside the
  application root
- a generated workflow that exposes a repository secret in a log or to code from
  a pull request

Templates and themes are other people's code, downloaded and installed as you
asked. Review a template before you run it; that is not something this package
can do for you.

## Supported versions

The latest minor release receives security fixes.
