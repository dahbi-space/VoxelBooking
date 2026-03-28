#!/usr/bin/env python3
"""
Localization Audit Script — VoxelBooking

Scans PHP templates for hardcoded English strings that should use __() calls.
Returns exit code 0 if clean, 1 if hardcoded strings are found.

Usage:
    python3 scripts/audit-i18n.py
    python3 scripts/audit-i18n.py --verbose
"""

import re
import sys
import os

# Surfaces to audit (relative to project root)
SURFACES = {
    'admin': [
        'templates/admin/layout.php',
        'templates/admin/dashboard.php',
        'templates/admin/deletion-queue.php',
        'templates/admin/settings/general.php',
        'templates/admin/settings/account.php',
        'templates/admin/settings/email.php',
        'templates/admin/settings/cron.php',
        'templates/admin/settings/logs.php',
        'templates/admin/settings/audit.php',
        'templates/partials/settings-tabs.php',
    ],
    'privacy': [
        'templates/booking/privacy.php',
        'templates/booking/privacy-anonymized.php',
        'templates/booking/privacy-deletion-requested.php',
    ],
    'auth': [
        'templates/auth/login.php',
        'templates/auth/error-403.php',
        'templates/auth/error-404.php',
        'templates/auth/error-429.php',
        'templates/auth/error-500.php',
    ],
    'install': [
        'templates/install/wizard.php',
    ],
}

# Brand/product names and technical terms that are OK to keep hardcoded
OK_WORDS = frozenset({
    'VoxelBooking', 'UTF-8', 'SSL', 'TLS', 'PHP', 'MySQL', 'PDO', 'SMTP',
    'None', 'POST', 'GET', 'JSON', 'CSS', 'HTML', 'Lucide', 'PRD', 'WOFF2',
    'Inter', 'System', 'Step', 'Copyright', 'N/A', 'Helvetica', 'SansSerif',
})


def audit_file(filepath: str) -> list[tuple[int, str]]:
    """Return list of (line_number, hardcoded_text) tuples."""
    findings = []

    if not os.path.isfile(filepath):
        return findings

    with open(filepath) as fh:
        for i, line in enumerate(fh, 1):
            stripped = line.strip()

            # Skip non-content lines
            if not stripped or stripped.startswith('//') or stripped.startswith('*'):
                continue
            if stripped.startswith('/**') or stripped.startswith('/*'):
                continue
            # Skip CSS
            if stripped.startswith('.') or stripped.startswith('{') or stripped.startswith('}'):
                continue
            if stripped.startswith('--') or stripped.startswith('background') or stripped.startswith('font'):
                continue
            if stripped.startswith('@') or stripped.startswith('src:'):
                continue

            # Find text between > and < that isn't a PHP echo
            matches = re.findall(r'>([A-Z][A-Za-z][^<]*?)<', line)
            for m in matches:
                m = m.strip()
                if not m or len(m) < 3:
                    continue
                if '<?=' in m:
                    continue
                first_word = m.split()[0] if m.split() else ''
                if first_word in OK_WORDS:
                    continue
                findings.append((i, m))

    return findings


def main():
    verbose = '--verbose' in sys.argv
    project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    os.chdir(project_root)

    total_issues = 0
    total_files = 0
    clean_surfaces = []

    for surface_name, files in SURFACES.items():
        surface_issues = 0
        for f in files:
            total_files += 1
            findings = audit_file(f)
            if findings:
                surface_issues += len(findings)
                total_issues += len(findings)
                for line_num, text in findings:
                    print(f"  ✗ {f}:{line_num}: {text}")
            elif verbose:
                print(f"  ✓ {f}")

        if surface_issues == 0:
            clean_surfaces.append(surface_name)
        else:
            print(f"\n⚠ {surface_name}: {surface_issues} hardcoded string(s) found\n")

    # Summary
    print(f"\n{'═' * 60}")
    print(f"i18n Audit Summary")
    print(f"{'═' * 60}")
    print(f"Files scanned:     {total_files}")
    print(f"Clean surfaces:    {', '.join(clean_surfaces) if clean_surfaces else 'none'}")
    print(f"Hardcoded strings: {total_issues}")

    if total_issues == 0:
        print(f"\n✓ All {total_files} templates are translation-clean.")
        return 0
    else:
        print(f"\n✗ {total_issues} hardcoded string(s) need translation keys.")
        return 1


if __name__ == '__main__':
    sys.exit(main())
