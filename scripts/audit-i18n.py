#!/usr/bin/env python3
"""
i18n Localization Audit — VoxelBooking

Scans PHP templates and JS source files for hardcoded English text
that should use __() or t() calls respectively.
Exits 0 if all surfaces are clean, 1 if hardcoded strings remain.

Usage:
    python3 scripts/audit-i18n.py            # summary only
    python3 scripts/audit-i18n.py --verbose   # per-file results
"""

import os
import re
import sys

# ── PHP template surfaces ──
PHP_SURFACES = {
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

# ── JS source surfaces ──
JS_SURFACES = {
    'booking-js': [
        'resources/js/booking/app.js',
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


def audit_php_file(filepath):
    """Scan a PHP template for hardcoded text between > and < not using <?=."""
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


# Patterns that are OK not to translate in JS
JS_OK_PATTERNS = frozenset({
    'Accept', 'Content', 'VoxelBooking',
})

def audit_js_file(filepath):
    """Scan a JS file for hardcoded English strings not wrapped in t()."""
    findings = []
    if not os.path.isfile(filepath):
        return findings

    with open(filepath) as fh:
        for i, line in enumerate(fh, 1):
            s = line.strip()
            # Skip comments and blank lines
            if not s or s.startswith('//') or s.startswith('*') or s.startswith('/*'):
                continue

            # 1. Check aria-label="..." attributes not using t()
            for m in re.findall(r'aria-label="([^"$][^"]*)"', line):
                if '${' in m or 't(' in line.split(m)[0][-20:]:
                    continue
                findings.append((i, f'aria-label="{m}"'))

            # 2. Check title="..." attributes not using t()
            for m in re.findall(r'title="([^"$][^"]*)"', line):
                if '${' in m:
                    continue
                findings.append((i, f'title="{m}"'))

            # 3. Check >Text< in template literals not using t()
            for m in re.findall(r'>([A-Z][a-z][^<$]*?)</', line):
                m = m.strip()
                if not m or len(m) < 3 or '${' in m:
                    continue
                first = m.split()[0]
                if first in JS_OK_PATTERNS:
                    continue
                findings.append((i, m))

            # 4. Check standalone hardcoded strings assigned or returned
            #    e.g. = 'Some English text';
            for m in re.findall(r"(?:=|:|\|\|)\s*'([A-Z][a-z][^']{4,})'", line):
                if "t('" in line:
                    continue
                first = m.split()[0]
                if first in JS_OK_PATTERNS:
                    continue
                findings.append((i, m))

    return findings


def main():
    verbose = '--verbose' in sys.argv
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    os.chdir(root)
    total, scanned, clean = 0, 0, []

    # PHP surfaces
    for name, files in PHP_SURFACES.items():
        issues = 0
        for f in files:
            scanned += 1
            hits = audit_php_file(f)
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

    # JS surfaces
    for name, files in JS_SURFACES.items():
        issues = 0
        for f in files:
            scanned += 1
            hits = audit_js_file(f)
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
        print(f'✓ All {scanned} files are translation-clean.')
    else:
        print(f'✗ {total} hardcoded string(s) need translation calls.')
    return 0 if total == 0 else 1


if __name__ == '__main__':
    sys.exit(main())
