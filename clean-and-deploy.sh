#!/usr/bin/env bash
# clean-and-deploy.sh
# Cleans the Boy Scout Management System project and publishes it to GitHub.
# Usage: bash clean-and-deploy.sh <github-remote-url>

set -e

# ---------------------------------------------------------------------------
# Task 2.1 — Argument validation and project-root guard
# ---------------------------------------------------------------------------

if [ "$#" -ne 1 ]; then
    echo "Usage: bash clean-and-deploy.sh <github-remote-url>"
    echo "Example: bash clean-and-deploy.sh https://github.com/user/repo.git"
    exit 1
fi

REMOTE_URL="$1"

# Detect project root by checking for config.php or composer.json
if [ ! -f "config.php" ] && [ ! -f "composer.json" ]; then
    echo "Error: Run this script from the project root."
    exit 1
fi

echo "Project root confirmed."

# ---------------------------------------------------------------------------
# Task 2.2 — Phase 1: Verify .gitignore exists
# ---------------------------------------------------------------------------

echo ""
echo "[Phase 1] Checking .gitignore..."

if [ ! -f ".gitignore" ]; then
    echo "Error: .gitignore not found. Please create it before running this script."
    exit 1
fi

echo "[Phase 1] .gitignore confirmed."

# ---------------------------------------------------------------------------
# Task 2.3 — Phase 2: config.example.php + verify config.php is in .gitignore
# ---------------------------------------------------------------------------

echo ""
echo "[Phase 2] Protecting sensitive configuration files..."

# Create config.example.php if it does not exist
if [ ! -f "config.example.php" ]; then
    echo "[Phase 2] Creating config.example.php..."
    cat > config.example.php << 'EOF'
<?php
// Set default timezone
date_default_timezone_set('Asia/Manila');

$host = "your_db_host";
$user = "your_db_user";
$password = "your_db_password";
$database = "your_db_name";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// SMTP Configuration
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'your_smtp_host');
    define('SMTP_USER', 'your_smtp_username');
    define('SMTP_PASS', 'your_smtp_password');
    define('SMTP_PORT', 587);
}

// Brevo API Configuration
if (!defined('BREVO_API_KEY')) {
    define('BREVO_API_KEY', 'your_brevo_api_key');
}

// Brevo Sender Configuration
if (!defined('BREVO_SENDER_EMAIL')) {
    define('BREVO_SENDER_EMAIL', 'your_sender_email');
    define('BREVO_SENDER_NAME', 'your_sender_name');
}
?>
EOF
    echo "[Phase 2] config.example.php created."
else
    echo "[Phase 2] config.example.php already exists."
fi

# Verify config.php is listed in .gitignore
if ! grep -qx "config.php" .gitignore; then
    echo "Error: config.php is not listed in .gitignore. Aborting to prevent credential exposure."
    exit 1
fi

echo "[Phase 2] config.php is listed in .gitignore. Sensitive files are protected."

# ---------------------------------------------------------------------------
# Task 3.1 — Phase 3: Delete debug/test files
# ---------------------------------------------------------------------------

echo ""
echo "[Phase 3] Removing debug/test files..."

for debug_file in debug_event_update.php test_brevo.php test_event_update.php test_modal.php; do
    if [ -f "$debug_file" ]; then
        rm "$debug_file"
        if [ ! -f "$debug_file" ]; then
            echo "[Phase 3] Deleted: $debug_file"
        else
            echo "Error: Failed to delete $debug_file"
            exit 1
        fi
    fi
done

echo "[Phase 3] Debug/test files removed."

# ---------------------------------------------------------------------------
# Task 3.2 — Phase 4: Delete superseded duplicate files
# ---------------------------------------------------------------------------

echo ""
echo "[Phase 4] Removing superseded duplicate files..."

# delete_event.php — active version is delete_event_new.php
if [ -f "delete_event.php" ]; then
    rm "delete_event.php"
    if [ ! -f "delete_event.php" ]; then
        echo "[Phase 4] Deleted: delete_event.php (active version: delete_event_new.php)"
    else
        echo "Error: Failed to delete delete_event.php"
        exit 1
    fi
fi

# edit_event_simple.php — active version is edit_event.php
if [ -f "edit_event_simple.php" ]; then
    rm "edit_event_simple.php"
    if [ ! -f "edit_event_simple.php" ]; then
        echo "[Phase 4] Deleted: edit_event_simple.php (active version: edit_event.php)"
    else
        echo "Error: Failed to delete edit_event_simple.php"
        exit 1
    fi
fi

echo "[Phase 4] Duplicate files removed."

# ---------------------------------------------------------------------------
# Task 4.1 — Phase 5: Create .gitkeep placeholders in uploads/ subdirectories
# ---------------------------------------------------------------------------

echo ""
echo "[Phase 5] Setting up uploads/ directory placeholders..."

for dir in uploads uploads/badge_icons uploads/profile_pictures uploads/proofs uploads/waivers; do
    mkdir -p "$dir"
    touch "$dir/.gitkeep"
done

echo "[Phase 5] .gitkeep placeholders created."

# ---------------------------------------------------------------------------
# Task 4.2 — Phase 6: Verify composer.json and composer.lock exist
# ---------------------------------------------------------------------------

echo ""
echo "[Phase 6] Verifying Composer files..."

if [ ! -f "composer.json" ]; then
    echo "Error: composer.json not found. Cannot ensure dependencies are restorable."
    exit 1
fi

if [ ! -f "composer.lock" ]; then
    echo "Error: composer.lock not found. Cannot ensure dependencies are restorable."
    exit 1
fi

echo "[Phase 6] composer.json and composer.lock confirmed."

# ---------------------------------------------------------------------------
# Tasks 6.1–6.4 — Phase 7: git init, stage, safety-check, commit
# ---------------------------------------------------------------------------

echo ""
echo "[Phase 7] Initializing Git repository..."

# Task 6.1 — git init and branch rename to main (Requirements: 7.1, 7.5)
git init

# Handle both new repos (no commits yet) and repos that already have a main branch
git checkout -b main 2>/dev/null || git checkout main

# Task 6.2 — Stage all files not excluded by .gitignore (Requirements: 7.2)
git add -A

# Task 6.3 — Sensitive-file safety check (Requirements: 2.5, 7.3)
STAGED_FILES=$(git diff --cached --name-only)

if echo "$STAGED_FILES" | grep -q "^config\.php$"; then
    git restore --staged config.php
    echo "WARNING: config.php was found in staged files and has been removed from staging. It must not be committed."
fi

if echo "$STAGED_FILES" | grep -q "^database\.php$"; then
    git restore --staged database.php
    echo "WARNING: database.php was found in staged files and has been removed from staging. It must not be committed."
fi

# Task 6.4 — Create the initial commit (Requirements: 7.4)
git commit -m "Initial commit: Boy Scout Management System - clean production release"

echo "[Phase 7] Initial commit created on branch main."

# ---------------------------------------------------------------------------
# Tasks 7.1–7.3 — Phase 8: Configure GitHub remote and push to origin
# ---------------------------------------------------------------------------

echo ""
echo "[Phase 8] Configuring GitHub remote and pushing..."

# Task 7.1 — Add or update the remote origin (Requirements: 8.1)
if git remote get-url origin 2>/dev/null; then
    git remote set-url origin "$REMOTE_URL"
    echo "[Phase 8] Remote 'origin' updated to: $REMOTE_URL"
else
    git remote add origin "$REMOTE_URL"
    echo "[Phase 8] Remote 'origin' added: $REMOTE_URL"
fi

# Task 7.2 — Push main to origin; handle rejection gracefully (Requirements: 8.2, 8.3)
set +e
git push -u origin main
PUSH_EXIT=$?
set -e

if [ $PUSH_EXIT -ne 0 ]; then
    echo ""
    echo "ERROR: Push to origin was rejected. The remote repository has existing history"
    echo "that conflicts with your local branch."
    echo ""
    echo "To resolve this conflict, choose one of the following options:"
    echo ""
    echo "  Option 1 (recommended — safe): Pull and rebase, then re-run this script."
    echo "    git pull --rebase origin main"
    echo "    bash clean-and-deploy.sh $REMOTE_URL"
    echo ""
    echo "  Option 2 (destructive — overwrites remote history):"
    echo "    WARNING: This will permanently overwrite the remote history."
    echo "    git push --force-with-lease origin main"
    echo ""
    exit 1
fi

# Task 7.3 — Confirm successful push (Requirements: 8.4)
echo ""
echo "[Phase 8] Success! Repository pushed to: $REMOTE_URL"
echo ""
echo "Deployment complete. Your project is live at: $REMOTE_URL"
