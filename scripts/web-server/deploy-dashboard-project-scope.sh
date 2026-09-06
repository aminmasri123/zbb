#!/usr/bin/env bash
# Run from the authenticated server session after fetching this revision.
# This update changes application files only; it does not run migrations or seeds.
set -euo pipefail
umask 077
cd /var/www/matrix
expected_base=1cbd16714a8a6d07a9708c8d17c75bbab3223e34
target=${1:?Pass the reviewed dashboard commit SHA}
test "$(git branch --show-current)" = main
test -z "$(git status --porcelain --untracked-files=no)"
test "$(git rev-parse HEAD)" = "$expected_base"
test "$(git rev-parse "$target^")" = "$expected_base"
while IFS= read -r path; do
    case "$path" in
        app/Http/Controllers/DashbaordController.php|resources/js/Pages/Dashboard.vue|tests/Feature/DashboardPermissionCardsTest.php|scripts/web-server/deploy-dashboard-project-scope.sh) ;;
        *) printf 'Unexpected file; stopping: %s\n' "$path" >&2; exit 1 ;;
    esac
done < <(git diff --name-only "$expected_base" "$target")
test -d node_modules
test -f .env
backup=$(mktemp -d "$HOME/zbb-dashboard-backup.XXXXXXXX")
git archive HEAD > "$backup/source.tar"
tar -czf "$backup/runtime.tar.gz" .env storage public/build
printf 'Backup: %s\n' "$backup"
git merge --ff-only "$target"
npm run build
php artisan view:clear
printf 'Updated commit: %s\n' "$(git rev-parse HEAD)"
printf 'Database unchanged; no migrations or seeds were run. Backup: %s\n' "$backup"
