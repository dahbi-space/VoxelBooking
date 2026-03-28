#!/usr/bin/env python3
"""
i18n Localization Audit — VoxelBooking

Scans PHP templates for hardcoded English text that should use __() calls.
Exits 0 if all surfaces are clean, 1 if hardcoded strings remain.

Usage:
    python3 scripts/audit-i18n.py            # summary only
    python3 scripts/audit-i18n.py --verbose   # per-file results
"""

import os
import re
import sys

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

OK_FIRST_WORDS = frozenset({
    'VoxelBooking', 'UTF', 'SSL', 'TLS', 'PHP', 'MySQL', 'PDO', 'SMTP',
    'None', 'POST', 'GET', 'JSON', 'CSS', 'HTML', 'Lucide', 'PRD', 'WOFF2',
    'Inter', 'System', 'Step', 'Copyright', 'Helvetica', 'SansSerif',
})

SKIP_LINE_STARTS = (
    '//', '*', '/**', '/*', '.', '{', '}', '--', 'background', 'font',
    '@', 'src:', '$', 'var(', 'color', 'border', 'padding', 'margin',
    'display', 'position', 'width', 'height', 'min-', 'max-', 'overflow',
    'transition', 'transform', 'animation', 'opacity', 'cursor', 'text-',
    'letter-', 'white-', 'flex', 'grid', 'gap', 'align', 'justify',
    'box-', 'user-', 'filter', 'backdrop',
)


def audit_file(filepath):
    findings = []
    if not os.path.isfile(filepath):
        return findings
    with open(filepath) as fh:
        for i, line in enumerate(fh, 1):
            s = line.strip()
            if not s or any(s.startswith(p) for p in SKIP_LINE_STARTS):
                continue
            for tag in ('svg', 'path', 'rect', 'polygon', 'line', 'polyline',
                        'circle', 'stop', 'linearGradient', 'defs'):
                if s.startswith(f'<{tag}'):
                    break
            else:
                for m in re.findall(r'>([A-Z][A-Za-z][^<]*?)<', line):
                    m = m.strip()
                    if not m or len(m) < 3 or '<?=' in m:
                        continue
                    first = m.split()[0]
                    if first in OK_FIRST_WORDS:
                        continue
                    findings.append((i, m))
    return findings


def main():
    verbose = '--verbose' in sys.argv
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    os.chdir(root)
    total, scanned, clean = 0, 0, []
    for name, files in SURFACES.items():
        issues = 0
        for f in files:
            scanned += 1
            hits = audit_file(f)
            if hits:
                issues += len(hits)
                total += len(hits)
                for ln, txt in hits:
                    print(f'  ✗ {f}:{ln}: {txt}')
            elif verbose:
                print(f'  ✓ {f}')
        if issues == 0:
            clean.append(name)
        else:
            print(f'\n⚠ {name}: {issues} hardcoded string(s)\n')
    print(f'\n{"═" * 50}')
    print(f'Files: {scanned}  Clean: {", ".join(clean)}  Issues: {total}')
    if total == 0:
        print(f'✓ All {scanned} templates are translation-clean.')
    else:
        print(f'✗ {total} hardcoded string(s) need __() calls.')
    return 0 if total == 0 else 1


if __name__ == '__main__':
    sys.exit(main())
