# Implementation Plan: clean-and-deploy

## Overview

Implement the `clean-and-deploy.sh` Bash script and supporting files that automate cleaning and publishing the Boy Scout Management System to GitHub. Tasks follow the eight phases defined in the design, building incrementally toward a fully wired, runnable script.

## Tasks

- [x] 1. Create supporting files (.gitignore and config.example.php)
  - [x] 1.1 Create `.gitignore` in the project root
    - Exclude `config.php`, `database.php`, `vendor/`, `uploads/`, `db/`, `error.log`, `.env`, `*.log`, `.DS_Store`, `Thumbs.db`, `.vscode/`
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9_
  - [x] 1.2 Create `config.example.php` with placeholder values mirroring `config.php` structure
    - All credential values replaced with descriptive placeholders (e.g., `'your_db_host'`, `'your_smtp_password'`)
    - _Requirements: 2.4_

- [x] 2. Create `clean-and-deploy.sh` scaffold and Phase 1–2 (gitignore + sensitive file protection)
  - [x] 2.1 Write script header, argument validation, and project-root guard
    - Accept one argument: GitHub remote URL; exit with usage message if missing
    - Detect if script is run outside project root and exit with error
    - _Requirements: 8.1_
  - [x] 2.2 Implement Phase 1: copy `.gitignore` into place (or confirm it exists)
    - _Requirements: 1.1_
  - [x] 2.3 Implement Phase 2: create `config.example.php` if absent; verify `config.php` is listed in `.gitignore`
    - _Requirements: 2.1, 2.2, 2.3, 2.4_

- [x] 3. Implement Phase 3–4 (delete debug/test files and duplicate files)
  - [x] 3.1 Implement Phase 3: delete the four debug/test files; skip silently if already absent
    - Files: `debug_event_update.php`, `test_brevo.php`, `test_event_update.php`, `test_modal.php`
    - After each deletion confirm the file no longer exists
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_
  - [x] 3.2 Implement Phase 4: delete superseded duplicate files
    - Delete `delete_event.php` (active version is `delete_event_new.php`)
    - Delete `edit_event_simple.php` (active version is `edit_event.php`)
    - Confirm each file no longer exists after deletion
    - _Requirements: 4.1, 4.2, 4.3, 4.5_

- [x] 4. Implement Phase 5–6 (uploads .gitkeep placeholders and vendor/composer check)
  - [x] 4.1 Implement Phase 5: create `.gitkeep` files in `uploads/` and each subdirectory
    - Create `uploads/.gitkeep`, `uploads/badge_icons/.gitkeep`, `uploads/profile_pictures/.gitkeep`, `uploads/proofs/.gitkeep`, `uploads/waivers/.gitkeep`
    - Create subdirectories if they do not exist
    - _Requirements: 5.2, 5.3_
  - [x] 4.2 Implement Phase 6: verify `composer.json` and `composer.lock` exist; exit with error if either is missing
    - _Requirements: 6.2_

- [x] 5. Checkpoint — review script phases 1–6
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Implement Phase 7 (git init, stage, safety-check, commit)
  - [x] 6.1 Implement `git init` and branch rename to `main`
    - _Requirements: 7.1, 7.5_
  - [x] 6.2 Stage all files with `git add -A`
    - _Requirements: 7.2_
  - [x] 6.3 Implement sensitive-file safety check: if `config.php` or `database.php` appear in staged files, remove them from staging and print a warning
    - _Requirements: 2.5, 7.3_
  - [x] 6.4 Create the initial commit with a descriptive message
    - _Requirements: 7.4_
  - [ ]* 6.5 Write a `verify.sh` smoke-test script that asserts post-conditions
    - Assert `.gitignore` exists, `config.php` is not tracked, debug files are absent, `.gitkeep` files exist, initial commit is present
    - _Requirements: 3.5, 4.5, 7.3_

- [x] 7. Implement Phase 8 (add remote and push to GitHub)
  - [x] 7.1 Add the GitHub remote URL as `origin`
    - _Requirements: 8.1_
  - [x] 7.2 Push `main` to `origin`; on rejection print conflict message and suggest `git pull --rebase origin main` or force-push with warning
    - _Requirements: 8.2, 8.3_
  - [x] 7.3 On successful push, print confirmation message with the GitHub URL
    - _Requirements: 8.4_

- [x] 8. Final checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP
- The script must be idempotent: re-running after an interruption should not cause errors
- All phases exit with a non-zero code and a descriptive message on failure
- `verify.sh` (task 6.5) is a standalone smoke-test helper, not part of the main script
