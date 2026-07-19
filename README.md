# miniPORTAL

miniPORTAL is a lightweight, modular CMS for teams, projects and communities.
This repository contains the official, installable public distribution.

## Installation

See [INSTALL.md](INSTALL.md) for server requirements and the web installer
workflow. In short: place the repository contents in the document root, grant
PHP write access to the documented runtime directories, and open `install.php`.

miniPORTAL requires PHP 8.4 or newer, MySQL or MariaDB, HTTPS in production and
at least one supported OAuth/OIDC provider.

## Distribution scope

This repository is generated from the clean `install/cms` runtime. It contains
the installer, Core, public modules and templates needed by a new miniPORTAL
installation. It does not contain local secrets, production data, build tools
belonging to the source installation or SyntaxDevTeam's dedicated modules:

- Econizer,
- Minecraft Console,
- Licences,
- Plugin Stats.

Dedicated modules can still be installed later as reviewed, signed packages
through the module manager. They are intentionally unavailable in the initial
installation wizard.

## Source and updates

The public repository is updated from the private source repository by a
repeatable distribution publisher. Do not add generated installation secrets or
local runtime data to Git.

miniPORTAL is licensed under the [MIT License](LICENSE).
