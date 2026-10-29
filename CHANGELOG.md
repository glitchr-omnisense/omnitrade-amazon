# Changelog

## Unreleased (1.x)

### Fixed
- `Product::$description` is HTML, as the family's contract has it: the item's features as a list
  (`<ul><li>`, each escaped), where they came as plain text lines.
