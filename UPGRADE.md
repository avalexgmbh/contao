# Upgrading

## From version 2 to 3

- Support for Contao 4 has been dropped.
- The texts are now also available as content elements. Existing frontend modules keep working, but switching to content elements is recommended as frontend modules are being phased out by Contao.
- The legacy `mod_avalex*.html5` templates have been replaced by Twig templates. Custom templates based on the old ones can no longer be used and are **reset by a migration** - please recreate them as Twig templates (see [README](README.md#templates)).
- Domain, API key and the cached texts of existing modules are kept, no reconfiguration is necessary.
