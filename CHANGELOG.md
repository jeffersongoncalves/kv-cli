# Changelog

All notable changes to this project will be documented in this file.

## [1.2.0] - 2026-11-13

### Bug Fixes

- Wrap docker inspect timeout in SshTunnelException

### Features

- Support Redis inside Docker containers over SSH

## [1.1.1] - 2026-11-04

### Bug Fixes

- Make database index optional on connections:add

### Build

- **deps:** Bump shivammathur/setup-php from 2.37.2 to 2.40.0
- **deps:** Bump stefanzweifel/git-auto-commit-action
- **deps:** Bump orhun/git-cliff-action from 4.9.0 to 4.9.1

### CI/CD

- Run tests on PHP 8.4 only, same workflow as db-cli

### Other

- Merge remote-tracking branch 'origin/main' into fix/optional-db

# Conflicts:
#	.github/workflows/run-tests.yml

## [1.1.0] - 2026-11-04

### CI/CD

- Add auto-assign and dependabot auto-merge workflows

### Miscellaneous Tasks

- Upgrade to Laravel Zero 13

## [1.0.0] - 2026-11-04

### Documentation

- Add banner to README

### Features

- Initial kv-cli, read-only Redis inspection CLI


