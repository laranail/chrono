## What does this change?

<!-- One or two sentences. Link the issue if there is one. -->

## Why?

<!-- The problem this solves. For a date/timezone fix, name the zone, the instant, and the wrong
     behaviour — e.g. "Europe/Dublin reports isDst() backwards because PHP's 'I' flag is inverted
     for negative DST". -->

## Checklist

- [ ] Tests added/updated and `composer test` passes
- [ ] `composer lint` is clean (Pint + PHPStan + deptrac + Rector)
- [ ] New DST or calendar fixtures are classified correctly — rule-based assertions hard-coded,
      decree-based assertions by shape and tagged `->group('tzdata')`
- [ ] Generated files were regenerated with `laranail::chrono.sync`, not hand-edited
- [ ] `CHANGELOG.md` updated under `## Next` (for user-facing changes)
- [ ] Docs updated under `docs/` (if behaviour or public API changed)
- [ ] Commits follow [Conventional Commits](https://www.conventionalcommits.org/)
- [ ] New Artisan commands follow `laranail::chrono.<command>`
