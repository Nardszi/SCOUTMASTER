# Requirements Document

## Introduction

This feature covers cleaning the Boy Scout Management System project for safe, production-ready publication on GitHub. The process involves removing debug/test files, eliminating duplicate files, protecting sensitive credentials, handling user-uploaded content and vendor dependencies, and initializing a Git repository with a proper push to GitHub.

## Glossary

- **Project_Root**: The top-level directory containing all PHP application files
- **Cleanup_Tool**: The developer or script performing the cleanup and Git operations
- **Sensitive_File**: Any file containing credentials, passwords, API keys, or database connection strings (e.g., `config.php`, `database.php`)
- **Debug_File**: A file created for temporary debugging or testing purposes not intended for production (e.g., `debug_event_update.php`, `test_brevo.php`, `test_event_update.php`, `test_modal.php`)
- **Duplicate_File**: A file that is a redundant or superseded version of another file (e.g., `delete_event_new.php` vs `delete_event.php`, `edit_event_simple.php` vs `edit_event.php`)
- **Gitignore**: A `.gitignore` file that instructs Git to exclude specified files and directories from version control
- **Uploads_Directory**: The `uploads/` directory containing user-generated files such as profile pictures, proofs, and waivers
- **Vendor_Directory**: The `vendor/` directory containing Composer-managed PHP dependencies
- **Config_Template**: A placeholder configuration file (`config-production-template.php`) with no real credentials, safe for version control
- **Repository**: The GitHub remote Git repository where the project will be published

---

## Requirements

### Requirement 1: Create a .gitignore File

**User Story:** As a developer, I want a `.gitignore` file in the project root, so that sensitive, generated, and irrelevant files are never accidentally committed to the repository.

#### Acceptance Criteria

1. THE Cleanup_Tool SHALL create a `.gitignore` file in the Project_Root before any Git operations are performed.
2. THE Gitignore SHALL exclude `config.php` from version control.
3. THE Gitignore SHALL exclude `database.php` from version control.
4. THE Gitignore SHALL exclude the `vendor/` directory from version control.
5. THE Gitignore SHALL exclude the `uploads/` directory from version control.
6. THE Gitignore SHALL exclude the `db/` directory from version control.
7. THE Gitignore SHALL exclude common PHP runtime artifacts including `error.log`, `.env`, and `*.log` files.
8. THE Gitignore SHALL exclude OS-generated files including `.DS_Store` and `Thumbs.db`.
9. THE Gitignore SHALL exclude the `.vscode/` directory from version control.

---

### Requirement 2: Protect Sensitive Configuration Files

**User Story:** As a developer, I want sensitive credentials kept out of the repository, so that database passwords, API keys, and SMTP credentials are never exposed publicly.

#### Acceptance Criteria

1. WHEN the Git repository is initialized, THE Cleanup_Tool SHALL verify that `config.php` is listed in `.gitignore` before staging any files.
2. THE Cleanup_Tool SHALL retain `config-production-template.php` in the repository as a safe reference for deployment configuration.
3. THE Config_Template SHALL contain no real credentials, only placeholder values.
4. THE Cleanup_Tool SHALL create a `config.example.php` file (if one does not already exist) that mirrors the structure of `config.php` with all credential values replaced by descriptive placeholders.
5. IF `config.php` or `database.php` are found staged for commit, THEN THE Cleanup_Tool SHALL remove them from the staging area before committing.

---

### Requirement 3: Remove Debug and Test Files

**User Story:** As a developer, I want debug and test files removed from the project, so that the repository contains only production-relevant code.

#### Acceptance Criteria

1. THE Cleanup_Tool SHALL delete `debug_event_update.php` from the Project_Root.
2. THE Cleanup_Tool SHALL delete `test_brevo.php` from the Project_Root.
3. THE Cleanup_Tool SHALL delete `test_event_update.php` from the Project_Root.
4. THE Cleanup_Tool SHALL delete `test_modal.php` from the Project_Root.
5. WHEN a Debug_File is deleted, THE Cleanup_Tool SHALL confirm the file no longer exists in the Project_Root.

---

### Requirement 4: Resolve Duplicate Files

**User Story:** As a developer, I want duplicate and superseded files removed, so that the codebase is unambiguous and maintainable.

#### Acceptance Criteria

1. THE Cleanup_Tool SHALL identify which file in each duplicate pair is the current, active version before deleting the other.
2. WHEN `delete_event_new.php` is confirmed as the active version, THE Cleanup_Tool SHALL delete `delete_event.php`.
3. WHEN `edit_event.php` is confirmed as the active version, THE Cleanup_Tool SHALL delete `edit_event_simple.php`.
4. IF the active version of a duplicate pair cannot be determined, THEN THE Cleanup_Tool SHALL present both files to the developer for manual review before deletion.
5. WHEN a Duplicate_File is deleted, THE Cleanup_Tool SHALL confirm the file no longer exists in the Project_Root.

---

### Requirement 5: Handle the Uploads Directory

**User Story:** As a developer, I want the uploads directory excluded from Git but preserved locally, so that user data is not committed to the repository while the directory structure is maintained for the running application.

#### Acceptance Criteria

1. THE Gitignore SHALL exclude all contents of the `uploads/` directory.
2. THE Cleanup_Tool SHALL create a `uploads/.gitkeep` placeholder file so that the `uploads/` directory structure is tracked in the repository without including user files.
3. THE Cleanup_Tool SHALL create per-subdirectory `.gitkeep` files in `uploads/badge_icons/`, `uploads/profile_pictures/`, `uploads/proofs/`, and `uploads/waivers/` to preserve the subdirectory structure.

---

### Requirement 6: Handle Vendor Dependencies

**User Story:** As a developer, I want the vendor directory excluded from the repository, so that Composer-managed dependencies are not bloating the repository size.

#### Acceptance Criteria

1. THE Gitignore SHALL exclude the `vendor/` directory.
2. THE Cleanup_Tool SHALL verify that `composer.json` and `composer.lock` are present in the Project_Root and included in the commit, so that dependencies can be restored with `composer install`.

---

### Requirement 7: Initialize Git Repository and Make Initial Commit

**User Story:** As a developer, I want the project initialized as a Git repository with a clean initial commit, so that the codebase history starts from a known good state.

#### Acceptance Criteria

1. THE Cleanup_Tool SHALL initialize a Git repository in the Project_Root using `git init`.
2. WHEN the repository is initialized, THE Cleanup_Tool SHALL stage all files not excluded by the Gitignore.
3. THE Cleanup_Tool SHALL verify that no Sensitive_File appears in the staged file list before committing.
4. THE Cleanup_Tool SHALL create an initial commit with a descriptive commit message.
5. THE Cleanup_Tool SHALL set the default branch name to `main`.

---

### Requirement 8: Push to GitHub

**User Story:** As a developer, I want the project pushed to a GitHub repository, so that the code is backed up and accessible remotely.

#### Acceptance Criteria

1. THE Cleanup_Tool SHALL add the GitHub remote URL as `origin` before pushing.
2. WHEN the remote is configured, THE Cleanup_Tool SHALL push the `main` branch to the `origin` remote.
3. IF the push is rejected due to an existing remote history, THEN THE Cleanup_Tool SHALL inform the developer and provide the appropriate command to resolve the conflict.
4. WHEN the push succeeds, THE Cleanup_Tool SHALL confirm the repository is accessible at the provided GitHub URL.
