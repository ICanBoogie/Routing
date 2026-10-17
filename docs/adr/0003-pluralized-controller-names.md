# 3. Pluralized controller names

- Status: Rejected.
- Date: 2026-10-17
- Deciders: Olivier Laviale

## Context

When the action of a method is not specified, `ActionResolver` builds it from the name of the
controller and the name of the method: `PhotosController::show` is `photos:show`. The name of the
controller is its class name without the namespace and the `Controller` suffix, hyphenated, and used
as is: `ContactController::show` is `contact:show`. `#[Name]` and the `name:` parameter of
`ConfigBuilder::add_controller()` replace it.

Controllers are conventionally named in the singular (`WatermarkController`), but routes are
overwhelmingly written in the plural, `/admin/watermarks/:watermark_pid/edit` or
`/api/watermarks/:watermark_pid`. With names used as is, the action prefix and the path disagree
(`watermark:edit` for `/watermarks/:watermark_pid/edit`). Pluralizing the inferred name was dropped
earlier; this ADR considered bringing it back.

The scope would be the inferred action, nothing else. Patterns are always explicit, and ids are not
derived from the controller name, so paths and ids would not change.

## Considered options

### A. Names used as is (current, chosen)

Nothing to learn, and the action is the class name. A controller for a collection is named in the
plural (`WatermarksController` is `watermarks:edit`), or uses `#[Name('watermarks')]`.

### B. A naming strategy

An enum `Naming` with `None`, `Plural` and `Singular`, set with a parameter of `add_controller()`,
on `#[Prefix]`, or on a new `#[Controller]` attribute. It overlaps with `#[Name]`, which already
replaces the inferred name, and adds one more invalid combination to validate.

### C. Pluralize the inferred name, override with `#[Name]`

The inferred name is pluralized with `icanboogie/inflector`, hyphenated names on their last
segment. The inflector is idempotent for the words that matter (`photos`, `post-categories`,
`news` and `people` come back unchanged), so `PhotosController` would stay `photos:show`. A
controller that is not a collection (`Contact`, `Dashboard`) would use `#[Name]`.

## Decision

Keep A. Pluralization is not adopted.

C was the only serious candidate. It was rejected because the benefit is cosmetic, while the cost is
real:

- It only changes an inferred identifier. Nothing in the path or the id depends on it, so it
  buys a class name without an `s` and not much else. Naming the class in the plural already
  gives the same action with no code.
- A dependency on `icanboogie/inflector`, and English-only behavior.
- A hidden transformation between the class name and the action (`Contact` becomes `contacts`
  when `contact` was wanted), so the action can no longer be read off the class.
- Actions are public identifiers (route ids, URL generation, compiled configuration) that would
  depend on inflection rules, and could be renamed by a change in the inflector.
- Every controller that is not a collection needs `#[Name]`.

B was rejected because it duplicates `#[Name]` for the sake of the same cosmetic gain.

## Consequences

- The behavior in the 7.0 changelog and in `ActionResolver` stays: names are not pluralized.
- An application that prefers singular class names and plural actions uses `#[Name]` or `name:`.
- This can be revisited if actions are ever derived from more than the controller name, or if
  singular class names become a convention shared with other packages, which would make the
  inference worth its cost.
