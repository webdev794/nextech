Paste this into Claude in VS Code:

---

Merge branch `v9` from origin into my current local work and apply its database changes. Before changing anything, read `docs/v9-changes.md` (it's on `v9`) for the full list of changes.

1. `git fetch origin v9`, then merge `origin/v9` into my current branch. Resolve conflicts by keeping both sides' functionality. If both sides changed the same logic, stop and ask me first.
2. Run `php artisan migrate:status`. Check that none of my local migrations already adds the columns or table that `v9` migrations `2026_10_05_000045`–`000049` add. Then run `php artisan migrate`. The `v9` migrations skip themselves if the column or table already exists.
3. Go through section 3 of `docs/v9-changes.md` ("Behaviour changes that could affect existing features"). For each item, check whether my local code depends on the old behaviour, and tell me what you found before changing anything.
4. Run `php artisan test`, `npm run lint` and `npm run build`, and fix only what this merge broke.
5. Summarise: conflicts resolved, migrations run, anything in my local work that `v9` changes, and test results.
